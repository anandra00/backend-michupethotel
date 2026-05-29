<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:cancel-unpaid')]
#[Description('Delete pending bookings that have been unpaid for more than 15 minutes')]
class CancelUnpaidBookings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = Booking::where('status', 'pending')
            ->where('payment_status', 'unpaid')
            ->where('created_at', '<=', now()->subMinutes(15))
            ->delete();

        $this->info("Deleted {$count} unpaid bookings.");
    }
}
