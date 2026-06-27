<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class RideRating extends Model
{
    use HasFactory;

    protected $table = 'ride_ratings';

    protected $fillable = [
        'ride_id',
        'customer_id',
        'rider_id',
        'customer_rating',
        'rider_rating',
        'customer_comment',
        'rider_comment',
        'customer_ratings_details',
        'rider_ratings_details',
        'is_public',
        'is_edited',
        'edited_at',
        'is_reported',
        'report_reason',
        'reported_by',
    ];

    protected $casts = [
        'customer_ratings_details' => 'array',
        'rider_ratings_details' => 'array',
        'is_public' => 'boolean',
        'is_edited' => 'boolean',
        'is_reported' => 'boolean',
        'edited_at' => 'datetime',
        'customer_rating' => 'integer',
        'rider_rating' => 'integer',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reported_by');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getCustomerStarDisplayAttribute(): string
    {
        return str_repeat('⭐', $this->customer_rating) . str_repeat('☆', 5 - $this->customer_rating);
    }

    public function getRiderStarDisplayAttribute(): string
    {
        return str_repeat('⭐', $this->rider_rating) . str_repeat('☆', 5 - $this->rider_rating);
    }

    public function getCustomerRatingPercentageAttribute(): int
    {
        return round(($this->customer_rating / 5) * 100);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeHighRated(Builder $query, $minRating = 4)
    {
        return $query->where('customer_rating', '>=', $minRating);
    }

    public function scopeLowRated(Builder $query, $maxRating = 2)
    {
        return $query->where('customer_rating', '<=', $maxRating);
    }

    public function scopePublic(Builder $query)
    {
        return $query->where('is_public', true);
    }

    public function scopeForRider(Builder $query, int $riderId): Builder
    {
        return $query->where('rider_id', $riderId);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
