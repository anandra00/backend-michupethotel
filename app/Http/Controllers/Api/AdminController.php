<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Cat;
use App\Models\Room;
use App\Models\User;
use App\Services\DashboardReportingService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function stats()
    {
        $totalRooms = Room::count();
        $activeBookings = Booking::whereIn('status', ['pending', 'approved', 'checked_in'])->count();
        $totalUsers = User::where('role', 'user')->count();
        $totalRevenue = Booking::whereIn('status', ['pending', 'approved', 'checked_in'])->where('payment_status', 'paid')->sum('total_price');
        $recentBookings = Booking::with(['user', 'room'])->orderBy('created_at', 'desc')->take(5)->get();

        return response()->json([
            'rooms' => $totalRooms,
            'bookings' => $activeBookings,
            'users' => $totalUsers,
            'revenue' => $totalRevenue,
            'recent' => $recentBookings,
        ]);
    }

    public function reports(DashboardReportingService $reportingService)
    {
        return response()->json($reportingService->getAdminReports());
    }

    public function cats(Request $request)
    {
        $query = Cat::query();
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
            return response()->json($query->get());
        }

        // Paginate by default for admin list to prevent memory leak
        return response()->json($query->paginate(20));
    }

    public function exportExcel()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BookingsExport, 'laporan_booking_michu.xlsx');
    }

    public function exportPdf()
    {
        $bookings = Booking::with(['user'])->orderBy('created_at', 'desc')->get();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports', compact('bookings'));
        return $pdf->download('laporan_booking_michu.pdf');
    }
}
