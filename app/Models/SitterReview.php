<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SitterReview extends Model
{
    protected $fillable = ['user_id', 'sitter_id', 'booking_id', 'rating', 'comment'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sitter()
    {
        return $this->belongsTo(Sitter::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
