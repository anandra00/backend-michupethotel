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

        foreach ($validated['settings'] as $key => $value) {
            if ($key === 'admin_fee' && !is_numeric($value)) {
                return response()->json(['message' => 'Biaya admin harus berupa angka.'], 422);
            }
            Setting::where('key', $key)->update(['value' => $value]);
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }
}
