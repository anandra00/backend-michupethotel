<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webpush:generate-keys')]
#[Description('Generate secure VAPID cryptographic keys for Web Push Notifications and append to .env')]
class GenerateVapidKeys extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🔑 Generating VAPID cryptographic keys...");

        try {
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
            $publicKey = $keys['publicKey'];
            $privateKey = $keys['privateKey'];

            $this->line("");
            $this->comment("Public Key (VAPID_PUBLIC_KEY):");
            $this->info($publicKey);
            $this->line("");
            $this->comment("Private Key (VAPID_PRIVATE_KEY):");
            $this->info($privateKey);
            $this->line("");

            // Save to .env
            $envPath = base_path('.env');
            if (file_exists($envPath)) {
                $envContent = file_get_contents($envPath);

                // Update VAPID_PUBLIC_KEY
                if (str_contains($envContent, 'VAPID_PUBLIC_KEY=')) {
                    $envContent = preg_replace('/VAPID_PUBLIC_KEY=.*/', 'VAPID_PUBLIC_KEY=' . $publicKey, $envContent);
                } else {
                    $envContent .= "\nVAPID_PUBLIC_KEY=" . $publicKey;
                }

                // Update VAPID_PRIVATE_KEY
                if (str_contains($envContent, 'VAPID_PRIVATE_KEY=')) {
                    $envContent = preg_replace('/VAPID_PRIVATE_KEY=.*/', 'VAPID_PRIVATE_KEY=' . $privateKey, $envContent);
                } else {
                    $envContent .= "\nVAPID_PRIVATE_KEY=" . $privateKey;
                }

                file_put_contents($envPath, $envContent);
                $this->info("✅ VAPID keys successfully saved to your .env file!");
            } else {
                $this->warning("⚠️  .env file not found. Please add the VAPID keys manually.");
            }

        } catch (\Exception $e) {
            $this->error("❌ Gagal membuat kunci VAPID: " . $e->getMessage());
            $this->line("");
            $this->warn("💡 Penyebab Umum di Windows (Laragon/XAMPP/PHP):");
            $this->line(" 1. Ekstensi OpenSSL di php.ini belum diaktifkan (hapus ';' pada ';extension=openssl').");
            $this->line(" 2. Path berkas 'openssl.cnf' tidak terbaca atau tidak dikonfigurasi.");
            $this->line("");
            $this->info("🔧 Solusi Alternatif Menggunakan Node.js:");
            $this->line(" Anda dapat memasang paket web-push secara global:");
            $this->comment("   npm install -g web-push");
            $this->line(" Lalu jalankan perintah berikut untuk menggenerasikan kunci:");
            $this->comment("   web-push generate-vapid-keys");
            $this->line(" Salin hasilnya dan masukkan ke berkas .env backend:");
            $this->comment("   VAPID_PUBLIC_KEY=... \n   VAPID_PRIVATE_KEY=...");
        }
    }
}
