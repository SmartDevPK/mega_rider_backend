<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSurCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'order_type_id', 'delivery_fee',
        'surge_multiplier', 'surge_fee', 'total_amount'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderType()
    {
        return $this->belongsTo(OrderType::class);
    }
}
