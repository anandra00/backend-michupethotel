<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Standard production practice: Paginate notifications to prevent out-of-memory errors
        $notifications = $user->notifications()->paginate(20);

        return response()->json($notifications);
    }

    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $notification = $user->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();

            return response()->json(['message' => 'Telah dibaca']);
        }

        return response()->json(['message' => 'Notifikasi tidak ditemukan'], 404);
    }

    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user->unreadNotifications->markAsRead();

        return response()->json(['message' => 'Semua telah dibaca']);
    }

    public function testNotification(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user->notify(new \App\Notifications\AppNotification(
            'Uji Coba Notifikasi 🔔',
            'Halo ' . $user->name . ', notifikasi real-time uji coba Anda berhasil dikirim!',
            'success',
            '/dashboard'
        ));

        return response()->json(['message' => 'Notifikasi uji coba berhasil dikirim!']);
    }
}
