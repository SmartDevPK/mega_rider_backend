<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderType extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'base_price',
        'price_per_km',
        'base_distance',
        'description'
    ];
}
