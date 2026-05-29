<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|required|email|unique:users,email,'.$user->id,
            'address' => 'sometimes|nullable|string|max:500',
            'password' => ['sometimes', 'nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if (isset($validated['password']) && $validated['password']) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }

    public function stats(Request $request)
    {
        $userId = Auth::id();

        $activeBookingsCount = Booking::where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved', 'checked_in'])
            ->count();

        $totalCost = Booking::where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved', 'checked_in'])
            ->sum('total_price');

        $hasCheckedIn = Booking::where('user_id', $userId)
            ->where('status', 'checked_in')
            ->exists();

        $hasCheckedInBoard = Booking::where('user_id', $userId)
            ->where('status', 'checked_in')
            ->where('booking_type', 'board')
            ->exists();

        return response()->json([
            'active_bookings_count' => $activeBookingsCount,
            'total_cost' => $totalCost,
            'has_checked_in' => $hasCheckedIn,
            'has_checked_in_board' => $hasCheckedInBoard,
        ]);
    }
}
