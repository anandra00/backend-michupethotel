<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Booking;
use App\Models\Room;
use App\Models\Sitter;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

echo "--- QA TEST SCRIPT START ---\n";

// 1. Get user and admin
$user = User::where('role', 'user')->first();
$admin = User::where('role', 'admin')->first();
if (! $user || ! $admin) {
    exit("Need user and admin\n");
}
Auth::login($user);

// 2. Create Room Booking
$room = Room::first();
echo "Creating Room Booking...\n";
$req = Request::create('/api/bookings', 'POST', [
    'booking_type' => 'board',
    'room_id' => $room->id,
    'check_in' => '2026-06-01',
    'check_out' => '2026-06-03',
    'total_cats' => 1,
]);
$res = app()->handle($req);
$bookingData = json_decode($res->getContent(), true);
$bookingId = $bookingData['id'] ?? null;
if (! $bookingId) {
    echo 'FAILED TO CREATE BOOKING: '.$res->getContent()."\n";
} else {
    echo "Room Booking created ID: $bookingId\n";

    // Simulate close popup (Delete)
    $reqDel = Request::create("/api/bookings/$bookingId", 'DELETE');
    $resDel = app()->handle($reqDel);
    echo 'Delete booking result: '.$resDel->getContent()."\n";
    if (Booking::find($bookingId)) {
        echo "ERROR: Booking not deleted!\n";
    } else {
        echo "SUCCESS: Booking deleted correctly.\n";
    }
}

// 3. Create Sitter Booking & Pay & Reassign
$sitter1 = Sitter::first();
$sitter2 = Sitter::where('id', '!=', $sitter1->id)->first();
echo "\nCreating Sitter Booking...\n";
$req2 = Request::create('/api/bookings', 'POST', [
    'booking_type' => 'sitter',
    'sitter_id' => $sitter1->id,
    'sitter_package' => '1x',
    'check_in' => '2026-06-05',
    'check_out' => '2026-06-07',
    'total_cats' => 1,
]);
$res2 = app()->handle($req2);
$sitterBookingData = json_decode($res2->getContent(), true);
$sBkgId = $sitterBookingData['id'] ?? null;

if ($sBkgId) {
    echo "Sitter Booking created ID: $sBkgId. Status: ".Booking::find($sBkgId)->status.', Payment: '.Booking::find($sBkgId)->payment_status."\n";

    // Simulate payment success
    $reqPay = Request::create("/api/bookings/$sBkgId/pay-success", 'PUT');
    app()->handle($reqPay);
    echo 'After Payment. Payment Status: '.Booking::find($sBkgId)->payment_status."\n";

    // Simulate Reassign (Admin)
    Auth::login($admin);
    $reqReassign = Request::create("/api/bookings/$sBkgId", 'PUT', [
        'sitter_id' => $sitter2->id,
        'notes' => '[Admin]: Sitter changed',
    ]);
    $resReassign = app()->handle($reqReassign);
    $updatedBooking = Booking::find($sBkgId);
    echo "After Reassign. Sitter ID: {$updatedBooking->sitter_id}. Notes: {$updatedBooking->notes}\n";
    if ($updatedBooking->sitter_id == $sitter2->id) {
        echo "SUCCESS: Reassign worked!\n";
    } else {
        echo "ERROR: Reassign failed!\n";
    }
}

echo "--- QA TEST SCRIPT END ---\n";
