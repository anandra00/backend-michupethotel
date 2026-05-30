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
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
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
        // 1-2 cats: 60k
        // 3-4 cats: 80k
        // 5+ cats: 120k
        $basePrice = 60000;
        if ($totalCats >= 3 && $totalCats <= 4) {
            $basePrice = 80000;
        } elseif ($totalCats >= 5) {
            $basePrice = 120000;
        }

        return ($days * $basePrice) + $adminFee;
    }
}
