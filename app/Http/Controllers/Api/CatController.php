<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CatController extends Controller
{
    /**
     * Display a listing of the user's cats.
     */
    public function index()
    {
        $cats = Cat::where('user_id', Auth::id())->get();

        return response()->json($cats);
    }

    /**
     * Store a newly created cat in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'breed' => 'required|string|max:255',
            'age' => 'required|integer|min:0',
            'weight' => 'required|numeric|min:0.1',
            'gender' => 'required|string|in:male,female,Male,Female',
            'notes' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['gender'] = strtolower($validated['gender']);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('cats', 'public');
        }

        $cat = Cat::create($validated);

        return response()->json($cat, 201);
    }

    /**
     * Update an existing cat.
     */
    public function update(Request $request, string $id)
    {
        $cat = Cat::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'breed' => 'sometimes|required|string|max:255',
            'age' => 'sometimes|required|integer|min:0',
            'weight' => 'sometimes|required|numeric|min:0.1',
            'gender' => 'sometimes|required|string|in:male,female,Male,Female',
            'notes' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
        ]);

        if (isset($validated['gender'])) {
            $validated['gender'] = strtolower($validated['gender']);
        }

        if ($request->hasFile('photo')) {
            // Delete old photo if it exists to prevent storage leaks
            if ($cat->photo) {
                Storage::disk('public')->delete($cat->photo);
            }
            $validated['photo'] = $request->file('photo')->store('cats', 'public');
        }

        $cat->update($validated);

        return response()->json($cat);
    }

    /**
     * Remove the specified cat from storage.
     */
    public function destroy(string $id)
    {
        $cat = Cat::where('user_id', Auth::id())->findOrFail($id);

        // Delete photo if it exists to prevent storage leaks
        if ($cat->photo) {
            Storage::disk('public')->delete($cat->photo);
        }

        $cat->delete();

        return response()->json(['message' => 'Cat deleted successfully']);
    }
}
