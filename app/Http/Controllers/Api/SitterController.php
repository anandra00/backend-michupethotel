<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Sitter;
use App\Services\BookingService;
use Illuminate\Http\Request;

class SitterController extends Controller
{
    public function index(Request $request, BookingService $bookingService)
    {
        $sitters = Sitter::withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->withCount(['bookings as visits' => function ($q) {
                $q->where('status', 'checked_out');
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($sitter) {
                // Alias to match frontend expected field names
                $sitter->avg_rating = $sitter->reviews_avg_rating ? round($sitter->reviews_avg_rating, 1) : 0;
                $sitter->review_count = $sitter->reviews_count;
                unset($sitter->reviews_avg_rating, $sitter->reviews_count);
                return $sitter;
            });

        $checkIn = $request->query('check_in');
        $checkOut = $request->query('check_out');
        $visitTime = $request->query('visit_time', 'none');

        $sitters->map(function ($sitter) use ($bookingService, $checkIn, $checkOut, $visitTime) {
            if ($checkIn && $checkOut) {
                $sitter->is_available = $bookingService->isSitterAvailable($sitter->id, $checkIn, $checkOut, $visitTime);
            } else {
                $sitter->is_available = true;
            }

            return $sitter;
        });

        return response()->json($sitters);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'area' => 'required|string|max:255',
            'speciality' => 'nullable|string|max:255',
            'status' => 'required|string|in:Active,On Leave',
        ]);

        $sitter = Sitter::create($validated);

        return response()->json($sitter, 201);
    }

    public function update(Request $request, string $id)
    {
        $sitter = Sitter::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'area' => 'sometimes|required|string|max:255',
            'speciality' => 'nullable|string|max:255',
            'status' => 'sometimes|required|string|in:Active,On Leave',
        ]);

        $sitter->update($validated);

        return response()->json($sitter);
    }

    public function destroy(string $id)
    {
        $sitter = Sitter::findOrFail($id);

        // Security Guard: Prevent deleting a sitter with active bookings (M-08)
        $activeBookings = Booking::where('sitter_id', $id)
            ->whereIn('status', ['pending', 'approved', 'checked_in'])
            ->count();
        if ($activeBookings > 0) {
            return response()->json(['message' => "Sitter masih memiliki {$activeBookings} booking aktif. Tidak bisa dihapus."], 422);
        }

        $sitter->delete();

        return response()->json(['message' => 'Sitter deleted successfully']);
    }

    public function schedule(string $id)
    {
        $sitter = Sitter::findOrFail($id);

        $bookings = Booking::with('user:id,name')
            ->where('sitter_id', $id)
            ->where('booking_type', 'sitter')
            ->whereIn('status', ['pending', 'approved', 'checked_in'])
            ->get(['id', 'user_id', 'check_in', 'check_out', 'visit_time', 'status', 'total_cats']);

        return response()->json($bookings);
    }
}
