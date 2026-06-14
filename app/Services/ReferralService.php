<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Transaction;
use App\Events\ReferralRewarded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * ReferralService
 * 
 * Handles referral reward operations including:
 * - Processing first delivery rewards
 * - Awarding points and cash to referrers
 * - Tracking referral usage
 * - Preventing duplicate rewards
 */
class ReferralService
{
    // =========================================================================
    // CONSTANTS
    // =========================================================================

    private const CACHE_PREFIX_WALLET = 'wallet:';
    private const CACHE_PREFIX_REFERRAL = 'referral:';
    private const LOCK_SECONDS = 10;

    // =========================================================================
    // PROPERTIES
    // =========================================================================

    protected float $rewardCash;
    protected int $rewardPoints;

    // =========================================================================
    // CONSTRUCTOR
    // =========================================================================

    public function __construct()
    {
        $this->rewardCash = config('referral.reward_amount', 5.00);
        $this->rewardPoints = config('referral.reward_points', 2);
    }

    // =========================================================================
    // MAIN PUBLIC METHODS
    // =========================================================================

    /**
     * Process referral reward for a user's first delivered order
     * 
     * @throws RuntimeException
     */
    public function processFirstDeliveryReward(Order $order): bool
    {
        $customer = $order->customer;

        // Validate referral eligibility
        if (!$this->isEligibleForReward($customer)) {
            Log::debug('Customer not eligible for referral reward', [
                'customer_id' => $customer->id,
                'referred_by' => $customer->referred_by,
                'referral_rewarded' => $customer->referral_rewarded,
            ]);
            return false;
        }

        // Ensure this is the first delivered order
        if (!$this->isFirstDeliveredOrder($customer)) {
            Log::debug('Not first delivered order', ['customer_id' => $customer->id]);
            return false;
        }

        // Prevent duplicate processing with cache lock
        $lock = $this->acquireLock($customer->id);

        if (!$lock) {
            Log::warning('Referral reward already processing', ['customer_id' => $customer->id]);
            return false;
        }

        try {
            return DB::transaction(function () use ($customer, $order) {
                return $this->handleReward($customer, $order);
            });
        } catch (\Exception $e) {
            Log::error('Referral reward failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new RuntimeException('Failed to process referral reward: ' . $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    /**
     * Get total referral earnings for a user
     */
    public function getTotalReferralEarnings(Customer $customer): float
    {
        return (float) Transaction::where('user_id', $customer->id)
            ->where('purpose', 'referral_reward')
            ->where('status', 'success')
            ->sum('amount');
    }

    /**
     * Get total referral points earned by a user
     */
    public function getTotalReferralPoints(Customer $customer): int
    {
        return (int) Transaction::where('user_id', $customer->id)
            ->where('purpose', 'referral_reward')
            ->where('status', 'success')
            ->sum(DB::raw("JSON_EXTRACT(metadata, '$.points_awarded')"));
    }

    /**
     * Get all referred users for a referrer
     */
    public function getReferredUsers(Customer $referrer): \Illuminate\Database\Eloquent\Collection
    {
        return Customer::where('referred_by', $referrer->referral_code)
            ->where('referral_rewarded', true)
            ->get(['id', 'first_name', 'last_name', 'email', 'created_at']);
    }

    /**
     * Get referral statistics for a user
     */
    public function getReferralStats(Customer $customer): array
    {
        $totalReferred = Customer::where('referred_by', $customer->referral_code)->count();
        $rewardedReferred = Customer::where('referred_by', $customer->referral_code)
            ->where('referral_rewarded', true)
            ->count();

        $totalEarnings = $this->getTotalReferralEarnings($customer);
        $totalPoints = $this->getTotalReferralPoints($customer);

        return [
            'referral_code' => $customer->referral_code,
            'total_referred' => $totalReferred,
            'rewarded_referred' => $rewardedReferred,
            'pending_referred' => $totalReferred - $rewardedReferred,
            'total_earnings' => $totalEarnings,
            'formatted_earnings' => '₦' . number_format($totalEarnings, 2),
            'total_points' => $totalPoints,
            'reward_cash' => $this->rewardCash,
            'reward_points' => $this->rewardPoints,
        ];
    }

    /**
     * Check if a user has received referral reward
     */
    public function hasReceivedReward(Customer $customer): bool
    {
        return (bool) $customer->referral_rewarded;
    }

    /**
     * Get current reward cash amount
     */
    public function getRewardCash(): float
    {
        return $this->rewardCash;
    }

    /**
     * Get current reward points amount
     */
    public function getRewardPoints(): int
    {
        return $this->rewardPoints;
    }

    // =========================================================================
    // VALIDATION METHODS
    // =========================================================================

    /**
     * Check if customer is eligible for referral reward
     */
    private function isEligibleForReward(Customer $customer): bool
    {
        return !empty($customer->referred_by) && !$customer->referral_rewarded;
    }

    /**
     * Check if this is the customer's first delivered order
     */
    private function isFirstDeliveredOrder(Customer $customer): bool
    {
        $deliveredCount = Order::where('customer_id', $customer->id)
            ->where('status', 'delivered')
            ->count();

        return $deliveredCount === 1;
    }

    /**
     * Acquire cache lock for processing
     */
    private function acquireLock(int $customerId): ?\Illuminate\Contracts\Cache\Lock
    {
        $lockKey = $this->getLockKey($customerId);
        $lock = Cache::lock($lockKey, self::LOCK_SECONDS);

        return $lock->get() ? $lock : null;
    }

    // =========================================================================
    // REWARD HANDLING METHODS
    // =========================================================================

    /**
     * Handle reward distribution
     */
    private function handleReward(Customer $customer, Order $order): bool
    {
        // Find the referrer
        $referrer = $this->findReferrer($customer->referred_by);

        if (!$referrer) {
            Log::error('Referrer not found', ['referral_code' => $customer->referred_by]);
            return false;
        }

        // Get wallet balance before reward
        $walletBefore = $referrer->wallet_balance ?? 0;

        // Award rewards to referrer
        $this->awardReferrerRewards($referrer);

        // Mark customer as rewarded
        $this->markCustomerAsRewarded($customer);

        // Create transaction record
        $this->createTransactionRecord($referrer, $customer, $order, $walletBefore);

        // Clear cached data
        $this->clearReferrerCache($referrer);

        // Dispatch event for real-time notifications
        $this->dispatchRewardEvent($referrer, $customer);

        // Log successful reward
        $this->logRewardSuccess($referrer, $customer);

        return true;
    }

    /**
     * Find referrer by referral code
     */
    private function findReferrer(string $referralCode): ?Customer
    {
        return Customer::where('referral_code', $referralCode)->first();
    }

    /**
     * Award points and cash to referrer
     */
    private function awardReferrerRewards(Customer $referrer): void
    {
        $referrer->increment('points_balance', $this->rewardPoints);
        $referrer->increment('wallet_balance', $this->rewardCash);
    }

    /**
     * Mark customer as rewarded
     */
    private function markCustomerAsRewarded(Customer $customer): void
    {
        $customer->referral_rewarded = true;
        $customer->save();
    }

    /**
     * Create transaction record for the reward
     */
    private function createTransactionRecord(
        Customer $referrer,
        Customer $customer,
        Order $order,
        float $walletBefore
    ): void {
        Transaction::create([
            'user_id' => $referrer->id,
            'type' => 'credit',
            'purpose' => 'referral_reward',
            'amount' => $this->rewardCash,
            'reference' => $this->generateTransactionReference(),
            'balance_before' => $walletBefore,
            'balance_after' => $walletBefore + $this->rewardCash,
            'status' => 'success',
            'metadata' => [
                'referred_user_id' => $customer->id,
                'referred_user_email' => $customer->email,
                'order_id' => $order->id,
                'points_awarded' => $this->rewardPoints,
            ],
        ]);
    }

    /**
     * Generate unique transaction reference
     */
    private function generateTransactionReference(): string
    {
        return 'REF_' . strtoupper(Str::random(16));
    }

    /**
     * Clear cached data for the referrer
     */
    private function clearReferrerCache(Customer $referrer): void
    {
        Cache::forget(self::CACHE_PREFIX_WALLET . $referrer->id);
        Cache::forget(self::CACHE_PREFIX_REFERRAL . $referrer->id);
    }

    /**
     * Dispatch the referral rewarded event
     */
    private function dispatchRewardEvent(Customer $referrer, Customer $customer): void
    {
        $this->ensureUserModelAlias();

        /** @var \App\Models\User $referrerAsUser */
        $referrerAsUser = $referrer;

        /** @var \App\Models\User $referredAsUser */
        $referredAsUser = $customer;

        event(new ReferralRewarded(
            $referrerAsUser,
            $referredAsUser,
            $this->rewardCash,
            $this->rewardPoints
        ));
    }

    /**
     * Ensure App\Models\User is available for event payloads
     */
    private function ensureUserModelAlias(): void
    {
        if (!class_exists(\App\Models\User::class, false)) {
            class_alias(Customer::class, \App\Models\User::class);
        }
    }

    /**
     * Log successful reward
     */
    private function logRewardSuccess(Customer $referrer, Customer $customer): void
    {
        Log::info('Referral reward granted successfully', [
            'referrer_id' => $referrer->id,
            'referrer_email' => $referrer->email,
            'referred_id' => $customer->id,
            'referred_email' => $customer->email,
            'amount' => $this->rewardCash,
            'points' => $this->rewardPoints,
            'order_id' => $customer->orders()->latest()->first()?->id,
        ]);
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Get lock key for customer
     */
    private function getLockKey(int $customerId): string
    {
        return "referral:first_delivery:{$customerId}";
    }

    /**
     * Generate a unique referral code
     */
    public function generateReferralCode(int $length = 8): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while (Customer::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Validate if a referral code is valid
     */
    public function isReferralCodeValid(string $code): bool
    {
        return Customer::where('referral_code', $code)->exists();
    }

    /**
     * Get referrer by referral code
     */
    public function getReferrerByCode(string $code): ?Customer
    {
        return Customer::where('referral_code', $code)->first();
    }
}
