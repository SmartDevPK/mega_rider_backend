<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReward extends Model
{
    use HasFactory;

    protected $table = 'customer_rewards';

    protected $fillable = [
        'customer_id',
        'type',
        'reference_date',
        'amount',
        'order_id',
    ];

    protected $casts = [
        'reference_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * The customer who received the reward
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Optional related order
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
