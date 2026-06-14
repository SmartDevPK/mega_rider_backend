<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * RiderReview Model
 * 
 * Manages customer reviews and ratings for riders.
 * Includes performance, speed, handling ratings and review content.
 */
class RiderReview extends Model
{
    use HasFactory;

    // =========================================================================
    // TABLE CONFIGURATION
    // =========================================================================

    /**
     * The table associated with the model.
     */
    protected $table = 'rider_reviews';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Relationships
        'order_id',
        'rider_id',
        'customer_id',

        // Ratings (1-5)
        'performance_rating',
        'speed_rating',
        'handling_rating',
        'communication_rating',

        // Calculated fields
        'average_rating',

        // Review content
        'review_content',
        'rider_response',

        // Status flags
        'is_approved',
        'is_edited',

        // Timestamps
        'edited_at',

        // Metadata
        'meta',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // No hidden fields by default
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Integer ratings
        'performance_rating' => 'integer',
        'speed_rating' => 'integer',
        'handling_rating' => 'integer',
        'communication_rating' => 'integer',

        // Decimal average
        'average_rating' => 'decimal:2',

        // Boolean flags
        'is_approved' => 'boolean',
        'is_edited' => 'boolean',

        // Strings
        'review_content' => 'string',
        'rider_response' => 'string',

        // Dates
        'edited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        // Arrays
        'meta' => 'array',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'is_approved' => true,
        'is_edited' => false,
        'communication_rating' => 0,
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'formatted_average_rating',
        'rating_level',
        'is_high_rating',
        'is_low_rating',
        'performance_level',
        'speed_level',
        'handling_level',
        'communication_level',
    ];

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    /**
     * Rating constants.
     */
    public const MIN_RATING = 1;
    public const MAX_RATING = 5;
    public const DEFAULT_RATING = 0;

    /**
     * Rating thresholds.
     */
    public const EXCELLENT_RATING = 5;
    public const GOOD_RATING = 4;
    public const AVERAGE_RATING = 3;
    public const POOR_RATING = 2;
    public const BAD_RATING = 1;

    /**
     * Rating level labels.
     */
    public const LEVEL_EXCELLENT = 'Excellent';
    public const LEVEL_GOOD = 'Good';
    public const LEVEL_AVERAGE = 'Average';
    public const LEVEL_POOR = 'Poor';
    public const LEVEL_BAD = 'Bad';

    /**
     * High rating threshold (for positive reviews).
     */
    public const HIGH_RATING_THRESHOLD = 4;

    /**
     * Low rating threshold (for negative reviews).
     */
    public const LOW_RATING_THRESHOLD = 2;

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the order associated with this review.
     * Uses UUID order_id from orders table.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    /**
     * Get the rider being reviewed.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }

    /**
     * Get the customer who wrote the review.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get approved reviews only.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope to get unapproved reviews.
     */
    public function scopeUnapproved(Builder $query): Builder
    {
        return $query->where('is_approved', false);
    }

    /**
     * Scope to get reviews for a specific rider.
     */
    public function scopeForRider(Builder $query, int $riderId): Builder
    {
        return $query->where('rider_id', $riderId);
    }

    /**
     * Scope to get reviews for a specific customer.
     */
    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to get reviews for a specific order.
     */
    public function scopeForOrder(Builder $query, string $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope to get high rating reviews (4+ stars).
     */
    public function scopeHighRating(Builder $query): Builder
    {
        return $query->where('average_rating', '>=', self::HIGH_RATING_THRESHOLD);
    }

    /**
     * Scope to get low rating reviews (2- stars).
     */
    public function scopeLowRating(Builder $query): Builder
    {
        return $query->where('average_rating', '<=', self::LOW_RATING_THRESHOLD);
    }

    /**
     * Scope to get reviews with minimum rating.
     */
    public function scopeWithMinRating(Builder $query, float $minRating): Builder
    {
        return $query->where('average_rating', '>=', $minRating);
    }

    /**
     * Scope to get reviews with maximum rating.
     */
    public function scopeWithMaxRating(Builder $query, float $maxRating): Builder
    {
        return $query->where('average_rating', '<=', $maxRating);
    }

    /**
     * Scope to get reviews with content.
     */
    public function scopeWithContent(Builder $query): Builder
    {
        return $query->whereNotNull('review_content')
            ->where('review_content', '!=', '');
    }

    /**
     * Scope to get reviews without content (rating only).
     */
    public function scopeWithoutContent(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('review_content')
                ->orWhere('review_content', '');
        });
    }

    /**
     * Scope to get reviews with rider response.
     */
    public function scopeWithResponse(Builder $query): Builder
    {
        return $query->whereNotNull('rider_response')
            ->where('rider_response', '!=', '');
    }

    /**
     * Scope to get reviews without rider response.
     */
    public function scopeWithoutResponse(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('rider_response')
                ->orWhere('rider_response', '');
        });
    }

    /**
     * Scope to get recent reviews.
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get today's reviews.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope to get reviews by rating level.
     */
    public function scopeByRatingLevel(Builder $query, string $level): Builder
    {
        return match ($level) {
            'excellent' => $query->where('average_rating', '>=', 4.5),
            'good' => $query->whereBetween('average_rating', [4.0, 4.49]),
            'average' => $query->whereBetween('average_rating', [3.0, 3.99]),
            'poor' => $query->whereBetween('average_rating', [2.0, 2.99]),
            'bad' => $query->where('average_rating', '<', 2.0),
            default => $query,
        };
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get formatted average rating.
     */
    public function getFormattedAverageRatingAttribute(): string
    {
        if ($this->average_rating === 0.00) {
            return 'No rating';
        }

        return number_format($this->average_rating, 1) . ' ★';
    }

    /**
     * Get rating level (Excellent, Good, Average, Poor, Bad).
     */
    public function getRatingLevelAttribute(): string
    {
        $rating = $this->average_rating;

        if ($rating >= 4.5) return self::LEVEL_EXCELLENT;
        if ($rating >= 4.0) return self::LEVEL_GOOD;
        if ($rating >= 3.0) return self::LEVEL_AVERAGE;
        if ($rating >= 2.0) return self::LEVEL_POOR;
        if ($rating > 0) return self::LEVEL_BAD;

        return 'Not rated';
    }

    /**
     * Get rating class for UI (badge color).
     */
    public function getRatingClassAttribute(): string
    {
        return match ($this->rating_level) {
            self::LEVEL_EXCELLENT => 'success',
            self::LEVEL_GOOD => 'primary',
            self::LEVEL_AVERAGE => 'warning',
            self::LEVEL_POOR => 'danger',
            self::LEVEL_BAD => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Check if this is a high rating (positive review).
     */
    public function getIsHighRatingAttribute(): bool
    {
        return $this->average_rating >= self::HIGH_RATING_THRESHOLD;
    }

    /**
     * Check if this is a low rating (negative review).
     */
    public function getIsLowRatingAttribute(): bool
    {
        return $this->average_rating <= self::LOW_RATING_THRESHOLD && $this->average_rating > 0;
    }

    /**
     * Get performance rating level.
     */
    public function getPerformanceLevelAttribute(): string
    {
        return $this->getLevelFromRating($this->performance_rating);
    }

    /**
     * Get speed rating level.
     */
    public function getSpeedLevelAttribute(): string
    {
        return $this->getLevelFromRating($this->speed_rating);
    }

    /**
     * Get handling rating level.
     */
    public function getHandlingLevelAttribute(): string
    {
        return $this->getLevelFromRating($this->handling_rating);
    }

    /**
     * Get communication rating level.
     */
    public function getCommunicationLevelAttribute(): string
    {
        return $this->getLevelFromRating($this->communication_rating);
    }

    /**
     * Get review summary (shortened version).
     */
    public function getReviewSummaryAttribute(): string
    {
        if (empty($this->review_content)) {
            return '';
        }

        if (strlen($this->review_content) <= 100) {
            return $this->review_content;
        }

        return substr($this->review_content, 0, 100) . '...';
    }

    /**
     * Get star rating display (HTML).
     */
    public function getStarsHtmlAttribute(): string
    {
        $fullStars = (int) floor($this->average_rating);
        $halfStar = ($this->average_rating - $fullStars) >= 0.5;
        $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

        $html = '';
        $html .= str_repeat('★', $fullStars);
        $html .= $halfStar ? '½' : '';
        $html .= str_repeat('☆', $emptyStars);

        return $html;
    }

    // =========================================================================
    // MUTATORS
    // =========================================================================

    /**
     * Set performance rating with validation.
     */
    public function setPerformanceRatingAttribute(?int $value): void
    {
        $this->attributes['performance_rating'] = $this->validateRating($value);
    }

    /**
     * Set speed rating with validation.
     */
    public function setSpeedRatingAttribute(?int $value): void
    {
        $this->attributes['speed_rating'] = $this->validateRating($value);
    }

    /**
     * Set handling rating with validation.
     */
    public function setHandlingRatingAttribute(?int $value): void
    {
        $this->attributes['handling_rating'] = $this->validateRating($value);
    }

    /**
     * Set communication rating with validation.
     */
    public function setCommunicationRatingAttribute(?int $value): void
    {
        $this->attributes['communication_rating'] = $this->validateRating($value);
    }

    // =========================================================================
    // BUSINESS LOGIC METHODS
    // =========================================================================

    /**
     * Calculate average rating from all rating categories.
     */
    public function calculateAverageRating(): float
    {
        $ratings = [
            $this->performance_rating,
            $this->speed_rating,
            $this->handling_rating,
            $this->communication_rating,
        ];

        // Filter out zero/null ratings
        $ratings = array_filter($ratings, function ($rating) {
            return $rating > 0;
        });

        if (empty($ratings)) {
            return 0.00;
        }

        $average = array_sum($ratings) / count($ratings);

        return round($average, 2);
    }

    /**
     * Update the average rating.
     */
    public function updateAverageRating(): self
    {
        $this->average_rating = $this->calculateAverageRating();

        return $this;
    }

    /**
     * Validate and sanitize rating value.
     */
    protected function validateRating(?int $rating): ?int
    {
        if ($rating === null || $rating === 0) {
            return null;
        }

        return max(self::MIN_RATING, min(self::MAX_RATING, $rating));
    }

    /**
     * Get rating level from numeric rating.
     */
    protected function getLevelFromRating(?int $rating): string
    {
        if ($rating === null || $rating === 0) {
            return 'Not rated';
        }

        return match ($rating) {
            5 => self::LEVEL_EXCELLENT,
            4 => self::LEVEL_GOOD,
            3 => self::LEVEL_AVERAGE,
            2 => self::LEVEL_POOR,
            1 => self::LEVEL_BAD,
            default => 'Not rated',
        };
    }

    /**
     * Mark as edited.
     */
    public function markAsEdited(): self
    {
        $this->is_edited = true;
        $this->edited_at = now();

        return $this;
    }

    /**
     * Approve the review.
     */
    public function approve(): self
    {
        $this->is_approved = true;
        $this->save();

        return $this;
    }

    /**
     * Disapprove the review.
     */
    public function disapprove(): self
    {
        $this->is_approved = false;
        $this->save();

        return $this;
    }

    /**
     * Add rider response to review.
     */
    public function addRiderResponse(string $response): self
    {
        $this->rider_response = trim($response);

        $this->save();
        return $this;
    }

    /**
     * Check if rider has responded.
     */
    public function hasRiderResponse(): bool
    {
        return !empty($this->rider_response);
    }

    // =========================================================================
    // STATISTICS METHODS
    // =========================================================================

    /**
     * Get average rating for a rider.
     */
    public static function getAverageRatingForRider(int $riderId): float
    {
        return (float) self::forRider($riderId)
            ->approved()
            ->avg('average_rating') ?? 0.00;
    }

    /**
     * Get total reviews count for a rider.
     */
    public static function getReviewCountForRider(int $riderId): int
    {
        return self::forRider($riderId)->approved()->count();
    }

    /**
     * Get rating distribution for a rider.
     */
    public static function getRatingDistributionForRider(int $riderId): array
    {
        return [
            'excellent' => self::forRider($riderId)->approved()->byRatingLevel('excellent')->count(),
            'good' => self::forRider($riderId)->approved()->byRatingLevel('good')->count(),
            'average' => self::forRider($riderId)->approved()->byRatingLevel('average')->count(),
            'poor' => self::forRider($riderId)->approved()->byRatingLevel('poor')->count(),
            'bad' => self::forRider($riderId)->approved()->byRatingLevel('bad')->count(),
        ];
    }

    /**
     * Get recent reviews for a rider.
     */
    public static function getRecentReviewsForRider(int $riderId, int $limit = 10): \Illuminate\Support\Collection
    {
        return self::forRider($riderId)
            ->approved()
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    // =========================================================================
    // BOOT METHOD
    // =========================================================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (RiderReview $review): void {
            // Calculate average rating before saving
            $review->average_rating = $review->calculateAverageRating();
        });

        static::updating(function (RiderReview $review): void {
            // Recalculate average rating if any rating field changed
            if ($review->isDirty(['performance_rating', 'speed_rating', 'handling_rating', 'communication_rating'])) {
                $review->average_rating = $review->calculateAverageRating();
            }
        });

        static::saved(function (RiderReview $review): void {
            // Update rider's aggregated ratings
            if ($review->is_approved) {
                $rating = self::getAverageRatingForRider($review->rider_id);

                // Update rider's rating
                if ($review->rider) {
                    $review->rider->update(['rating' => $rating]);
                }

                // Update or create rider rating record
                $riderRating = RiderRating::firstOrCreate(['rider_id' => $review->rider_id]);
                $riderRating->addRating([
                    'performance' => $review->performance_rating,
                    'speed' => $review->speed_rating,
                    'handling' => $review->handling_rating,
                    'communication' => $review->communication_rating,
                ], $review->average_rating);
            }
        });
    }
}
