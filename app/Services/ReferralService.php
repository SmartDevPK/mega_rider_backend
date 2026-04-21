<?php
// app/Services/ReferralService.php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReferralService
{
    protected float $rewardCash;
    protected int $rewardPoints;

    public function __construct()
    {
        $this->rewardCash = config('referral.reward_amount', 5.00); // $5 default
        $this->rewardPoints = config('referral.reward_points', 2);
    }

    /**
     * Process referral reward for a user's first delivered order.
     * Returns true if reward was given, false otherwise.
     */
    public function processFirstDeliveryReward(Order $order): bool
    {
        $customer = $order->customer;

        // No referral code or already rewarded
        if (empty($customer->referred_by) || $customer->referral_rewarded) {
            return false;
        }

        // Verify this is truly the first delivered order
        $deliveredCount = Order::where('customer_id', $customer->id)
            ->where('status', 'delivered')
            ->count();

        if ($deliveredCount > 1) {
            return false;
        }

        // Idempotency lock to prevent double processing
        $lockKey = "referral:first_delivery:{$customer->id}";
        $lock = Cache::lock($lockKey, 10); // 10 seconds TTL

        if (!$lock->get()) {
            Log::warning("Referral reward already processing for customer {$customer->id}");
            return false;
        }

        try {
            return DB::transaction(function () use ($customer, $lock) {
                $referrer = User::where('referral_code', $customer->referred_by)->first();

                if (!$referrer) {
                    Log::error("Referrer not found for code {$customer->referred_by}");
                    return false;
                }

                $walletBefore = $referrer->wallet_balance ?? 0;

                // Award points and cash
                $referrer->increment('point_balance', $this->rewardPoints);
                $referrer->increment('wallet_balance', $this->rewardCash);

                // Mark referred user as rewarded
                $customer->referral_rewarded = true;
                $customer->save();

                // Create transaction record
                Transaction::create([
                    'user_id' => $referrer->id,
                    'type' => 'credit',
                    'purpose' => 'Referral First Delivery Reward',
                    'amount' => $this->rewardCash,
                    'reference' => Str::uuid(),
                    'balance_before' => $walletBefore,
                    'balance_after' => $walletBefore + $this->rewardCash,
                    'status' => 'success',
                    'metadata' => json_encode([
                        'referred_user_id' => $customer->id,
                        'order_id' => $order->id,
                        'points_awarded' => $this->rewardPoints,
                    ]),
                ]);

                // Update cache (if you use Redis for wallet)
                Cache::forget("wallet:{$referrer->id}");
                Cache::forget("referral:{$referrer->id}");

                // Dispatch real-time notification (using Laravel Echo or WebSocket)
                event(new ReferralRewarded($referrer, $customer, $this->rewardCash, $this->rewardPoints));

                Log::info("Referral reward granted", [
                    'referrer_id' => $referrer->id,
                    'referred_id' => $customer->id,
                    'amount' => $this->rewardCash,
                    'points' => $this->rewardPoints,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error("Referral reward failed: " . $e->getMessage(), [
                'customer_id' => $customer->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        } finally {
            $lock->release();
        }
    }
}