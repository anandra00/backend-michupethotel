<?php

use App\Http\Controllers\Api\BookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$req = Request::create('/api/bookings', 'POST', [
    'booking_type' => 'sitter',
    'sitter_package' => '1x',
    'sitter_id' => 1,
    'check_in' => '2026-05-23',
    'check_out' => '2026-05-26',
    'total_cats' => 1,
    'notes' => 'test',
]);
$c = new BookingController;
Auth::loginUsingId(1);
try {
    $res = $c->store($req);
    echo $res->getContent();
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n".$e->getTraceAsString();
}
