<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Models\Setting;
use App\Models\SitterPackage;

class BookingService
{
    /**
     * Check if a room is available for the given dates.
     */
    public function isRoomAvailable(int $roomId, string $checkIn, string $checkOut): bool
    {
        return ! Booking::where('room_id', $roomId)
            ->where('booking_type', 'board')
            ->whereIn('status', ['pending', 'approved', 'checked_in'])
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->exists();
    }

    /**
     * Check if a sitter is available for the given dates and visit time.
     */
    public function isSitterAvailable(int $sitterId, string $checkIn, string $checkOut, string $visitTime): bool
    {
        return ! Booking::where('sitter_id', $sitterId)
            ->where('booking_type', 'sitter')
            ->whereIn('status', ['pending', 'approved', 'checked_in'])
            // If they want 'both' they overlap with 'morning', 'afternoon' and 'both'
            // If they want 'morning', they overlap with 'morning' and 'both'
            ->where(function ($query) use ($visitTime) {
                if ($visitTime === 'both') {
                    $query->whereIn('visit_time', ['morning', 'afternoon', 'both']);
                } else {
                    $query->whereIn('visit_time', [$visitTime, 'both']);
                }
            })
            ->where('check_in', '<=', $checkOut)
            ->where('check_out', '>=', $checkIn)
            ->exists();
    }

    /**
     * Calculate price for Boarding.
     */
    public function calculateBoardingPrice(int $roomId, string $checkIn, string $checkOut): float
    {
        $d1 = new \DateTime($checkIn);
        $d2 = new \DateTime($checkOut);
        $nights = max(1, $d1->diff($d2)->days);

        $room = Room::findOrFail($roomId);

        return $nights * $room->price_per_night;
    }

    /**
     * Calculate price for Sitter.
     */
    public function calculateSitterPrice(int $packageId, string $checkIn, string $checkOut, int $totalCats): float
    {
        $d1 = new \DateTime($checkIn);
        $d2 = new \DateTime($checkOut);
        $days = $d1->diff($d2)->days + 1;

        $package = SitterPackage::findOrFail($packageId);

        $setting = Setting::where('key', 'admin_fee')->first();
        $adminFee = $setting ? (float) $setting->value : 5000;

        // Tiered pricing matching frontend rules:
        // 1-2 cats: 60k for 1x, 120k for 2x
        // 3-4 cats: 80k for 1x, 160k for 2x
        // 5+ cats: 120k for 1x, 240k for 2x
        $is2x = str_contains($package->name, '2x');
        $basePrice = 60000;
        if ($totalCats >= 3 && $totalCats <= 4) {
            $basePrice = 80000;
        } elseif ($totalCats >= 5) {
            $basePrice = 120000;
        }

        $pricePerDay = $is2x ? $basePrice * 2 : $basePrice;

        return ($days * $pricePerDay) + $adminFee;
    }

    /**
     * Process a new booking including price calculation, coupon logic, DB transaction, and Midtrans integration.
     */
    public function processBooking(array $validated, \App\Models\User $user): \App\Models\Booking
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $user) {
            $totalPrice = 0;

            if ($validated['booking_type'] === 'board') {
                if (! $this->isRoomAvailable($validated['room_id'], $validated['check_in'], $validated['check_out'])) {
                    throw new \Exception('Kamar ini sudah di-booking pada tanggal tersebut.');
                }
                $totalPrice = $this->calculateBoardingPrice($validated['room_id'], $validated['check_in'], $validated['check_out']);
            } else {
                $visitTime = $validated['visit_time'] ?? 'none';
                if (! $this->isSitterAvailable($validated['sitter_id'], $validated['check_in'], $validated['check_out'], $visitTime)) {
                    throw new \Exception('Sitter ini sudah memiliki jadwal penuh (bentrok) pada tanggal dan shift tersebut.');
                }
                $totalPrice = $this->calculateSitterPrice($validated['sitter_package'], $validated['check_in'], $validated['check_out'], $validated['total_cats']);
            }

            // --- COUPON DISCOUNT ---
            $couponId = null;
            $discountAmount = 0;
            if (! empty($validated['coupon_code'])) {
                $coupon = \App\Models\Coupon::where('code', strtoupper($validated['coupon_code']))->first();
                if ($coupon && $coupon->isUsable()) {
                    $discountAmount = $coupon->calculateDiscount($totalPrice);
                    if ($discountAmount > 0) {
                        $couponId = $coupon->id;
                        $totalPrice = max(0, $totalPrice - $discountAmount);
                        $coupon->increment('used_count');
                    }
                }
            }

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

            $booking->cats()->sync($validated['cat_ids']);

            // --- MIDTRANS INTEGRATION ---
            $midtransService = app(\App\Services\MidtransService::class);
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
                'callbacks' => [
                    'finish' => env('FRONTEND_URL', 'https://frontend-sage-theta.vercel.app') . '/dashboard/history',
                    'unfinish' => env('FRONTEND_URL', 'https://frontend-sage-theta.vercel.app') . '/dashboard/history',
                    'error' => env('FRONTEND_URL', 'https://frontend-sage-theta.vercel.app') . '/dashboard/history',
                ],
            ];

            try {
                $snapToken = \Midtrans\Snap::getSnapToken($params);
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

            // Notify admins
            $admins = \App\Models\User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\AppNotification(
                    'Pesanan Baru',
                    "Pesanan baru ({$booking->booking_type}) dari {$user->name}",
                    'info',
                    '/admin/reservations'
                ));
            }

            return $booking;
        });
    }
}
