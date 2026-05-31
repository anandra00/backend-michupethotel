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
        \Log::info('Midtrans Webhook Received', $request->all());

        $serverKey = config('services.midtrans.server_key');
        $hashed = hash('sha512', $request->order_id.$request->status_code.$request->gross_amount.$serverKey);

        if ($hashed === $request->signature_key) {
            $booking = Booking::where('midtrans_order_id', $request->order_id)->first();

            if (! $booking) {
                // Fallback for legacy format if needed, but preferred to be strict
                $orderIdParts = explode('-', $request->order_id);
                if (count($orderIdParts) >= 2) {
                    $booking = Booking::find($orderIdParts[1]);
                }
            }

            if (! $booking) {
                \Log::warning("Midtrans Webhook: Booking not found for order ID: {$request->order_id}");
                return response()->json(['message' => 'Booking not found'], 404);
            }

            if ($request->transaction_status == 'settlement') {
                // settlement = final confirmation, always safe to mark as paid
                if ($booking->payment_status !== 'paid') {
                    $booking->update([
                        'payment_status' => 'paid',
                        'status' => 'approved', // auto-confirm booking upon payment
                    ]);
                    app(WhatsappService::class)->sendBookingConfirmation($booking);
                }
            } elseif ($request->transaction_status == 'capture') {
                // capture = credit card — MUST verify fraud_status before marking paid
                if ($request->fraud_status == 'accept') {
                    if ($booking->payment_status !== 'paid') {
                        $booking->update([
                            'payment_status' => 'paid',
                            'status' => 'approved', // auto-confirm booking upon payment
                        ]);
                        app(WhatsappService::class)->sendBookingConfirmation($booking);
                    }
                } else {
                    \Log::warning("Midtrans fraud detected for booking #{$booking->id}: fraud_status={$request->fraud_status}");
                }
            } elseif ($request->transaction_status == 'cancel' || $request->transaction_status == 'deny' || $request->transaction_status == 'expire') {
                $booking->update(['payment_status' => 'failed', 'status' => 'cancelled']);
            } elseif ($request->transaction_status == 'pending') {
                $booking->update(['payment_status' => 'unpaid']);
            } elseif ($request->transaction_status == 'refund' || $request->transaction_status == 'partial_refund') {
                // Midtrans refund webhook — update booking refund status
                $booking->update([
                    'refund_status' => 'processed',
                ]);
                \Log::info("Midtrans refund confirmed for booking #{$booking->id}");
            }

            return response()->json(['message' => 'Callback handled successfully']);
        }

        \Log::warning('Midtrans Webhook: Invalid signature', [
            'received' => $request->signature_key,
            'calculated' => $hashed,
            'payload' => $request->all()
        ]);
        return response()->json(['message' => 'Invalid signature'], 403);
    }
}
