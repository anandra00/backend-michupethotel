<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        // Whitelist: only these setting keys may be modified via API
        $allowedKeys = [
            'admin_fee', 'hotel_name', 'email', 'phone', 'address', 
            'open_time', 'close_time', 'max_capacity', 
            'notify_booking', 'notify_checkout', 'notify_new_user', 
            'gps_checkin_radius'
        ];

        foreach ($validated['settings'] as $key => $value) {
            if (! in_array($key, $allowedKeys)) {
                continue; // silently skip unauthorized keys
            }
            if ($key === 'admin_fee' && ! is_numeric($value)) {
                return response()->json(['message' => 'Biaya admin harus berupa angka.'], 422);
            }
            Setting::where('key', $key)->update(['value' => $value]);
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }
}
