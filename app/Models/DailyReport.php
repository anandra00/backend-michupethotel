<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    protected $fillable = [
        'booking_id',
        'cat_id',
        'title',
        'description',
        'badge',
        'badge_bg',
        'icon_type',
        'photo_path',
        'time',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function cat()
    {
        return $this->belongsTo(Cat::class);
    }
}
