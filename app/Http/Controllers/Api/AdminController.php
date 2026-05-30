<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Cat;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;

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
        $cacheKey = 'admin_reports_data';

        return Cache::remember($cacheKey, 300, function () {
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

            // 6. Chart Data (Last 6 Months booking counts)
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

            // 7. 30-Day Daily Room Occupancy Chart Data
            $occupancyChartData = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dateStr = $date->toDateString();

                // Calculate active room bookings on that day
                $activeBoardCount = Booking::where('booking_type', 'board')
                    ->whereIn('status', ['approved', 'checked_in', 'checked_out'])
                    ->where('check_in', '<=', $dateStr)
                    ->where('check_out', '>=', $dateStr)
                    ->count();

                $rate = $totalRooms > 0 ? min(100, round(($activeBoardCount / $totalRooms) * 100)) : 0;

                $occupancyChartData[] = [
                    'date' => $date->format('d M'),
                    'rate' => $rate,
                    'bookings' => $activeBoardCount,
                ];
            }

            // 8. 6-Month Monthly Revenue Split (Board vs Sitter)
            $revenueChartData = [];
            for ($i = 5; $i >= 0; $i--) {
                $monthDate = Carbon::now()->subMonths($i);

                $boardRev = Booking::whereMonth('created_at', $monthDate->month)
                    ->whereYear('created_at', $monthDate->year)
                    ->whereIn('status', $validStatuses)
                    ->where('payment_status', 'paid')
                    ->where('booking_type', 'board')
                    ->sum('total_price');

                $sitterRev = Booking::whereMonth('created_at', $monthDate->month)
                    ->whereYear('created_at', $monthDate->year)
                    ->whereIn('status', $validStatuses)
                    ->where('payment_status', 'paid')
                    ->where('booking_type', 'sitter')
                    ->sum('total_price');

                $revenueChartData[] = [
                    'month' => $monthDate->format('M'),
                    'board' => (float)$boardRev,
                    'sitter' => (float)$sitterRev,
                    'total' => (float)($boardRev + $sitterRev),
                ];
            }

            // 9. Sitter Performance
            $sitterPerformance = \App\Models\Sitter::withCount(['bookings as completed_visits' => function ($query) {
                $query->where('booking_type', 'sitter')
                    ->whereIn('status', ['approved', 'checked_in', 'checked_out'])
                    ->where('payment_status', 'paid');
            }])
            ->withSum(['bookings as total_earnings' => function ($query) {
                $query->where('booking_type', 'sitter')
                    ->whereIn('status', ['approved', 'checked_in', 'checked_out'])
                    ->where('payment_status', 'paid');
            }], 'total_price')
            ->orderByDesc('completed_visits')
            ->get()
            ->map(function ($sitter) {
                return [
                    'id' => $sitter->id,
                    'name' => $sitter->name,
                    'phone' => $sitter->phone,
                    'area' => $sitter->area,
                    'speciality' => $sitter->speciality,
                    'rating' => $sitter->rating,
                    'completed_visits' => $sitter->completed_visits ?? 0,
                    'total_earnings' => (float)($sitter->total_earnings ?? 0),
                ];
            });

            return [
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
                'occupancy_chart_data' => $occupancyChartData,
                'revenue_chart_data' => $revenueChartData,
                'sitter_performance' => $sitterPerformance,
            ];
        });
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
