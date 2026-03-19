<?php
// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
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
        'order_type',
        'vehicle_type',
        'order_instruction',
        'travel_time',
        'distance_km',
        'delivery_fee',
        
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
        'cancellation_reason'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
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
        
        // Dates
        'date_payment_confirmed' => 'datetime',
        'driver_assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'pending',
        'payment_status' => 'pending',
        'order_type' => 'standard',
        'package_insurance' => false,
        'use_my_details' => false,
        'package_category' => 'other'
    ];

    /**
     * The relationships that should be eager loaded by default.
     *
     * @var array
     */
    protected $with = [];

    /**
     * Get the customer that owns the order.
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the driver assigned to the order.
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the formatted package worth.
     */
    public function getFormattedPackageWorthAttribute(): string
    {
        return '₦' . number_format($this->package_worth, 2);
    }

    /**
     * Get the formatted insurance fee.
     */
    public function getFormattedInsuranceFeeAttribute(): string
    {
        return $this->insurance_fee ? '₦' . number_format($this->insurance_fee, 2) : '₦0.00';
    }

    /**
     * Get the formatted delivery fee.
     */
    public function getFormattedDeliveryFeeAttribute(): string
    {
        return $this->delivery_fee ? '₦' . number_format($this->delivery_fee, 2) : '₦0.00';
    }

    /**
     * Get the full pickup address.
     */
    public function getFullPickupAddressAttribute(): string
    {
        $parts = array_filter([
            $this->pickup_address,
            $this->pickup_city,
            $this->pickup_state,
            $this->pickup_zip_code
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Get the full delivery address.
     */
    public function getFullDeliveryAddressAttribute(): string
    {
        $parts = array_filter([
            $this->delivery_address,
            $this->dropoff_city,
            $this->dropoff_state,
            $this->dropoff_zip_code
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Scope a query to only include pending orders.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include active orders.
     */
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

    /**
     * Scope a query to only include completed orders.
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['delivered', 'completed']);
    }

    /**
     * Scope a query to only include orders for a specific customer.
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope a query to only include orders for a specific driver.
     */
    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }
}