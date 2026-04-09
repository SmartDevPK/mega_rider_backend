<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderRating extends Model
{
    protected $table = 'rider_ratings';

    protected $fillable = [
        'rider_id',
        'total_ratings',
        'total_performance',
        'total_speed',
        'total_handling',
        'avg_performance',
        'avg_speed',
        'avg_handling',
        'overall_rating',
    ];

    protected $casts = [
        'total_ratings'      => 'integer',
        'total_performance'  => 'integer',
        'total_speed'        => 'integer',
        'total_handling'     => 'integer',
        'avg_performance'    => 'decimal:2',   // matches DECIMAL(3,2)
        'avg_speed'          => 'decimal:2',
        'avg_handling'       => 'decimal:2',
        'overall_rating'     => 'decimal:2',
    ];

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }
}