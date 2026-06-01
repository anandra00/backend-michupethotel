<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * List all coupons (admin).
     */
    public function index()
    {
        $coupons = Coupon::orderBy('created_at', 'desc')->get();
        return response()->json($coupons);
    }

    /**
     * Create a new coupon (admin).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'type' => 'required|in:percentage,flat',
            'value' => 'required|numeric|min:0',
            'min_order' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:255',
        ]);

        // Force uppercase code
        $validated['code'] = strtoupper($validated['code']);
        $validated['usage_limit'] = $validated['usage_limit'] ?? 0;
        $validated['used_count'] = 0;

        $coupon = Coupon::create($validated);

        return response()->json($coupon, 201);
    }

    /**
     * Update a coupon (admin).
     */
    public function update(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:coupons,code,' . $coupon->id,
            'type' => 'sometimes|in:percentage,flat',
            'value' => 'sometimes|numeric|min:0',
            'min_order' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'valid_from' => 'sometimes|date',
            'valid_until' => 'sometimes|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:255',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $coupon->update($validated);

        return response()->json($coupon);
    }

    /**
     * Delete a coupon (admin).
     */
    public function destroy(Coupon $coupon)
    {
        $coupon->delete();
        return response()->json(['message' => 'Kupon berhasil dihapus']);
    }

    /**
     * Get active coupons for users.
     */
    public function activeCoupons()
    {
        $coupons = Coupon::where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->where(function ($query) {
                $query->where('usage_limit', 0)
                      ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->orderBy('valid_until', 'asc')
            ->get();

        return response()->json($coupons);
    }

    /**
     * Validate a coupon code and preview discount (user-facing).
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (! $coupon) {
            return response()->json(['message' => 'Kode kupon tidak ditemukan'], 404);
        }

        if (! $coupon->isUsable()) {
            $reason = 'Kupon tidak valid';
            if (! $coupon->is_active) {
                $reason = 'Kupon sudah tidak aktif';
            } elseif (now()->lt($coupon->valid_from)) {
                $reason = 'Kupon belum berlaku';
            } elseif (now()->gt($coupon->valid_until)) {
                $reason = 'Kupon sudah kedaluwarsa';
            } elseif ($coupon->usage_limit > 0 && $coupon->used_count >= $coupon->usage_limit) {
                $reason = 'Kuota kupon sudah habis';
            }
            return response()->json(['message' => $reason], 422);
        }

        $discount = $coupon->calculateDiscount($request->subtotal);

        if ($discount <= 0) {
            return response()->json([
                'message' => 'Minimum order belum tercapai (min. Rp ' . number_format($coupon->min_order, 0, ',', '.') . ')',
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'discount' => $discount,
            'description' => $coupon->description,
            'final_total' => max(0, $request->subtotal - $discount),
        ]);
    }
}
