<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\SitterReview;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\BookingService;
use App\Services\GpsService;
use App\Services\MidtransService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Midtrans\Snap;

class BookingController extends Controller
{
    protected BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Booking::with(['user', 'room', 'sitter', 'sitterReview', 'cats']);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Standard production practice: Paginate responses to prevent out-of-memory errors
        $bookings = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($bookings);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_type' => 'required|string|in:board,sitter',
            'room_id' => 'exclude_if:booking_type,sitter|required_if:booking_type,board|exists:rooms,id',
            'sitter_id' => 'nullable|exists:sitters,id',
            'sitter_package' => 'nullable|exists:sitter_packages,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after_or_equal:check_in',
            'cat_ids' => 'required|array|min:1',
            'cat_ids.*' => 'exists:cats,id',
            'notes' => 'nullable|string',
            'visit_time' => 'nullable|string|in:morning,afternoon,both,none',
            'coupon_code' => 'nullable|string|max:50',
        ]);

        // Security & Ownership Guard: Verify that the selected cats belong to the authenticated user
        $userCatCount = \App\Models\Cat::where('user_id', Auth::id())
            ->whereIn('id', $validated['cat_ids'])
            ->count();
        if ($userCatCount !== count($validated['cat_ids'])) {
            return response()->json(['message' => 'Salah satu kucing yang dipilih tidak valid atau bukan milik Anda.'], 422);
        }

        $validated['total_cats'] = count($validated['cat_ids']);

        try {
            return DB::transaction(function () use ($validated) {
                $totalPrice = 0;

                if ($validated['booking_type'] === 'board') {
                    if (! $this->bookingService->isRoomAvailable($validated['room_id'], $validated['check_in'], $validated['check_out'])) {
                        return response()->json([
                            'message' => 'Kamar ini sudah di-booking pada tanggal tersebut.',
                        ], 422);
                    }
                    $totalPrice = $this->bookingService->calculateBoardingPrice($validated['room_id'], $validated['check_in'], $validated['check_out']);
                } else {
                    $visitTime = $validated['visit_time'] ?? 'none';
                    if (! $this->bookingService->isSitterAvailable($validated['sitter_id'], $validated['check_in'], $validated['check_out'], $visitTime)) {
                        return response()->json([
                            'message' => 'Sitter ini sudah memiliki jadwal penuh (bentrok) pada tanggal dan shift tersebut.',
                        ], 422);
                    }
                    $totalPrice = $this->bookingService->calculateSitterPrice($validated['sitter_package'], $validated['check_in'], $validated['check_out'], $validated['total_cats']);
                }

                // --- COUPON DISCOUNT ---
                $couponId = null;
                $discountAmount = 0;
                if (! empty($validated['coupon_code'])) {
                    $coupon = Coupon::where('code', strtoupper($validated['coupon_code']))->first();
                    if ($coupon && $coupon->isUsable()) {
                        $discountAmount = $coupon->calculateDiscount($totalPrice);
                        if ($discountAmount > 0) {
                            $couponId = $coupon->id;
                            $totalPrice = max(0, $totalPrice - $discountAmount);
                            $coupon->increment('used_count');
                        }
                    }
                }
                // --- END COUPON DISCOUNT ---

                $user = Auth::user();

                $booking = Booking::create([
                    'user_id' => $user->id,
                    'booking_type' => $validated['booking_type'],
                    'room_id' => $validated['booking_type'] === 'board' ? $validated['room_id'] : null,
                    'sitter_id' => $validated['sitter_id'] ?? null,
                    'sitter_package' => $validated['sitter_package'] ?? null,
                    'coupon_id' => $couponId,
                    'check_in' => $validated['check_in'],
                    'check_out' => $validated['check_out'],
                    'total_cats' => $validated['total_cats'],
                    'total_price' => $totalPrice,
                    'discount_amount' => $discountAmount,
                    'visit_time' => $validated['visit_time'] ?? 'none',
                    'status' => 'pending',
                    'notes' => $validated['notes'] ?? null,
                ]);

                // Sync the selected cats pivot relation
                $booking->cats()->sync($validated['cat_ids']);

                // --- MIDTRANS INTEGRATION ---
                $midtransService = app(MidtransService::class);
                $midtransService->configureSnap();

                $orderId = 'BKG-'.$booking->id.'-'.time();
                $params = [
                    'transaction_details' => [
                        'order_id' => $orderId,
                        'gross_amount' => $totalPrice,
                    ],
                    'customer_details' => [
                        'first_name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone ?? '081234567890',
                    ],
                ];

                try {
                    $snapToken = Snap::getSnapToken($params);
                    $booking->update(['snap_token' => $snapToken, 'midtrans_order_id' => $orderId]);
                } catch (\Exception $e) {
                    \Log::error('Midtrans Snap Error: '.$e->getMessage().' | Trace: '.$e->getTraceAsString());
                    if (app()->environment('local', 'testing')) {
                        $booking->update([
                            'snap_token' => 'dummy_token_local_testing_'.time(),
                            'midtrans_order_id' => $orderId,
                        ]);
                    } else {
                        throw $e;
                    }
                }
                // --- END MIDTRANS INTEGRATION ---

                // Notify admins
                $admins = User::where('role', 'admin')->get();
                foreach ($admins as $admin) {
                    $admin->notify(new AppNotification(
                        'Pesanan Baru',
                        "Pesanan baru ({$booking->booking_type}) dari {$user->name}",
                        'info',
                        '/admin/reservations'
                    ));
                }

                return response()->json($booking->load(['user', 'room', 'sitter', 'cats']), 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memproses pesanan: ' . $e->getMessage()], 500);
        }
    }

    public function paySuccess(Booking $booking)
    {
        // CRITICAL SECURITY: Only admin can manually mark payment as paid.
        // Regular users must pay through Midtrans gateway (webhook handles status update).
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized — payment must go through payment gateway'], 403);
        }

        if ($booking->payment_status !== 'paid') {
            $booking->update(['payment_status' => 'paid']);

            // Send WhatsApp notification ONLY after successful payment
            app(WhatsappService::class)->sendBookingConfirmation($booking);
        }

        return response()->json($booking);
    }

    public function show(string $id)
    {
        $booking = Booking::with(['user', 'room', 'sitter', 'sitterReview', 'cats'])->findOrFail($id);

        if (Auth::user()->role !== 'admin' && $booking->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($booking);
    }

    public function update(Request $request, string $id)
    {
        $booking = Booking::findOrFail($id);
        $user = Auth::user();

        if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rules = [
            'notes' => 'sometimes|nullable|string',
        ];

        if ($user->role === 'admin') {
            $rules['status'] = 'sometimes|required|string|in:pending,approved,rejected,checked_in,checked_out,cancelled';
            $rules['sitter_id'] = 'sometimes|nullable|exists:sitters,id';
        }

        $validated = $request->validate($rules);

        // Prevent sitter reassignment on completed/cancelled bookings
        if (isset($validated['sitter_id']) && ! in_array($booking->status, ['pending', 'approved'])) {
            return response()->json(['message' => 'Sitter hanya bisa diganti pada booking yang masih pending atau approved'], 422);
        }

        $booking->update($validated);

        if (isset($validated['status']) && $user->role === 'admin') {
            // Auto-refund when admin cancels a paid booking
            if ($validated['status'] === 'cancelled' && $booking->payment_status === 'paid') {
                $this->processRefund($booking);
            }

            $statusLabels = [
                'approved' => 'disetujui',
                'rejected' => 'ditolak',
                'checked_in' => 'sedang berjalan',
                'checked_out' => 'telah selesai',
                'cancelled' => 'dibatalkan',
            ];
            $label = $statusLabels[$validated['status']] ?? $validated['status'];

            $booking->user->notify(new AppNotification(
                'Status Pesanan Diperbarui',
                "Pesanan Anda ({$booking->booking_type}) kini berstatus: $label.",
                $validated['status'] === 'cancelled' || $validated['status'] === 'rejected' ? 'error' : 'success',
                '/dashboard/history'
            ));
        }

        return new BookingResource($booking->load(['user', 'room', 'sitter', 'coupon']));
    }

    public function destroy(string $id)
    {
        $booking = Booking::findOrFail($id);
        $user = Auth::user();

        // Block deleting paid bookings for non-admin users.
        // Paid bookings must go through the cancellation/refund workflow.
        if ($user->role !== 'admin' && $booking->payment_status === 'paid') {
            return response()->json(['message' => 'Tidak dapat menghapus pesanan yang sudah dibayar. Gunakan fitur batalkan pesanan untuk memproses refund.'], 400);
        }

        if ($user->role !== 'admin') {
            if ($booking->user_id !== $user->id || $booking->status !== 'pending') {
                return response()->json(['message' => 'Unauthorized or booking cannot be deleted'], 403);
            }
        } else {
            // Admin Guard: Prevent deleting bookings that are paid and active (checked_in, approved, etc.)
            // to ensure financial data integrity and avoid accidental data loss.
            if ($booking->payment_status === 'paid' && in_array($booking->status, ['approved', 'checked_in', 'checked_out'])) {
                return response()->json(['message' => 'Tidak dapat menghapus pesanan aktif yang sudah lunas.'], 400);
            }
        }

        $booking->delete(); // Soft deletes since Booking uses SoftDeletes

        return response()->json(['message' => 'Booking deleted successfully']);
    }

    /**
     * Submit a review for a sitter on a booking
     */
    public function reviewSitter(Request $request, string $bookingId)
    {
        $booking = Booking::findOrFail($bookingId);

        // Ensure the user owns this booking
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ensure booking has a sitter
        if (! $booking->sitter_id) {
            return response()->json(['message' => 'Booking ini tidak punya sitter'], 422);
        }

        // Ensure booking is completed
        if (! in_array($booking->status, ['checked_out'])) {
            return response()->json(['message' => 'Hanya bisa review booking yang sudah selesai'], 422);
        }

        // Check if already reviewed
        if ($booking->sitterReview) {
            return response()->json(['message' => 'Sudah pernah direview'], 422);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $review = SitterReview::create([
            'user_id' => Auth::id(),
            'sitter_id' => $booking->sitter_id,
            'booking_id' => $booking->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json($review->load(['user', 'sitter']), 201);
    }

    /**
     * User-initiated booking cancellation with auto-refund.
     */
    public function cancelBooking(Request $request, Booking $booking)
    {
        $user = Auth::user();

        // User can only cancel their own bookings
        if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Can only cancel pending or approved bookings
        if (! in_array($booking->status, ['pending', 'approved'])) {
            return response()->json(['message' => 'Booking ini tidak bisa dibatalkan'], 422);
        }

        $refundInfo = null;

        // Process refund if the booking was already paid
        if ($booking->payment_status === 'paid') {
            $refundInfo = $this->processRefund($booking);
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        // Notify user
        $refundMsg = '';
        if ($booking->payment_status === 'paid') {
            if ($booking->refund_status === 'pending' || $booking->refund_status === 'processed') {
                $refundMsg = ' Refund sebesar Rp ' . number_format($booking->refund_amount, 0, ',', '.') . ' sedang diproses.';
            } else {
                $refundMsg = ' Pembatalan dilakukan kurang dari H-2, tidak ada refund.';
            }
        }

        if ($booking->user) {
            $booking->user->notify(new AppNotification(
                'Pesanan Dibatalkan',
                "Pesanan Anda ({$booking->booking_type}) telah dibatalkan.{$refundMsg}",
                'error',
                '/dashboard/history'
            ));
        }

        // Notify admins
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new AppNotification(
                'Pesanan Dibatalkan',
                "Pesanan ({$booking->booking_type}) dari " . ($booking->user->name ?? 'Pelanggan') . " telah dibatalkan.",
                'warning',
                '/admin/reservations'
            ));
        }

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan' . $refundMsg,
            'booking' => $booking->fresh()->load(['user', 'room', 'sitter', 'coupon']),
            'refund' => $refundInfo,
        ]);
    }

    /**
     * GPS-verified sitter check-in.
     */
    public function sitterCheckin(Request $request, Booking $booking)
    {
        $user = Auth::user();

        // Only admin can perform sitter check-in (sitters don't have accounts yet, admin operates)
        // OR if we expand: the booking owner/sitter can check in
        if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Must be a sitter booking
        if ($booking->booking_type !== 'sitter') {
            return response()->json(['message' => 'Fitur check-in GPS hanya untuk layanan sitter'], 422);
        }

        // Must be approved
        if ($booking->status !== 'approved') {
            return response()->json(['message' => 'Booking harus berstatus approved untuk check-in'], 422);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        // Get customer's saved location
        $customer = $booking->user;
        if (! $customer->latitude || ! $customer->longitude) {
            return response()->json([
                'message' => 'Pelanggan belum menyimpan lokasi di profil. Minta pelanggan update lokasi terlebih dahulu.',
            ], 422);
        }

        $gpsService = app(GpsService::class);
        $radius = $gpsService->getRadius();
        $result = $gpsService->isWithinRadius(
            $validated['latitude'],
            $validated['longitude'],
            $customer->latitude,
            $customer->longitude,
            $radius
        );

        $booking->update([
            'checkin_lat' => $validated['latitude'],
            'checkin_lng' => $validated['longitude'],
            'checkin_distance_m' => $result['distance'],
            'checkin_verified' => $result['within'],
        ]);

        if ($result['within']) {
            $booking->update(['status' => 'checked_in']);

            $booking->user->notify(new AppNotification(
                'Sitter Check-in Berhasil ✅',
                "Sitter telah check-in di lokasi Anda (jarak: {$result['distance']}m). Kunjungan dimulai!",
                'success',
                '/dashboard/history'
            ));

            return response()->json([
                'message' => 'Check-in berhasil! Lokasi terverifikasi.',
                'verified' => true,
                'distance' => $result['distance'],
                'radius' => $radius,
                'booking' => $booking->fresh()->load(['user', 'room', 'sitter']),
            ]);
        }

        return response()->json([
            'message' => "Lokasi tidak terverifikasi. Jarak Anda {$result['distance']}m dari lokasi pelanggan (maks. {$radius}m).",
            'verified' => false,
            'distance' => $result['distance'],
            'radius' => $radius,
        ], 422);
    }

    /**
     * Process refund for a booking based on H-2 policy.
     */
    private function processRefund(Booking $booking): array
    {
        if (! $booking->isRefundEligible()) {
            $booking->update([
                'refund_status' => 'failed',
                'refund_amount' => 0,
                'cancelled_at' => now(),
            ]);
            return [
                'eligible' => false,
                'reason' => 'Pembatalan kurang dari H-2, tidak ada refund',
            ];
        }

        $refundAmount = (int) $booking->total_price;
        $booking->update([
            'refund_status' => 'pending',
            'refund_amount' => $refundAmount,
            'cancelled_at' => now(),
        ]);

        // Call Midtrans refund API
        if ($booking->midtrans_order_id) {
            $midtransService = app(MidtransService::class);
            $result = $midtransService->refundTransaction(
                $booking->midtrans_order_id,
                $refundAmount,
                'Pembatalan oleh pelanggan (H-2 policy)'
            );

            if ($result['success']) {
                $booking->update(['refund_status' => 'processed']);
            } else {
                \Log::warning('Refund API failed for booking #' . $booking->id, $result);
            }

            return [
                'eligible' => true,
                'amount' => $refundAmount,
                'midtrans_result' => $result,
            ];
        }

        return [
            'eligible' => true,
            'amount' => $refundAmount,
            'midtrans_result' => null,
        ];
    }
}
