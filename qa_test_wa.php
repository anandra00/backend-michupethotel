<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

$token = env('FONNTE_TOKEN');

// === SEND TO CLIENT 6287817881767 ===
echo "--- Sending to CLIENT (6287817881767) ---\n";
$clientMsg = "✅ *KONFIRMASI PESANAN MICHU MEOWSTAY* ✅\n\n";
$clientMsg .= "Halo *Andi Prasetyo*! 🐾\n";
$clientMsg .= "Pesanan kamu telah kami terima. Berikut rinciannya:\n\n";
$clientMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
$clientMsg .= "📋 *DETAIL PESANAN*\n";
$clientMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
$clientMsg .= "• Tipe: *Cat Sitter*\n";
$clientMsg .= "• ID Booking: *BKG-42*\n";
$clientMsg .= "• Paket: *1x Visit/hari*\n";
$clientMsg .= "• Sitter: *Budi Santoso* (Jakarta Selatan)\n";
$clientMsg .= "• Durasi: *3 hari*\n";
$clientMsg .= "• Alamat: Jl. Mawar No. 12, Kel. Cipete, Jakarta Selatan\n";
$clientMsg .= "\n📅 *JADWAL*\n";
$clientMsg .= "• Check-in: 25 May 2026\n";
$clientMsg .= "• Check-out: 27 May 2026\n";
$clientMsg .= "• Jumlah Kucing: 1 ekor\n";
$clientMsg .= "\n💰 *TOTAL BIAYA*\n";
$clientMsg .= "*Rp 230.000*\n";
$clientMsg .= "Status: _Menunggu Pembayaran_\n\n";
$clientMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
$clientMsg .= "Segera lakukan pembayaran agar pesanan kamu bisa diproses ya! 🙏\n\n";
$clientMsg .= "Terima kasih! 🐱💕\n";
$clientMsg .= '_Michu MeowStay — Rumah Kedua Anabulmu_';

$r1 = Http::withoutVerifying()->withHeaders(['Authorization' => $token])
    ->post('https://api.fonnte.com/send', ['target' => '6287817881767', 'message' => $clientMsg]);
echo 'Status: '.$r1->status()."\n";
echo 'Response: '.$r1->body()."\n\n";

// === SEND TO ADMIN 08585929383 ===
echo "--- Sending to ADMIN (08585929383) ---\n";
$adminMsg = "🔔 *PESANAN BARU MASUK!* 🔔\n\n";
$adminMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
$adminMsg .= "📋 *DETAIL PESANAN*\n";
$adminMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
$adminMsg .= "• ID: *BKG-42*\n";
$adminMsg .= "• Tipe: *Cat Sitter*\n";
$adminMsg .= "• Pelanggan: *Andi Prasetyo*\n";
$adminMsg .= "• HP: 6287817881767\n";
$adminMsg .= "• Email: andi@email.com\n";
$adminMsg .= "• Paket: *1x Visit/hari*\n";
$adminMsg .= "• Sitter: *Budi Santoso*\n";
$adminMsg .= "• Alamat: Jl. Mawar No. 12, Jakarta Selatan\n";
$adminMsg .= "• Check-in: 25 May 2026\n";
$adminMsg .= "• Check-out: 27 May 2026\n";
$adminMsg .= "• Jumlah Kucing: 1 ekor\n";
$adminMsg .= "• Total: *Rp 230.000*\n";
$adminMsg .= "\nSegera cek dan konfirmasi di dashboard admin! 🚀";

$r2 = Http::withoutVerifying()->withHeaders(['Authorization' => $token])
    ->post('https://api.fonnte.com/send', ['target' => '08585929383', 'message' => $adminMsg]);
echo 'Status: '.$r2->status()."\n";
echo 'Response: '.$r2->body()."\n";
