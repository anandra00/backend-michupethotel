<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Cat;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
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

    public function reports()
    {
        $now = Carbon::now();
        $thisMonth = $now->month;
        $thisYear = $now->year;

        $lastMonth = $now->copy()->subMonth();
        $lmMonth = $lastMonth->month;
        $lmYear = $lastMonth->year;

        // Valid statuses for revenue & count
        $validStatuses = ['pending', 'approved', 'checked_in', 'checked_out'];

        // 1. Revenue (only count paid bookings)
        $revenueThisMonth = Booking::whereMonth('created_at', $thisMonth)->whereYear('created_at', $thisYear)->whereIn('status', $validStatuses)->where('payment_status', 'paid')->sum('total_price');
        $revenueLastMonth = Booking::whereMonth('created_at', $lmMonth)->whereYear('created_at', $lmYear)->whereIn('status', $validStatuses)->where('payment_status', 'paid')->sum('total_price');

        // Split Revenue
        $boardRevenue = Booking::whereMonth('created_at', $thisMonth)->whereYear('created_at', $thisYear)->whereIn('status', $validStatuses)->where('payment_status', 'paid')->where('booking_type', 'board')->sum('total_price');
        $sitterRevenue = Booking::whereMonth('created_at', $thisMonth)->whereYear('created_at', $thisYear)->whereIn('status', $validStatuses)->where('payment_status', 'paid')->where('booking_type', 'sitter')->sum('total_price');

        // 2. Bookings
        $bookingsThisMonth = Booking::whereMonth('created_at', $thisMonth)->whereYear('created_at', $thisYear)->whereIn('status', $validStatuses)->count();
        $bookingsLastMonth = Booking::whereMonth('created_at', $lmMonth)->whereYear('created_at', $lmYear)->whereIn('status', $validStatuses)->count();

        // 3. New Users
        $usersThisMonth = User::where('role', 'user')->whereMonth('created_at', $thisMonth)->whereYear('created_at', $thisYear)->count();
        $usersLastMonth = User::where('role', 'user')->whereMonth('created_at', $lmMonth)->whereYear('created_at', $lmYear)->count();

        // 4. Occupancy Rate (Simulated based on bookings vs total rooms * 30)
        $totalRooms = Room::count();
        $maxCapacity = $totalRooms > 0 ? $totalRooms * 30 : 1;
        $occupancyRate = min(100, round(($bookingsThisMonth / $maxCapacity) * 100));

        // 5. Popular Rooms
        $popularRooms = Room::withCount(['bookings as bookings_count' => function ($query) {
            $query->where('status', 'checked_out')->where('booking_type', 'board')->where('payment_status', 'paid');
        }])
            ->withSum(['bookings as revenue' => function ($query) {
                $query->where('status', 'checked_out')->where('booking_type', 'board')->where('payment_status', 'paid');
            }], 'total_price')
            ->orderByDesc('bookings_count')
            ->take(3)
            ->get()
            ->map(function ($room) {
                $room->revenue = $room->revenue ?? 0;

                return $room;
            });

        // 6. Chart Data (Last 6 Months)
        $chartData = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $val = Booking::whereMonth('created_at', $monthDate->month)
                ->whereYear('created_at', $monthDate->year)
                ->whereIn('status', $validStatuses)
                ->count();
            $chartData[] = [
                'month' => $monthDate->format('M'),
                'val' => $val,
            ];
        }

        return response()->json([
            'summary' => [
                'revenue' => [
                    'current' => $revenueThisMonth,
                    'last' => $revenueLastMonth,
                    'board' => $boardRevenue,
                    'sitter' => $sitterRevenue,
                ],
                'bookings' => [
                    'current' => $bookingsThisMonth,
                    'last' => $bookingsLastMonth,
                ],
                'users' => [
                    'current' => $usersThisMonth,
                    'last' => $usersLastMonth,
                ],
                'occupancy' => $occupancyRate,
            ],
            'popular_rooms' => $popularRooms,
            'chart_data' => $chartData,
        ]);
    }

    public function cats(Request $request)
    {
        $query = Cat::query();
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json($query->get());
    }
}
