<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'hotel_name', 'value' => 'Michu MeowStay', 'type' => 'string'],
            ['key' => 'email', 'value' => 'admin@michumeowstay.com', 'type' => 'string'],
            ['key' => 'phone', 'value' => '081234567890', 'type' => 'string'],
            ['key' => 'address', 'value' => 'Jl. Kucing Manis No. 42, Jakarta Selatan', 'type' => 'string'],
            ['key' => 'open_time', 'value' => '08:00', 'type' => 'string'],
            ['key' => 'close_time', 'value' => '20:00', 'type' => 'string'],
            ['key' => 'max_capacity', 'value' => '30', 'type' => 'number'],
            ['key' => 'admin_fee', 'value' => '5000', 'type' => 'number'],
            ['key' => 'notify_booking', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'notify_checkout', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'notify_new_user', 'value' => '0', 'type' => 'boolean'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
