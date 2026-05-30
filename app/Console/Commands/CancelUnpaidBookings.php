<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:cancel-unpaid')]
#[Description('Cancel pending bookings that have been unpaid for more than 1 hour')]
class CancelUnpaidBookings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bookings = Booking::where('status', 'pending')
            ->where('payment_status', 'unpaid')
            ->where('created_at', '<=', now()->subHour())
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'notes' => $booking->notes 
                    ? $booking->notes . "\n\n[System]: Pesanan dibatalkan otomatis karena tidak ada pembayaran dalam waktu 1 jam."
                    : "[System]: Pesanan dibatalkan otomatis karena tidak ada pembayaran dalam waktu 1 jam.",
            ]);
            $count++;
        }

        $this->info("Cancelled {$count} unpaid bookings.");
    }
}
