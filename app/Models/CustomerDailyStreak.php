<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerDailyStreak extends Model
{
    use HasFactory;

    protected $table = 'customer_daily_streaks';

    protected $fillable = [
        'customer_id',
        'date',
        'delivery_count',
        'reward_claimed',
    ];

    protected $casts = [
        'date' => 'date',
        'delivery_count' => 'integer',
        'reward_claimed' => 'boolean',
    ];

    /**
     * Relationship: streak belongs to a customer (user)
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
