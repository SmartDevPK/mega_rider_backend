<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Promotion extends Model
{
    use HasFactory;

    protected $table = 'promotions';

    protected $fillable = [
        'title',
        'body',
        'promo_code',
        'ends_by',
    ];

    protected $casts = [
        'ends_by' => 'datetime',
    ];
}
