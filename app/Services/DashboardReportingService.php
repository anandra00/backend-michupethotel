<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Models\Sitter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardReportingService
{
    public function getAdminReports()
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

            // Fetch all bookings created since 6 months ago to calculate summary and charts in memory
            $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();
            $allBookingsIn6Months = Booking::where('created_at', '>=', $sixMonthsAgo)->get();

            // 1. Revenue (only count paid bookings)
            $revenueThisMonth = $allBookingsIn6Months->filter(function ($b) use ($thisMonth, $thisYear, $validStatuses) {
                $created = Carbon::parse($b->created_at);
                return $created->month === $thisMonth && $created->year === $thisYear && in_array($b->status, $validStatuses) && $b->payment_status === 'paid';
            })->sum('total_price');

            $revenueLastMonth = $allBookingsIn6Months->filter(function ($b) use ($lmMonth, $lmYear, $validStatuses) {
                $created = Carbon::parse($b->created_at);
                return $created->month === $lmMonth && $created->year === $lmYear && in_array($b->status, $validStatuses) && $b->payment_status === 'paid';
            })->sum('total_price');

            // Split Revenue
            $boardRevenue = $allBookingsIn6Months->filter(function ($b) use ($thisMonth, $thisYear, $validStatuses) {
                $created = Carbon::parse($b->created_at);
                return $created->month === $thisMonth && $created->year === $thisYear && in_array($b->status, $validStatuses) && $b->payment_status === 'paid' && $b->booking_type === 'board';
            })->sum('total_price');

            $sitterRevenue = $allBookingsIn6Months->filter(function ($b) use ($thisMonth, $thisYear, $validStatuses) {
                $created = Carbon::parse($b->created_at);
                return $created->month === $thisMonth && $created->year === $thisYear && in_array($b->status, $validStatuses) && $b->payment_status === 'paid' && $b->booking_type === 'sitter';
            })->sum('total_price');

            // 2. Bookings
            $bookingsThisMonth = $allBookingsIn6Months->filter(function ($b) use ($thisMonth, $thisYear, $validStatuses) {
                $created = Carbon::parse($b->created_at);
                return $created->month === $thisMonth && $created->year === $thisYear && in_array($b->status, $validStatuses);
            })->count();

            $bookingsLastMonth = $allBookingsIn6Months->filter(function ($b) use ($lmMonth, $lmYear, $validStatuses) {
                $created = Carbon::parse($b->created_at);
                return $created->month === $lmMonth && $created->year === $lmYear && in_array($b->status, $validStatuses);
            })->count();

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
                $m = $monthDate->month;
                $y = $monthDate->year;
                $val = $allBookingsIn6Months->filter(function ($b) use ($m, $y, $validStatuses) {
                    $created = Carbon::parse($b->created_at);
                    return $created->month === $m && $created->year === $y && in_array($b->status, $validStatuses);
                })->count();
                $chartData[] = [
                    'month' => $monthDate->format('M'),
                    'val' => $val,
                ];
            }

            // 7. 30-Day Daily Room Occupancy Chart Data (1 query to fetch bookings in range, filter in memory)
            $thirtyDaysAgo = Carbon::now()->subDays(29)->toDateString();
            $today = Carbon::now()->toDateString();

            $bookingsInPeriod = Booking::where('booking_type', 'board')
                ->whereIn('status', ['approved', 'checked_in', 'checked_out'])
                ->where('check_in', '<=', $today)
                ->where('check_out', '>=', $thirtyDaysAgo)
                ->get(['check_in', 'check_out']);

            $occupancyChartData = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dateStr = $date->toDateString();

                $activeBoardCount = $bookingsInPeriod->filter(function ($booking) use ($dateStr) {
                    return $booking->check_in <= $dateStr && $booking->check_out >= $dateStr;
                })->count();

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
                $m = $monthDate->month;
                $y = $monthDate->year;

                $periodBookings = $allBookingsIn6Months->filter(function ($b) use ($m, $y, $validStatuses) {
                    $created = Carbon::parse($b->created_at);
                    return $created->month === $m && $created->year === $y && in_array($b->status, $validStatuses) && $b->payment_status === 'paid';
                });

                $boardRev = $periodBookings->where('booking_type', 'board')->sum('total_price');
                $sitterRev = $periodBookings->where('booking_type', 'sitter')->sum('total_price');

                $revenueChartData[] = [
                    'month' => $monthDate->format('M'),
                    'board' => (float)$boardRev,
                    'sitter' => (float)$sitterRev,
                    'total' => (float)($boardRev + $sitterRev),
                ];
            }

            // 9. Sitter Performance
            $sitterPerformance = Sitter::withCount(['bookings as completed_visits' => function ($query) {
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
}
