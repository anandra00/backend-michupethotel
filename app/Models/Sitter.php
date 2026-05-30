<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sitter extends Model
{
    protected $fillable = [
        'name', 'phone', 'area', 'speciality', 'status', 'rating', 'visits',
    ];

    public function reviews()
    {
        return $this->hasMany(SitterReview::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}

