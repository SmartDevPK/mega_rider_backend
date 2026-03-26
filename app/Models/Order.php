<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'orders';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [

        // Identification
        'order_id',
        'customer_id',
        'driver_id',

        // Pickup Information
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'pickup_city',
        'pickup_state',
        'pickup_zip_code',
        'pickup_instructions',

        // Delivery Information
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'dropoff_city',
        'dropoff_state',
        'dropoff_zip_code',
        'delivery_instructions',

        // Sender Details
        'sender_name',
        'sender_email',
        'sender_phone',
        'use_my_details',

        // Receiver Details
        'receiver_name',
        'receiver_email',
        'receiver_phone',

        // Package Details
        'package_name',
        'package_image',
        'package_worth',
        'package_insurance',
        'insurance_fee',
        'package_weight',
        'package_dimensions',
        'package_category',

        // Order Details
        'vehicle_type',
        'order_instruction',
        'travel_time',
        'distance_km',
        'delivery_fee',

        // Tip Information
        'tip_amount',
        'tip_method',
        'tip_added_at',

        // Status
        'status',

        // Payment Information
        'payment_status',
        'payment_method',
        'payment_reference',
        'date_payment_confirmed',

        // Tracking Information
        'driver_assigned_at',
        'picked_up_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [

        // Booleans
        'use_my_details' => 'boolean',
        'package_insurance' => 'boolean',

        // Decimals
        'pickup_latitude' => 'decimal:8',
        'pickup_longitude' => 'decimal:8',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'package_worth' => 'decimal:2',
        'insurance_fee' => 'decimal:2',
        'package_weight' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'tip_amount' => 'decimal:2',

        // Dates
        'tip_added_at' => 'datetime',
        'date_payment_confirmed' => 'datetime',
        'driver_assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Default values
     */
    protected $attributes = [
        'status' => 'pending',
        'payment_status' => 'pending',
        'package_insurance' => false,
        'use_my_details' => false,
        'package_category' => 'other',
    ];

    /**
     * Relationships
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Accessors
     */
    public function getFormattedPackageWorthAttribute(): string
    {
        return '₦' . number_format($this->package_worth, 2);
    }

    public function getFormattedInsuranceFeeAttribute(): string
    {
        return $this->insurance_fee
            ? '₦' . number_format($this->insurance_fee, 2)
            : '₦0.00';
    }

    public function getFormattedDeliveryFeeAttribute(): string
    {
        return $this->delivery_fee
            ? '₦' . number_format($this->delivery_fee, 2)
            : '₦0.00';
    }

    public function getFullPickupAddressAttribute(): string
    {
        return implode(', ', array_filter([
            $this->pickup_address,
            $this->pickup_city,
            $this->pickup_state,
            $this->pickup_zip_code
        ]));
    }

    public function getFullDeliveryAddressAttribute(): string
    {
        return implode(', ', array_filter([
            $this->delivery_address,
            $this->dropoff_city,
            $this->dropoff_state,
            $this->dropoff_zip_code
        ]));
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            'confirmed',
            'processing',
            'driver_assigned',
            'picked_up',
            'in_transit'
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['delivered', 'completed']);
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }
}
