<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected static function booted()
    {
        static::saved(function ($booking) {
            \Illuminate\Support\Facades\Cache::forget('admin_reports_data');
        });

        static::deleted(function ($booking) {
            \Illuminate\Support\Facades\Cache::forget('admin_reports_data');
        });
    }

    protected $fillable = [
        'user_id',
        'booking_type',
        'room_id',
        'sitter_id',
        'sitter_package',
        'coupon_id',
        'check_in',
        'check_out',
        'total_cats',
        'total_price',
        'discount_amount',
        'visit_time',
        'checkin_lat',
        'checkin_lng',
        'checkin_verified',
        'checkin_distance_m',
        'status',
        'payment_status',
        'snap_token',
        'midtrans_order_id',
        'refund_status',
        'refund_amount',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'checkin_verified' => 'boolean',
        'discount_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Check if this booking is eligible for a refund (H-2 policy).
     * Full refund if cancelled 2+ days before check_in.
     */
    public function isRefundEligible(): bool
    {
        if ($this->payment_status !== 'paid') {
            return false;
        }

        $checkInDate = \Carbon\Carbon::parse($this->check_in);
        $now = now();

        // Must be at least 2 days (H-2) before check-in
        return $now->lt($checkInDate->subDays(2));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function sitter()
    {
        return $this->belongsTo(Sitter::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function sitterReview()
    {
        return $this->hasOne(SitterReview::class);
    }

    public function cats()
    {
        return $this->belongsToMany(Cat::class, 'booking_cat');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
