<?php

namespace App\Services;

use App\Jobs\SendWhatsappMessage;
use App\Models\Booking;
use App\Models\DailyReport;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    /**
     * Send a WhatsApp message using Fonnte API or fallback to logs.
     */
    public function sendMessage(string $phone, string $message): bool
    {
        $token = env('FONNTE_TOKEN');

        if (empty($token)) {
            // Fallback to logs if no Fonnte token is set
            Log::info('=== MOCKED WHATSAPP MESSAGE ===');
            Log::info("To: {$phone}");
            Log::info("Message: \n{$message}");
            Log::info('===============================');

            return true;
        }

        // Dispatch to queue instead of sending synchronously
        SendWhatsappMessage::dispatch($phone, $message);

        return true;
    }

    public function sendBookingConfirmation(Booking $booking): void
    {
        $booking->load(['user', 'room', 'sitter']);
        $user = $booking->user;

        $typeLabel = $booking->booking_type === 'sitter' ? 'Cat Sitter' : 'Cat Hotel (Inap)';
        $checkInFormatted = date('d M Y', strtotime($booking->check_in));
        $checkOutFormatted = date('d M Y', strtotime($booking->check_out));
        $totalFormatted = 'Rp '.number_format($booking->total_price, 0, ',', '.');

        // --- MESSAGE TO CUSTOMER ---
        if ($user && $user->phone) {
            $msg = "✅ *PEMBAYARAN BERHASIL* ✅\n\n";
            $msg .= "Halo *{$user->name}*! 🐾\n";
            $msg .= "Pembayaran untuk pesanan kamu telah berhasil dikonfirmasi. Berikut rinciannya:\n\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "📋 *DETAIL PESANAN*\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "• Tipe: *{$typeLabel}*\n";
            $msg .= "• ID Booking: *BKG-{$booking->id}*\n";

            if ($booking->booking_type === 'board' && $booking->room) {
                $msg .= "• Kamar: *{$booking->room->name}* ({$booking->room->type})\n";
                $nights = max(1, (new \DateTime($booking->check_in))->diff(new \DateTime($booking->check_out))->days);
                $msg .= "• Durasi: *{$nights} malam*\n";
                $msg .= '• Harga/Malam: Rp '.number_format($booking->room->price_per_night, 0, ',', '.')."\n";
            }

            if ($booking->booking_type === 'sitter') {
                $days = max(1, (new \DateTime($booking->check_in))->diff(new \DateTime($booking->check_out))->days);
                $packageLabel = $booking->sitter_package === '2x' ? '2x Visit/hari' : '1x Visit/hari';
                $msg .= "• Paket: *{$packageLabel}*\n";
                if ($booking->sitter) {
                    $msg .= "• Sitter: *{$booking->sitter->name}* ({$booking->sitter->area})\n";
                }
                $msg .= "• Durasi: *{$days} hari*\n";
                if ($user->address) {
                    $msg .= "• Alamat: {$user->address}\n";
                }
            }

            $msg .= "\n📅 *JADWAL*\n";
            $msg .= "• Check-in: {$checkInFormatted}\n";
            $msg .= "• Check-out: {$checkOutFormatted}\n";
            $msg .= "• Jumlah Kucing: {$booking->total_cats} ekor\n";

            if ($booking->notes) {
                $msg .= "\n📝 *CATATAN*\n";
                $msg .= "{$booking->notes}\n";
            }

            $msg .= "\n💰 *TOTAL BIAYA*\n";
            $msg .= "*{$totalFormatted}*\n";
            $msg .= "Status: _LUNAS_\n\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "Terima kasih! Kami akan segera mempersiapkan semuanya dengan baik. 🐱💕\n";
            $msg .= '_Michu MeowStay — Rumah Kedua Anabulmu_';

            $this->sendMessage($user->phone, $msg);
        }

        // --- MESSAGE TO ADMIN ---
        $adminPhone = env('ADMIN_PHONE', '085885929383');
        $adminMsg = "💰 *PEMBAYARAN DITERIMA!* 💰\n\n";
        $adminMsg .= "Pesanan baru telah LUNAS dan siap diproses.\n\n";
        $adminMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
        $adminMsg .= "📋 *DETAIL PESANAN*\n";
        $adminMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
        $adminMsg .= "• ID: *BKG-{$booking->id}*\n";
        $adminMsg .= "• Tipe: *{$typeLabel}*\n";

        if ($user) {
            $adminMsg .= "• Pelanggan: *{$user->name}*\n";
            $adminMsg .= "• HP: {$user->phone}\n";
            $adminMsg .= "• Email: {$user->email}\n";
        }

        if ($booking->booking_type === 'board' && $booking->room) {
            $adminMsg .= "• Kamar: *{$booking->room->name}* ({$booking->room->type})\n";
        }
        if ($booking->booking_type === 'sitter') {
            $packageLabel = $booking->sitter_package === '2x' ? '2x Visit/hari' : '1x Visit/hari';
            $adminMsg .= "• Paket: *{$packageLabel}*\n";
            if ($booking->sitter) {
                $adminMsg .= "• Sitter: *{$booking->sitter->name}*\n";
            }
            if ($user && $user->address) {
                $adminMsg .= "• Alamat: {$user->address}\n";
            }
        }

        $adminMsg .= "• Check-in: {$checkInFormatted}\n";
        $adminMsg .= "• Check-out: {$checkOutFormatted}\n";
        $adminMsg .= "• Jumlah Kucing: {$booking->total_cats} ekor\n";
        $adminMsg .= "• Total: *{$totalFormatted}*\n";
        $adminMsg .= "• Status: *LUNAS*\n";

        if ($booking->notes) {
            $adminMsg .= "\n📝 Catatan: {$booking->notes}\n";
        }

        $adminMsg .= "\nSegera cek dan proses pesanan ini di dashboard admin! 🚀";

        $this->sendMessage($adminPhone, $adminMsg);
    }

    public function sendDailyReportNotification(DailyReport $report): void
    {
        $report->load(['booking.user', 'booking.sitter', 'cat']);
        $user = $report->booking->user;
        $cat = $report->cat;
        $sitter = $report->booking->sitter;

        if ($user && $user->phone) {
            $sitterName = $sitter ? $sitter->name : 'Sitter kami';

            $msg = "📸 *LAPORAN HARIAN TERBARU* 📸\n\n";
            $msg .= "Halo *{$user->name}*! 🐾\n";
            $msg .= "{$sitterName} telah menyelesaikan kunjungan dan membagikan laporan aktivitas terbaru untuk *{$cat->name}*.\n\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "📝 *Ringkasan Laporan*\n";
            $msg .= "• Waktu: {$report->time}\n";
            $msg .= "• Aktivitas: {$report->title}\n";
            $msg .= "• Catatan: {$report->description}\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $msg .= "Cek foto lucu dan detail lengkapnya di dashboard aplikasi Michu MeowStay ya!\n\n";
            $msg .= '_Michu MeowStay — Rumah Kedua Anabulmu_';

            $this->sendMessage($user->phone, $msg);
        }
    }
}
