<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerDailyStreak;
use App\Models\CustomerReward;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * StreakService
 * 
 * Handles customer daily streak operations including:
 * - Tracking daily delivery counts
 * - Managing streak rewards
 * - Preventing duplicate reward claims
 * - Calculating streak bonuses
 */
class StreakService
{
    // =========================================================================
    // CONSTANTS
    // =========================================================================

    private const STREAK_REWARD_THRESHOLD = 5;
    private const DEFAULT_REWARD_AMOUNT = 100.00;
    private const STREAK_BONUS_MULTIPLIER = 10; // Additional ₦10 per streak day
    private const MAX_STREAK_BONUS = 500.00;
    private const CACHE_TTL_SECONDS = 3600;
    private const CACHE_PREFIX_STREAK = 'streak:';

    // =========================================================================
    // MAIN PUBLIC METHODS
    // =========================================================================

    /**
     * Update or create today's streak record for a customer
     * 
     * @param int $customerId
     * @param int $deliveryCount (default 1, increments by 1)
     * @return CustomerDailyStreak
     */
    public function updateStreak(int $customerId, int $deliveryCount = 1): CustomerDailyStreak
    {
        $today = Carbon::today();

        return DB::transaction(function () use ($customerId, $today, $deliveryCount) {
            // Get or create today's streak record with lock
            $streak = CustomerDailyStreak::where('customer_id', $customerId)
                ->where('date', $today)
                ->lockForUpdate()
                ->first();

            if ($streak) {
                // Increment delivery count
                $streak->increment('delivery_count', $deliveryCount);
                $streak->refresh();
            } else {
                // Create new streak record
                $streak = CustomerDailyStreak::create([
                    'customer_id' => $customerId,
                    'date' => $today,
                    'delivery_count' => $deliveryCount,
                    'streak_count' => $this->calculateStreakCount($customerId),
                    'reward_claimed' => false,
                ]);
            }

            // Update streak count based on consecutive days
            $this->updateStreakCount($streak);

            Log::info('Streak updated', [
                'customer_id' => $customerId,
                'date' => $today->toDateString(),
                'delivery_count' => $streak->delivery_count,
                'streak_count' => $streak->streak_count,
            ]);

            return $streak;
        });
    }

    /**
     * Increment streak by 1 (convenience method)
     */
    public function incrementStreak(int $customerId): CustomerDailyStreak
    {
        return $this->updateStreak($customerId, 1);
    }

    /**
     * Check if customer has reached reward threshold and grant reward if eligible
     * 
     * @return CustomerReward|null
     */
    public function checkAndReward(int $customerId): ?CustomerReward
    {
        $today = Carbon::today();

        // Use cache to prevent repeated checks
        $cacheKey = $this->getRewardCheckCacheKey($customerId, $today);

        if (Cache::has($cacheKey)) {
            Log::debug('Reward check already performed today', ['customer_id' => $customerId]);
            return null;
        }

        return DB::transaction(function () use ($customerId, $today, $cacheKey) {
            // Lock the streak record to prevent race conditions
            $streak = CustomerDailyStreak::where('customer_id', $customerId)
                ->where('date', $today)
                ->lockForUpdate()
                ->first();

            if (!$streak) {
                $this->setRewardCheckCache($cacheKey);
                return null;
            }

            // Already claimed
            if ($streak->reward_claimed) {
                $this->setRewardCheckCache($cacheKey);
                Log::info('Reward already claimed', [
                    'customer_id' => $customerId,
                    'date' => $today->toDateString(),
                ]);
                return null;
            }

            // Check if threshold reached
            if ($streak->delivery_count >= self::STREAK_REWARD_THRESHOLD) {
                $rewardAmount = $this->calculateRewardAmount($streak);

                $reward = $this->grantReward($streak, $rewardAmount);

                $this->setRewardCheckCache($cacheKey);

                Log::info('Streak reward granted', [
                    'customer_id' => $customerId,
                    'date' => $today->toDateString(),
                    'delivery_count' => $streak->delivery_count,
                    'streak_count' => $streak->streak_count,
                    'reward_amount' => $rewardAmount,
                    'reward_id' => $reward->id,
                ]);

                return $reward;
            }

            $this->setRewardCheckCache($cacheKey);

            Log::debug('Threshold not reached', [
                'customer_id' => $customerId,
                'current' => $streak->delivery_count,
                'required' => self::STREAK_REWARD_THRESHOLD,
            ]);

            return null;
        });
    }

    /**
     * Get current streak information for a customer
     */
    public function getStreakInfo(int $customerId): array
    {
        $today = Carbon::today();
        $currentStreak = CustomerDailyStreak::where('customer_id', $customerId)
            ->where('date', $today)
            ->first();

        $yesterdayStreak = CustomerDailyStreak::where('customer_id', $customerId)
            ->where('date', Carbon::yesterday())
            ->first();

        $isActiveToday = $currentStreak && $currentStreak->delivery_count > 0;
        $hasStreak = $isActiveToday || ($yesterdayStreak && $yesterdayStreak->delivery_count > 0);

        $streakCount = $currentStreak?->streak_count ?? 0;
        $deliveryCount = $currentStreak?->delivery_count ?? 0;
        $remainingForReward = max(0, self::STREAK_REWARD_THRESHOLD - $deliveryCount);

        $nextRewardAmount = $this->calculatePotentialReward($streakCount + 1);

        return [
            'is_active_today' => $isActiveToday,
            'has_active_streak' => $hasStreak,
            'current_streak_days' => $streakCount,
            'today_deliveries' => $deliveryCount,
            'reward_threshold' => self::STREAK_REWARD_THRESHOLD,
            'remaining_for_reward' => $remainingForReward,
            'reward_claimed_today' => $currentStreak?->reward_claimed ?? false,
            'next_reward_amount' => $nextRewardAmount,
            'progress_percentage' => min(100, round(($deliveryCount / self::STREAK_REWARD_THRESHOLD) * 100)),
        ];
    }

    /**
     * Get streak history for a customer
     */
    public function getStreakHistory(int $customerId, int $days = 30): array
    {
        $streaks = CustomerDailyStreak::where('customer_id', $customerId)
            ->where('date', '>=', Carbon::now()->subDays($days))
            ->orderBy('date', 'desc')
            ->get();

        $history = [];
        $currentDate = Carbon::today();

        for ($i = 0; $i < $days; $i++) {
            $date = $currentDate->copy()->subDays($i);
            $streak = $streaks->firstWhere('date', $date->toDateString());

            $history[] = [
                'date' => $date->toDateString(),
                'day_name' => $date->format('D'),
                'deliveries' => $streak?->delivery_count ?? 0,
                'reward_claimed' => $streak?->reward_claimed ?? false,
                'is_active' => $streak && $streak->delivery_count > 0,
            ];
        }

        return $history;
    }

    /**
     * Get total rewards earned by customer
     */
    public function getTotalRewardsEarned(int $customerId): float
    {
        return (float) CustomerReward::where('customer_id', $customerId)
            ->where('type', 'streak_bonus')
            ->sum('amount');
    }

    /**
     * Get streak statistics for dashboard
     */
    public function getStatistics(): array
    {
        $today = Carbon::today();

        $todayStreaks = CustomerDailyStreak::where('date', $today)
            ->where('delivery_count', '>', 0)
            ->count();

        $rewardedToday = CustomerDailyStreak::where('date', $today)
            ->where('reward_claimed', true)
            ->count();

        $averageDailyDeliveries = CustomerDailyStreak::where('date', $today)
            ->avg('delivery_count') ?? 0;

        $longestStreak = CustomerDailyStreak::max('streak_count') ?? 0;

        $totalRewardsPaid = CustomerReward::where('type', 'streak_bonus')
            ->sum('amount');

        return [
            'active_streaks_today' => $todayStreaks,
            'rewards_claimed_today' => $rewardedToday,
            'average_daily_deliveries' => round($averageDailyDeliveries, 2),
            'longest_streak_all_time' => $longestStreak,
            'total_rewards_paid' => $totalRewardsPaid,
            'formatted_total_rewards' => '₦' . number_format($totalRewardsPaid, 2),
        ];
    }

    // =========================================================================
    // STREAK CALCULATION METHODS
    // =========================================================================

    /**
     * Calculate streak count based on consecutive days
     */
    private function calculateStreakCount(int $customerId): int
    {
        $yesterday = Carbon::yesterday();
        $yesterdayStreak = CustomerDailyStreak::where('customer_id', $customerId)
            ->where('date', $yesterday)
            ->first();

        if ($yesterdayStreak && $yesterdayStreak->delivery_count > 0) {
            return $yesterdayStreak->streak_count + 1;
        }

        return 1;
    }

    /**
     * Update streak count for a streak record
     */
    private function updateStreakCount(CustomerDailyStreak $streak): void
    {
        $yesterday = Carbon::yesterday();
        $yesterdayStreak = CustomerDailyStreak::where('customer_id', $streak->customer_id)
            ->where('date', $yesterday)
            ->first();

        if ($yesterdayStreak && $yesterdayStreak->delivery_count > 0) {
            $streak->streak_count = $yesterdayStreak->streak_count + 1;
        } else {
            $streak->streak_count = 1;
        }

        $streak->save();
    }

    /**
     * Calculate reward amount based on streak count
     */
    private function calculateRewardAmount(CustomerDailyStreak $streak): float
    {
        $baseReward = self::DEFAULT_REWARD_AMOUNT;
        $bonus = ($streak->streak_count - 1) * self::STREAK_BONUS_MULTIPLIER;

        $totalReward = $baseReward + $bonus;

        return min($totalReward, self::MAX_STREAK_BONUS);
    }

    /**
     * Calculate potential reward for a streak day
     */
    private function calculatePotentialReward(int $streakCount): float
    {
        $baseReward = self::DEFAULT_REWARD_AMOUNT;
        $bonus = ($streakCount - 1) * self::STREAK_BONUS_MULTIPLIER;

        $totalReward = $baseReward + $bonus;

        return min($totalReward, self::MAX_STREAK_BONUS);
    }

    // =========================================================================
    // REWARD GRANTING METHODS
    // =========================================================================

    /**
     * Grant reward to customer
     */
    private function grantReward(CustomerDailyStreak $streak, float $amount): CustomerReward
    {
        // Create reward record
        $reward = CustomerReward::create([
            'customer_id' => $streak->customer_id,
            'type' => 'streak_bonus',
            'reference_date' => $streak->date,
            'amount' => $amount,
            'metadata' => [
                'delivery_count' => $streak->delivery_count,
                'streak_count' => $streak->streak_count,
            ],
        ]);

        // Mark streak as claimed
        $streak->update([
            'reward_claimed' => true,
            'reward_amount' => $amount,
            'reward_claimed_at' => now(),
        ]);

        // Add reward to customer's wallet
        $this->addRewardToWallet($streak->customer_id, $amount, $reward->id);

        return $reward;
    }

    /**
     * Add reward amount to customer's wallet
     */
    private function addRewardToWallet(int $customerId, float $amount, int $rewardId): void
    {
        $customer = \App\Models\Customer::find($customerId);

        if ($customer) {
            // Add to wallet balance
            $customer->increment('wallet_balance', $amount);

            // Create transaction record
            $customer->transactions()->create([
                'type' => 'credit',
                'purpose' => 'streak_reward',
                'amount' => $amount,
                'reference' => 'STREAK_' . $rewardId . '_' . time(),
                'balance_before' => $customer->wallet_balance - $amount,
                'balance_after' => $customer->wallet_balance,
                'status' => 'success',
                'metadata' => [
                    'streak_reward_id' => $rewardId,
                ],
            ]);
        }
    }

    // =========================================================================
    // CACHE METHODS
    // =========================================================================

    /**
     * Get reward check cache key
     */
    private function getRewardCheckCacheKey(int $customerId, Carbon $date): string
    {
        return self::CACHE_PREFIX_STREAK . "reward_check:{$customerId}:{$date->toDateString()}";
    }

    /**
     * Set reward check cache to prevent duplicate checks
     */
    private function setRewardCheckCache(string $cacheKey): void
    {
        Cache::put($cacheKey, true, self::CACHE_TTL_SECONDS);
    }

    /**
     * Clear streak cache for a customer
     */
    public function clearCache(int $customerId): void
    {
        $today = Carbon::today();
        Cache::forget($this->getRewardCheckCacheKey($customerId, $today));

        Log::debug('Streak cache cleared', ['customer_id' => $customerId]);
    }

    // =========================================================================
    // ADMIN METHODS
    // =========================================================================

    /**
     * Manually grant streak reward (admin function)
     */
    public function manuallyGrantReward(int $customerId, string $date, float $amount, string $reason = 'Manual grant'): ?CustomerReward
    {
        $dateObj = Carbon::parse($date);

        return DB::transaction(function () use ($customerId, $dateObj, $amount, $reason) {
            $streak = CustomerDailyStreak::where('customer_id', $customerId)
                ->where('date', $dateObj)
                ->first();

            if (!$streak) {
                $streak = CustomerDailyStreak::create([
                    'customer_id' => $customerId,
                    'date' => $dateObj,
                    'delivery_count' => self::STREAK_REWARD_THRESHOLD,
                    'streak_count' => 1,
                    'reward_claimed' => false,
                ]);
            }

            if ($streak->reward_claimed) {
                throw ValidationException::withMessages([
                    'customer_id' => ['Reward already claimed for this date.'],
                ]);
            }

            $reward = CustomerReward::create([
                'customer_id' => $customerId,
                'type' => 'streak_bonus',
                'reference_date' => $dateObj,
                'amount' => $amount,
                'metadata' => [
                    'manual_grant' => true,
                    'reason' => $reason,
                    'granted_by' => auth("santum")->id(),
                ],
            ]);

            $streak->update([
                'reward_claimed' => true,
                'reward_amount' => $amount,
                'reward_claimed_at' => now(),
            ]);

            $this->addRewardToWallet($customerId, $amount, $reward->id);

            Log::info('Manual reward granted', [
                'customer_id' => $customerId,
                'amount' => $amount,
                'reason' => $reason,
            ]);

            return $reward;
        });
    }

    /**
     * Reset streak for a customer (admin function)
     */
    public function resetStreak(int $customerId, string $reason = 'Admin reset'): bool
    {
        return DB::transaction(function () use ($customerId, $reason) {
            $today = Carbon::today();

            $streak = CustomerDailyStreak::where('customer_id', $customerId)
                ->where('date', $today)
                ->first();

            if ($streak) {
                $streak->update([
                    'delivery_count' => 0,
                    'streak_count' => 0,
                ]);
            }

            $this->clearCache($customerId);

            Log::info('Streak reset', [
                'customer_id' => $customerId,
                'reason' => $reason,
                'admin_id' => auth("santum")->id(),
            ]);

            return true;
        });
    }
}
