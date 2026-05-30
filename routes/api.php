<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CatController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\SitterController;
use App\Http\Controllers\Api\SitterPackageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VisitServiceController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\SettingController;
use App\Http\Middleware\IsAdmin;
use Illuminate\Support\Facades\Route;

// Auth endpoints — strict rate limit (5 attempts/min) to prevent brute force
Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['throttle:60,1'])->group(function () {
    // Public Settings
    Route::get('/settings', [SettingController::class, 'index']);

    // Webhook Route (Midtrans callback — no auth needed)
    Route::post('/midtrans/callback', [PaymentCallbackController::class, 'handleCallback']);

    // Public routes
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::get('/rooms/{room}', [RoomController::class, 'show']);
    Route::get('/visit-services', [VisitServiceController::class, 'index']);
    Route::get('/sitter-packages', [SitterPackageController::class, 'index']);

    // Protected routes (User & Admin)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::put('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::put('/notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/test', [NotificationController::class, 'testNotification']);

        // Profile update
        Route::get('/user/stats', [UserController::class, 'stats']);
        Route::put('/profile', [UserController::class, 'updateProfile']);

        // Sitters list (for user to pick a sitter)
        Route::get('/sitters', [SitterController::class, 'index']);

        // Bookings
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::put('/bookings/{booking}', [BookingController::class, 'update']);
        Route::put('/bookings/{booking}/pay-success', [BookingController::class, 'paySuccess']);
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancelBooking']);
        Route::post('/bookings/{booking}/sitter-checkin', [BookingController::class, 'sitterCheckin']);
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);

        // Coupons validation (user-facing) — Rate limited to 10 attempts/min
        Route::middleware('throttle:10,1')->post('/coupons/validate', [CouponController::class, 'validateCoupon']);

        // Chat messages — Rate limited to 30 messages/min
        Route::middleware('throttle:30,1')->group(function () {
            Route::get('/bookings/{booking}/messages', [MessageController::class, 'index']);
            Route::post('/bookings/{booking}/messages', [MessageController::class, 'store']);
        });

        // Sitter Review
        Route::post('/bookings/{booking}/review', [BookingController::class, 'reviewSitter']);

        // User Cats Management
        Route::apiResource('cats', CatController::class);

        // Daily Reports
        Route::get('/cats/{catId}/daily-reports', [DailyReportController::class, 'index']);

        // Admin Routes
        Route::middleware([IsAdmin::class])->group(function () {
            Route::get('/admin/stats', [AdminController::class, 'stats']);
            Route::get('/admin/reports', [AdminController::class, 'reports']);
            Route::get('/admin/cats', [AdminController::class, 'cats']); // NEW ROUTE
            Route::apiResource('admin/rooms', RoomController::class)->except(['index', 'show']);
            Route::apiResource('admin/coupons', CouponController::class);
            Route::get('/admin/sitters/{id}/schedule', [SitterController::class, 'schedule']);
            Route::apiResource('admin/sitters', SitterController::class);

            // Admin Settings
            Route::put('/admin/settings', [SettingController::class, 'update']);

            // Admin Daily Reports Management
            Route::post('/admin/daily-reports', [DailyReportController::class, 'store']);
            Route::delete('/admin/daily-reports/{id}', [DailyReportController::class, 'destroy']);
        });
    });
});
