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
        'check_in',
        'check_out',
        'total_cats',
        'total_price',
        'visit_time',
        'status',
        'payment_status',
        'snap_token',
        'notes',
    ];

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
