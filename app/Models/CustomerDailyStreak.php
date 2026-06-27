<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

/**
 * CustomerDailyStreak Model
 * 
 * Tracks daily delivery streaks and rewards for customers
 */
class CustomerDailyStreak extends Model
{
    use HasFactory;

    // =========================================================================
    // TABLE CONFIGURATION
    // =========================================================================

    protected $table = 'customer_daily_streaks';

    protected $fillable = [
        'customer_id',
        'date',
        'delivery_count',
        'reward_claimed',
        'streak_count',
        'reward_type',
        'reward_amount',
        'reward_claimed_at',
    ];

    protected $hidden = [
        // Add any sensitive fields here if needed
    ];

    protected $casts = [
        // Dates
        'date' => 'date',
        'reward_claimed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        // Integers
        'delivery_count' => 'integer',
        'streak_count' => 'integer',

        // Decimals
        'reward_amount' => 'decimal:2',

        // Booleans
        'reward_claimed' => 'boolean',

        // Arrays
        'meta' => 'array',
    ];

    protected $attributes = [
        'delivery_count' => 0,
        'reward_claimed' => false,
        'streak_count' => 0,
        'reward_amount' => 0.00,
    ];

    protected $appends = [
        'is_completed',
        'formatted_reward_amount',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the customer (user) who owns this streak
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    // =========================================================================
    // ACCESSORS & MUTATORS
    // =========================================================================

    /**
     * Check if the streak is completed (has deliveries)
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->delivery_count > 0;
    }

    /**
     * Get formatted reward amount
     */
    public function getFormattedRewardAmountAttribute(): string
    {
        return '₦' . number_format($this->reward_amount, 2);
    }

    /**
     * Set reward amount with proper formatting
     */
    public function setRewardAmountAttribute(float $value): void
    {
        $this->attributes['reward_amount'] = round($value, 2);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get streaks for a specific customer
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to get streaks for a specific date
     */
    public function scopeOnDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Scope to get unclaimed rewards
     */
    public function scopeUnclaimed($query)
    {
        return $query->where('reward_claimed', false)
            ->where('delivery_count', '>', 0);
    }

    /**
     * Scope to get claimed rewards
     */
    public function scopeClaimed($query)
    {
        return $query->where('reward_claimed', true);
    }

    /**
     * Scope to get streaks with minimum delivery count
     */
    public function scopeWithMinDeliveries($query, int $minCount)
    {
        return $query->where('delivery_count', '>=', $minCount);
    }

    /**
     * Scope to get streaks within date range
     */
    public function scopeBetweenDates($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to get current week streaks
     */
    public function scopeCurrentWeek($query)
    {
        return $query->whereBetween('date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Scope to get current month streaks
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereBetween('date', [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ]);
    }

    // =========================================================================
    // BUSINESS LOGIC METHODS
    // =========================================================================

    /**
     * Increment delivery count for this streak
     */
    public function incrementDeliveryCount(): void
    {
        $this->increment('delivery_count');
        $this->updateStreakCount();
    }

    /**
     * Update streak count based on consecutive days
     */
    public function updateStreakCount(): void
    {
        $previousDay = $this->where('customer_id', $this->customer_id)
            ->whereDate('date', $this->date->subDay())
            ->first();

        if ($previousDay && $previousDay->delivery_count > 0) {
            $this->streak_count = $previousDay->streak_count + 1;
        } else {
            $this->streak_count = 1;
        }

        $this->saveQuietly();
    }

    /**
     * Mark reward as claimed
     */
    public function claimReward(): bool
    {
        if ($this->reward_claimed) {
            return false;
        }

        $this->reward_claimed = true;
        $this->reward_claimed_at = now();

        return $this->save();
    }

    /**
     * Check if reward can be claimed
     */
    public function canClaimReward(): bool
    {
        return !$this->reward_claimed && $this->delivery_count >= $this->getRequiredDeliveriesForReward();
    }

    /**
     * Get required deliveries for reward (configurable)
     */
    protected function getRequiredDeliveriesForReward(): int
    {
        // This could be fetched from settings or config
        return config('streaks.required_deliveries', 3);
    }

    /**
     * Calculate reward amount based on streak
     */
    public function calculateRewardAmount(): float
    {
        $baseReward = 500; // Base reward in Naira
        $bonusPerStreak = 100; // Bonus per consecutive streak

        return $baseReward + ($this->streak_count * $bonusPerStreak);
    }

    /**
     * Apply reward to customer wallet
     */
    public function applyRewardToWallet(): bool
    {
        if (!$this->canClaimReward()) {
            return false;
        }

        $amount = $this->calculateRewardAmount();
        $this->reward_amount = $amount;

        // Add to customer wallet
        if ($this->customer && $this->customer->addToWallet($amount)) {
            $this->claimReward();

            // Create transaction record
            $this->customer->transactions()->create([
                'amount' => $amount,
                'type' => 'credit',
                'description' => "Daily streak reward for {$this->date->format('Y-m-d')}",
                'reference' => "STREAK_{$this->id}_{$this->date->format('Ymd')}",
            ]);

            return true;
        }

        return false;
    }

    // =========================================================================
    // STATISTICS METHODS
    // =========================================================================

    /**
     * Get longest streak for a customer
     */
    public static function getLongestStreak(int $customerId): int
    {
        return static::where('customer_id', $customerId)
            ->max('streak_count') ?? 0;
    }

    /**
     * Get current active streak for customer
     */
    public static function getCurrentStreak(int $customerId): int
    {
        $today = static::forCustomer($customerId)
            ->onDate(now()->toDateString())
            ->first();

        if (!$today || $today->delivery_count === 0) {
            return 0;
        }

        return $today->streak_count;
    }

    /**
     * Get total rewards claimed by customer
     */
    public static function getTotalRewardsClaimed(int $customerId): float
    {
        return static::forCustomer($customerId)
            ->where('reward_claimed', true)
            ->sum('reward_amount');
    }

    // =========================================================================
    // BOOT METHOD
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($streak) {
            // Ensure streak count is calculated on creation
            if (!isset($streak->streak_count)) {
                $streak->streak_count = 1;
            }
        });

        static::saving(function ($streak) {
            // Auto-calculate reward amount if not set
            if (!$streak->reward_amount && $streak->delivery_count > 0) {
                $streak->reward_amount = $streak->calculateRewardAmount();
            }
        });
    }
}
