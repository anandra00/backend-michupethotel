<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sitter extends Model
{
    protected $fillable = [
        'name', 'phone', 'area', 'speciality', 'status', 'rating', 'visits',
    ];

    protected $appends = ['avg_rating', 'review_count'];

    public function reviews()
    {
        return $this->hasMany(SitterReview::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // Computed average rating from actual reviews
    public function getAvgRatingAttribute()
    {
        $avg = $this->reviews()->avg('rating');

        return $avg ? round($avg, 1) : 0;
    }

    public function getReviewCountAttribute()
    {
        return $this->reviews()->count();
    }

    public function getVisitsAttribute($value)
    {
        return $this->bookings()->whereIn('status', ['checked_out'])->count();
    }
}
