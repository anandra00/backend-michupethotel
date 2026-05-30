<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_order', 'max_discount',
        'usage_limit', 'used_count', 'valid_from', 'valid_until',
        'is_active', 'description',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /**
     * Scope: only active, valid, and within-quota coupons.
     */
    public function scopeUsable($query)
    {
        return $query->where('is_active', true)
            ->where('valid_from', '<=', now()->toDateString())
            ->where('valid_until', '>=', now()->toDateString())
            ->where(function ($q) {
                $q->where('usage_limit', 0)
                  ->orWhereColumn('used_count', '<', 'usage_limit');
            });
    }

    /**
     * Check if this coupon can still be used.
     */
    public function isUsable(): bool
    {
        if (! $this->is_active) return false;
        if (now()->lt($this->valid_from) || now()->gt($this->valid_until)) return false;
        if ($this->usage_limit > 0 && $this->used_count >= $this->usage_limit) return false;
        return true;
    }

    /**
     * Calculate the discount amount for a given subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->min_order && $subtotal < $this->min_order) {
            return 0;
        }

        if ($this->type === 'percentage') {
            $discount = $subtotal * ($this->value / 100);
            // Cap at max_discount if set
            if ($this->max_discount && $discount > $this->max_discount) {
                $discount = $this->max_discount;
            }
            return round($discount);
        }

        // Flat discount — cannot exceed subtotal
        return min($this->value, $subtotal);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
