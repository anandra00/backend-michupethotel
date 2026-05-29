<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SitterReview;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\BookingService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Midtrans\Config;
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

        $query = Booking::with(['user', 'room', 'sitter', 'sitterReview']);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $bookings = $query->orderBy('created_at', 'desc')->get();

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
            'total_cats' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'visit_time' => 'nullable|string|in:morning,afternoon,both,none',
        ]);

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

        $user = Auth::user();

        $booking = Booking::create([
            'user_id' => $user->id,
            'booking_type' => $validated['booking_type'],
            'room_id' => $validated['booking_type'] === 'board' ? $validated['room_id'] : null,
            'sitter_id' => $validated['sitter_id'] ?? null,
            'sitter_package' => $validated['sitter_package'] ?? null,
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'total_cats' => $validated['total_cats'],
            'total_price' => $totalPrice,
            'visit_time' => $validated['visit_time'] ?? 'none',
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        // --- MIDTRANS INTEGRATION ---
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;

        // Workaround for local Laragon/XAMPP cURL SSL certificate issues
        // and fix for Midtrans PHP library bug expecting CURLOPT_HTTPHEADER
        Config::$curlOptions = [
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => [],
        ];

        $params = [
            'transaction_details' => [
                'order_id' => 'BKG-'.$booking->id.'-'.time(),
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
            $booking->update(['snap_token' => $snapToken]);
        } catch (\Exception $e) {
            \Log::error('Midtrans Snap Error: '.$e->getMessage().' | Trace: '.$e->getTraceAsString());
            if (env('APP_ENV', 'local') === 'local') {
                // Fallback for local development so QA testing is not blocked by Midtrans credential issues
                $booking->update(['snap_token' => 'dummy_token_local_testing_'.time()]);
            } else {
                $booking->delete();

                return response()->json(['message' => 'Failed to generate payment token: '.$e->getMessage()], 500);
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

        return response()->json($booking->load(['user', 'room', 'sitter']), 201);
    }

    public function paySuccess(Booking $booking)
    {
        // For local development bypass to mark payment as paid
        // without waiting for Midtrans webhook which can't reach localhost
        if (Auth::user()->role !== 'admin' && $booking->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
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
        $booking = Booking::with(['user', 'room', 'sitter', 'sitterReview'])->findOrFail($id);

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

        return response()->json($booking->load(['user', 'room', 'sitter']));
    }

    public function destroy(string $id)
    {
        $booking = Booking::findOrFail($id);
        $user = Auth::user();

        if ($user->role !== 'admin') {
            if ($booking->user_id !== $user->id || $booking->status !== 'pending') {
                return response()->json(['message' => 'Unauthorized or booking cannot be deleted'], 403);
            }
        }

        $booking->delete();

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
}
