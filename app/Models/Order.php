<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'order_id',
        'customer_id',
        'rider_id',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'pickup_city',
        'dropoff_address',
        'dropoff_latitude',
        'dropoff_longitude',
        'dropoff_city',
        'sender_name',
        'sender_phone',
        'sender_email',
        'receiver_name',
        'receiver_phone',
        'receiver_email',
        'package_name',
        'package_worth',
        'package_image',
        'insurance_flag',
        'price',        // Added
        'item_name',    // Added
        'status',
        'cancelled_at',
        'cancellation_reason',
        'zone_id',
        'insurance_fee',
        'special_instructions',
    ];

    protected $casts = [
        'pickup_latitude'   => 'decimal:7',
        'pickup_longitude'  => 'decimal:7',
        'dropoff_latitude'  => 'decimal:7',
        'dropoff_longitude' => 'decimal:7',
        'package_worth'     => 'decimal:2',
        'price'             => 'decimal:2',   // Added
        'insurance_flag'    => 'boolean',
        'cancelled_at'      => 'datetime',
        'status'            => 'string',
    ];

    protected $dates = [
        'cancelled_at',
    ];

    // Automatically generate UUID for order_id when creating
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_id)) {
                $order->order_id = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    // Helpers for cancellation
    public function isCancellable(): bool
    {
        return in_array($this->status, ['pending', 'assigned', 'picked_up']);
    }

    public function cancel(string $reason = null): void
    {
        $this->status = 'cancelled';
        $this->cancelled_at = now();
        $this->cancellation_reason = $reason;
        $this->save();
    }

    // Scope to get only cancellable orders
    public function scopeCancellable($query)
    {
        return $query->whereIn('status', ['pending', 'assigned', 'picked_up']);
    }

    
    public function orderType()
{
    return $this->belongsTo(OrderType::class, 'order_type_id'); // Assuming you have order_type_id column
}
}
