<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderReview extends Model
{
    protected $table = 'rider_reviews';

    protected $fillable = [
        'order_id',
        'rider_id',
        'customer_id',
        'performance_rating',
        'speed_rating',
        'handling_rating',
        'average_rating',
        'review_content',
    ];

    protected $casts = [
        'performance_rating' => 'integer',
        'speed_rating'       => 'integer',
        'handling_rating'    => 'integer',
        'average_rating'     => 'decimal:2',   // matches DECIMAL(3,2)
        'review_content'     => 'string',
    ];

    // Relationships
    public function order()
    {
        // 'order_id' in this table references 'order_id' column in orders table (UUID)
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}