<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cat extends Model
{
    protected $fillable = [
        'user_id', 'name', 'breed', 'age', 'weight', 'gender', 'notes', 'photo',
    ];
}
