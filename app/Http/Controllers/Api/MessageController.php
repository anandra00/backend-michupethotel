<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function index($bookingId)
    {
        $booking = Booking::findOrFail($bookingId);
        $user = Auth::user();

        // Authorization check: User must be admin or the owner of the booking
        if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = Message::where('booking_id', $bookingId)
            ->with('sender:id,name,role,photo')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    public function store(Request $request, $bookingId)
    {
        $booking = Booking::findOrFail($bookingId);
        $user = Auth::user();

        if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('chats', 'public');
        }

        $message = Message::create([
            'booking_id' => $bookingId,
            'sender_id' => $user->id,
            'message' => $validated['message'],
            'photo_path' => $photoPath,
        ]);

        // Eager load sender to return to frontend
        $message->load('sender:id,name,role,photo');

        return response()->json($message, 201);
    }

    /**
     * Stream new messages for a booking via Server-Sent Events (SSE).
     */
    public function stream(Request $request, $bookingId)
    {
        $booking = Booking::findOrFail($bookingId);
        
        $user = Auth::user();
        if (!$user && $request->has('token')) {
            $token = $request->query('token');
            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($tokenModel) {
                $user = $tokenModel->tokenable;
                Auth::login($user);
            }
        }

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->stream(function () use ($bookingId, $request) {
            $lastId = $request->query('last_id', 0);
            $startTime = time();
            
            while (true) {
                if (connection_aborted()) {
                    break;
                }
                
                $messages = Message::where('booking_id', $bookingId)
                    ->where('id', '>', $lastId)
                    ->with('sender:id,name,role,photo')
                    ->get();
                    
                if ($messages->isNotEmpty()) {
                    foreach ($messages as $msg) {
                        echo "data: " . json_encode($msg) . "\n\n";
                        $lastId = $msg->id;
                    }
                    ob_flush();
                    flush();
                }
                
                echo ": ping\n\n";
                ob_flush();
                flush();
                
                sleep(1);
                
                if (time() - $startTime > 30) {
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, private',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
