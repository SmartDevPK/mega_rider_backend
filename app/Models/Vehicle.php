<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'vehicle_type',  // Added this field
        'license_plate',
        'make',
        'model',
        'year',
        'color',
        'vin',
        'driver_id',     // Added driver_id
        'driver_name',   // Added denormalized fields
        'driver_phone',
        'driver_image',
        'insurance_fee',
        'insurance_paid',
        'insurance_paid_at',
    ];

    /**
     * Cast attributes to proper data types
     */
    protected $casts = [
        'year'              => 'integer',
        'insurance_fee'     => 'decimal:2',
        'insurance_paid'    => 'boolean',
        'insurance_paid_at' => 'datetime',
    ];

    /**
     * Default attribute values
     */
    protected $attributes = [
        'insurance_paid' => false,  // Changed from null to match migration default
    ];

    /**
     * Get the driver that owns the vehicle
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}