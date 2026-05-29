<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RoomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $rooms = Cache::remember('rooms_all_array', 3600, function () {
            return Room::all()->toArray();
        });

        return response()->json($rooms);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'price_per_night' => 'required|numeric',
            'description' => 'nullable|string',
            'facilities' => 'nullable|json',
            'capacity' => 'required|integer',
            'status' => 'required|string|in:available,occupied,cleaning,maintenance',
            'photo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('rooms', 'public');
        }

        $room = Room::create($validated);
        Cache::forget('rooms_all_array');

        return response()->json($room, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $room = Room::findOrFail($id);

        return response()->json($room);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:255',
            'price_per_night' => 'sometimes|required|numeric',
            'description' => 'nullable|string',
            'facilities' => 'nullable|json',
            'capacity' => 'sometimes|required|integer',
            'status' => 'sometimes|required|string|in:available,occupied,cleaning,maintenance',
            'photo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('rooms', 'public');
        }

        $room->update($validated);
        Cache::forget('rooms_all_array');

        return response()->json($room);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $room = Room::findOrFail($id);
        $room->delete();
        Cache::forget('rooms_all_array');

        return response()->json(['message' => 'Room deleted successfully']);
    }
}
