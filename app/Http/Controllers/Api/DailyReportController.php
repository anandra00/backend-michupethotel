<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DailyReport;
use App\Notifications\AppNotification;
use App\Services\WhatsappService;
use Illuminate\Http\Request;

class DailyReportController extends Controller
{
    // For users to view their cat's reports
    public function index($catId)
    {
        $cat = \App\Models\Cat::findOrFail($catId);
        if (\Illuminate\Support\Facades\Auth::user()->role !== 'admin' && $cat->user_id !== \Illuminate\Support\Facades\Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Standard production practice: Paginate daily reports to prevent memory issues
        $reports = DailyReport::where('cat_id', $catId)
            ->with(['booking.sitter', 'booking' => function ($query) {
                $query->select('id', 'sitter_id', 'status');
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($reports);
    }

    // For admin/sitter to create a report
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'cat_id' => 'required|exists:cats,id',
            'title' => 'required|string',
            'description' => 'required|string',
            'badge' => 'nullable|string',
            'badge_bg' => 'nullable|string',
            'icon_type' => 'nullable|string',
            'time' => 'required|string',
            'photo' => 'nullable|image|max:5120',
        ]);

        // Security & Logic Guard: Validate that the cat belongs to the booking's owner
        $bookingObj = Booking::findOrFail($validated['booking_id']);
        $catObj = \App\Models\Cat::findOrFail($validated['cat_id']);
        if ($catObj->user_id !== $bookingObj->user_id) {
            return response()->json([
                'message' => 'Kucing yang dipilih tidak terasosiasi dengan pemilik pesanan ini.',
            ], 422);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('daily_reports', 'public');
        }

        $report = DailyReport::create([
            'booking_id' => $validated['booking_id'],
            'cat_id' => $validated['cat_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'badge' => $validated['badge'] ?? null,
            'badge_bg' => $validated['badge_bg'] ?? null,
            'icon_type' => $validated['icon_type'] ?? 'CheckCircle2',
            'photo_path' => $photoPath,
            'time' => $validated['time'],
        ]);

        $booking = Booking::with('user')->find($validated['booking_id']);
        if ($booking && $booking->user) {
            $booking->user->notify(new AppNotification(
                'Daily Report Baru!',
                'Laporan aktivitas untuk kucing Anda telah diunggah.',
                'info',
                "/dashboard/reports/{$validated['cat_id']}"
            ));
        }

        app(WhatsappService::class)->sendDailyReportNotification($report);

        return response()->json($report, 201);
    }

    public function destroy($id)
    {
        $report = DailyReport::findOrFail($id);
        $report->delete();

        return response()->json(['message' => 'Report deleted successfully']);
    }
}
