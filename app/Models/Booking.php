<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
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
}
