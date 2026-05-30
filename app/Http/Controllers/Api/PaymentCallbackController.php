<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\WhatsappService;
use Illuminate\Http\Request;

class PaymentCallbackController extends Controller
{
    public function handleCallback(Request $request)
    {
        $serverKey = config('services.midtrans.server_key');
        $hashed = hash('sha512', $request->order_id.$request->status_code.$request->gross_amount.$serverKey);

        if ($hashed === $request->signature_key) {
            // Check order_id format "BKG-{id}-{time}"
            $orderIdParts = explode('-', $request->order_id);
            if (count($orderIdParts) < 2) {
                return response()->json(['message' => 'Invalid order ID format'], 400);
            }

            $bookingId = $orderIdParts[1];
            $booking = Booking::find($bookingId);

            if (! $booking) {
                return response()->json(['message' => 'Booking not found'], 404);
            }

            if ($request->transaction_status == 'settlement') {
                // settlement = final confirmation, always safe to mark as paid
                if ($booking->payment_status !== 'paid') {
                    $booking->update(['payment_status' => 'paid']);
                    app(WhatsappService::class)->sendBookingConfirmation($booking);
                }
            } elseif ($request->transaction_status == 'capture') {
                // capture = credit card — MUST verify fraud_status before marking paid
                if ($request->fraud_status == 'accept') {
                    if ($booking->payment_status !== 'paid') {
                        $booking->update(['payment_status' => 'paid']);
                        app(WhatsappService::class)->sendBookingConfirmation($booking);
                    }
                } else {
                    \Log::warning("Midtrans fraud detected for booking #{$bookingId}: fraud_status={$request->fraud_status}");
                }
            } elseif ($request->transaction_status == 'cancel' || $request->transaction_status == 'deny' || $request->transaction_status == 'expire') {
                $booking->update(['payment_status' => 'failed', 'status' => 'cancelled']);
            } elseif ($request->transaction_status == 'pending') {
                $booking->update(['payment_status' => 'unpaid']);
            }

            return response()->json(['message' => 'Callback handled successfully']);
        }

        return response()->json(['message' => 'Invalid signature'], 403);
    }
}
