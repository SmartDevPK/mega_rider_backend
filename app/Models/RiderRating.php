<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RiderRating Model
 * 
 * Aggregates and caches rider rating statistics for optimal performance.
 * Prevents expensive AVG() calculations on millions of review records.
 */
class RiderRating extends Model
{
    use HasFactory;

    // =========================================================================
    // TABLE CONFIGURATION
    // =========================================================================

    /**
     * The table associated with the model.
     */
    protected $table = 'rider_ratings';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Relationships
        'rider_id',

        // Statistics counts
        'total_ratings',
        'total_performance',
        'total_speed',
        'total_handling',
        'total_communication',

        // Averages
        'avg_performance',
        'avg_speed',
        'avg_handling',
        'avg_communication',
        'overall_rating',

        // Additional metrics
        'five_star_count',
        'four_star_count',
        'three_star_count',
        'two_star_count',
        'one_star_count',

        // Timestamps
        'last_updated_at',
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
        // Integers
        'total_ratings' => 'integer',
        'total_performance' => 'integer',
        'total_speed' => 'integer',
        'total_handling' => 'integer',
        'total_communication' => 'integer',
        'five_star_count' => 'integer',
        'four_star_count' => 'integer',
        'three_star_count' => 'integer',
        'two_star_count' => 'integer',
        'one_star_count' => 'integer',

        // Decimals (averages)
        'avg_performance' => 'decimal:2',
        'avg_speed' => 'decimal:2',
        'avg_handling' => 'decimal:2',
        'avg_communication' => 'decimal:2',
        'overall_rating' => 'decimal:2',

        // Dates
        'last_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'total_ratings' => 0,
        'total_performance' => 0,
        'total_speed' => 0,
        'total_handling' => 0,
        'total_communication' => 0,
        'avg_performance' => 0.00,
        'avg_speed' => 0.00,
        'avg_handling' => 0.00,
        'avg_communication' => 0.00,
        'overall_rating' => 0.00,
        'five_star_count' => 0,
        'four_star_count' => 0,
        'three_star_count' => 0,
        'two_star_count' => 0,
        'one_star_count' => 0,
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'formatted_overall_rating',
        'rating_distribution',
        'rating_percentages',
    ];

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    /**
     * Rating scale constants.
     */
    public const MAX_RATING = 5;
    public const MIN_RATING = 1;
    public const DEFAULT_RATING = 0;

    /**
     * Rating thresholds for different levels.
     */
    public const EXCELLENT_THRESHOLD = 4.5;
    public const GOOD_THRESHOLD = 4.0;
    public const AVERAGE_THRESHOLD = 3.0;
    public const POOR_THRESHOLD = 2.0;

    /**
     * Rating labels.
     */
    public static array $ratingLevels = [
        self::EXCELLENT_THRESHOLD => 'Excellent',
        self::GOOD_THRESHOLD => 'Good',
        self::AVERAGE_THRESHOLD => 'Average',
        self::POOR_THRESHOLD => 'Poor',
        0 => 'Unrated',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the rider that owns this rating record.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get riders with high ratings (excellent).
     */
    public function scopeExcellent($query)
    {
        return $query->where('overall_rating', '>=', self::EXCELLENT_THRESHOLD);
    }

    /**
     * Scope to get riders with good ratings.
     */
    public function scopeGood($query)
    {
        return $query->whereBetween('overall_rating', [
            self::GOOD_THRESHOLD,
            self::EXCELLENT_THRESHOLD - 0.01
        ]);
    }

    /**
     * Scope to get riders with average ratings.
     */
    public function scopeAverage($query)
    {
        return $query->whereBetween('overall_rating', [
            self::AVERAGE_THRESHOLD,
            self::GOOD_THRESHOLD - 0.01
        ]);
    }

    /**
     * Scope to get riders with poor ratings.
     */
    public function scopePoor($query)
    {
        return $query->where('overall_rating', '<', self::AVERAGE_THRESHOLD)
            ->where('overall_rating', '>', 0);
    }

    /**
     * Scope to get riders with no ratings.
     */
    public function scopeUnrated($query)
    {
        return $query->where('total_ratings', 0);
    }

    /**
     * Scope to get riders with minimum rating.
     */
    public function scopeWithMinRating($query, float $minRating)
    {
        return $query->where('overall_rating', '>=', $minRating);
    }

    /**
     * Scope to get riders with at least X ratings.
     */
    public function scopeWithMinRatingsCount($query, int $minCount)
    {
        return $query->where('total_ratings', '>=', $minCount);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get formatted overall rating (e.g., "4.5 ★").
     */
    public function getFormattedOverallRatingAttribute(): string
    {
        if ($this->total_ratings === 0) {
            return 'No ratings';
        }

        return number_format($this->overall_rating, 1) . ' ★';
    }

    /**
     * Get rating level (Excellent, Good, Average, Poor, Unrated).
     */
    public function getRatingLevelAttribute(): string
    {
        if ($this->total_ratings === 0) {
            return 'Unrated';
        }

        foreach (self::$ratingLevels as $threshold => $level) {
            if ($this->overall_rating >= $threshold) {
                return $level;
            }
        }

        return 'Unrated';
    }

    /**
     * Get rating class for UI (badge color).
     */
    public function getRatingClassAttribute(): string
    {
        return match ($this->rating_level) {
            'Excellent' => 'success',
            'Good' => 'primary',
            'Average' => 'warning',
            'Poor' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get rating distribution as array.
     */
    public function getRatingDistributionAttribute(): array
    {
        return [
            5 => $this->five_star_count,
            4 => $this->four_star_count,
            3 => $this->three_star_count,
            2 => $this->two_star_count,
            1 => $this->one_star_count,
        ];
    }

    /**
     * Get rating percentages for chart display.
     */
    public function getRatingPercentagesAttribute(): array
    {
        if ($this->total_ratings === 0) {
            return [
                5 => 0,
                4 => 0,
                3 => 0,
                2 => 0,
                1 => 0,
            ];
        }

        return [
            5 => round(($this->five_star_count / $this->total_ratings) * 100, 1),
            4 => round(($this->four_star_count / $this->total_ratings) * 100, 1),
            3 => round(($this->three_star_count / $this->total_ratings) * 100, 1),
            2 => round(($this->two_star_count / $this->total_ratings) * 100, 1),
            1 => round(($this->one_star_count / $this->total_ratings) * 100, 1),
        ];
    }

    /**
     * Get performance rating level.
     */
    public function getPerformanceLevelAttribute(): string
    {
        return $this->getLevelFromRating($this->avg_performance);
    }

    /**
     * Get speed rating level.
     */
    public function getSpeedLevelAttribute(): string
    {
        return $this->getLevelFromRating($this->avg_speed);
    }

    /**
     * Get handling rating level.
     */
    public function getHandlingLevelAttribute(): string
    {
        return $this->getLevelFromRating($this->avg_handling);
    }

    // =========================================================================
    // BUSINESS LOGIC METHODS
    // =========================================================================

    /**
     * Add a new rating to the rider's statistics.
     */
    public function addRating(array $ratings, ?int $overall = null): self
    {
        // Increment total ratings
        $this->total_ratings += 1;

        // Add to category totals
        $this->total_performance += $ratings['performance'] ?? 0;
        $this->total_speed += $ratings['speed'] ?? 0;
        $this->total_handling += $ratings['handling'] ?? 0;
        $this->total_communication += $ratings['communication'] ?? 0;

        // Update star distribution
        $starRating = $overall ?? $this->calculateOverallFromRatings($ratings);
        $this->incrementStarCount($starRating);

        // Recalculate averages
        $this->recalculateAverages();

        // Update timestamp
        $this->last_updated_at = now();

        $this->save();

        // Also update the rider's cached rating
        if ($this->rider) {
            $this->rider->update([
                'rating' => $this->overall_rating,
            ]);
        }

        return $this;
    }

    /**
     * Update rating after a review is edited.
     */
    public function updateRating(array $oldRatings, array $newRatings): self
    {
        // Subtract old ratings
        $this->total_performance -= $oldRatings['performance'] ?? 0;
        $this->total_speed -= $oldRatings['speed'] ?? 0;
        $this->total_handling -= $oldRatings['handling'] ?? 0;
        $this->total_communication -= $oldRatings['communication'] ?? 0;

        // Add new ratings
        $this->total_performance += $newRatings['performance'] ?? 0;
        $this->total_speed += $newRatings['speed'] ?? 0;
        $this->total_handling += $newRatings['handling'] ?? 0;
        $this->total_communication += $newRatings['communication'] ?? 0;

        // Update star distribution (if overall changed)
        $oldOverall = $oldRatings['overall'] ?? $this->calculateOverallFromRatings($oldRatings);
        $newOverall = $newRatings['overall'] ?? $this->calculateOverallFromRatings($newRatings);

        if ($oldOverall !== $newOverall) {
            $this->decrementStarCount($oldOverall);
            $this->incrementStarCount($newOverall);
        }

        // Recalculate averages
        $this->recalculateAverages();

        // Update timestamp
        $this->last_updated_at = now();

        $this->save();

        // Update rider's cached rating
        if ($this->rider) {
            $this->rider->update([
                'rating' => $this->overall_rating,
            ]);
        }

        return $this;
    }

    /**
     * Remove a rating (when review is deleted).
     */
    public function removeRating(array $ratings, ?int $overall = null): self
    {
        // Decrement total ratings
        $this->total_ratings -= 1;

        // Subtract from category totals
        $this->total_performance -= $ratings['performance'] ?? 0;
        $this->total_speed -= $ratings['speed'] ?? 0;
        $this->total_handling -= $ratings['handling'] ?? 0;
        $this->total_communication -= $ratings['communication'] ?? 0;

        // Update star distribution
        $starRating = $overall ?? $this->calculateOverallFromRatings($ratings);
        $this->decrementStarCount($starRating);

        // Recalculate averages (or set to 0 if no ratings)
        if ($this->total_ratings > 0) {
            $this->recalculateAverages();
        } else {
            $this->resetAverages();
        }

        // Update timestamp
        $this->last_updated_at = now();

        $this->save();

        // Update rider's cached rating
        if ($this->rider) {
            $this->rider->update([
                'rating' => $this->overall_rating,
            ]);
        }

        return $this;
    }

    /**
     * Recalculate all average ratings.
     */
    public function recalculateAverages(): self
    {
        if ($this->total_ratings === 0) {
            return $this->resetAverages();
        }

        $this->avg_performance = round($this->total_performance / $this->total_ratings, 2);
        $this->avg_speed = round($this->total_speed / $this->total_ratings, 2);
        $this->avg_handling = round($this->total_handling / $this->total_ratings, 2);
        $this->avg_communication = round($this->total_communication / $this->total_ratings, 2);

        // Calculate overall rating (weighted average)
        $totalWeighted = ($this->total_performance + $this->total_speed + $this->total_handling + $this->total_communication);
        $totalCategories = 4;

        $this->overall_rating = round($totalWeighted / ($this->total_ratings * $totalCategories), 2);

        return $this;
    }

    /**
     * Reset all averages to zero.
     */
    public function resetAverages(): self
    {
        $this->avg_performance = 0.00;
        $this->avg_speed = 0.00;
        $this->avg_handling = 0.00;
        $this->avg_communication = 0.00;
        $this->overall_rating = 0.00;

        return $this;
    }

    /**
     * Increment star count for a rating.
     */
    protected function incrementStarCount(int $rating): void
    {
        match ($rating) {
            5 => $this->five_star_count++,
            4 => $this->four_star_count++,
            3 => $this->three_star_count++,
            2 => $this->two_star_count++,
            1 => $this->one_star_count++,
            default => null,
        };
    }

    /**
     * Decrement star count for a rating.
     */
    protected function decrementStarCount(int $rating): void
    {
        match ($rating) {
            5 => $this->five_star_count = max(0, $this->five_star_count - 1),
            4 => $this->four_star_count = max(0, $this->four_star_count - 1),
            3 => $this->three_star_count = max(0, $this->three_star_count - 1),
            2 => $this->two_star_count = max(0, $this->two_star_count - 1),
            1 => $this->one_star_count = max(0, $this->one_star_count - 1),
            default => null,
        };
    }

    /**
     * Calculate overall rating from individual ratings.
     */
    protected function calculateOverallFromRatings(array $ratings): int
    {
        $total = ($ratings['performance'] ?? 0)
            + ($ratings['speed'] ?? 0)
            + ($ratings['handling'] ?? 0)
            + ($ratings['communication'] ?? 0);

        $count = 4; // Number of rating categories

        if ($total === 0 || $count === 0) {
            return 0;
        }

        return (int) round($total / $count);
    }

    /**
     * Get rating level from numeric rating.
     */
    protected function getLevelFromRating(float $rating): string
    {
        if ($rating === 0.00) {
            return 'Unrated';
        }

        foreach (self::$ratingLevels as $threshold => $level) {
            if ($rating >= $threshold) {
                return $level;
            }
        }

        return 'Unrated';
    }

    // =========================================================================
    // STATISTICS METHODS
    // =========================================================================

    /**
     * Get average rating for all riders.
     */
    public static function getGlobalAverageRating(): float
    {
        return (float) self::avg('overall_rating') ?? 0.00;
    }

    /**
     * Get total number of ratings across all riders.
     */
    public static function getTotalRatingsCount(): int
    {
        return (int) self::sum('total_ratings');
    }

    /**
     * Get rating distribution for all riders.
     */
    public static function getGlobalRatingDistribution(): array
    {
        return [
            5 => (int) self::sum('five_star_count'),
            4 => (int) self::sum('four_star_count'),
            3 => (int) self::sum('three_star_count'),
            2 => (int) self::sum('two_star_count'),
            1 => (int) self::sum('one_star_count'),
        ];
    }

    // =========================================================================
    // BOOT METHOD
    // =========================================================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($rating) {
            if (empty($rating->last_updated_at)) {
                $rating->last_updated_at = now();
            }
        });
    }
}
