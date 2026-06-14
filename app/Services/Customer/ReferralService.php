<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Events\ReferralRewarded;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;

class ReferralService
{
  protected float $rewardCash;
  protected int $rewardPoints;

  public function __construct()
  {
    $this->rewardCash = config('referral.reward_amount', 5.00);
    $this->rewardPoints = config('referral.reward_points', 2);
  }

  public function processFirstDeliveryReward(Order $order): bool
  {
    $customer = $order->customer;

    if (!$this->isEligibleForReward($customer)) {
      return false;
    }

    if (!$this->isFirstDeliveredOrder($customer)) {
      return false;
    }

    $lock = $this->acquireProcessLock($customer->id);

    if (!$lock) {
      Log::warning("Referral reward already processing", ['customer_id' => $customer->id]);
      return false;
    }

    try {
      return DB::transaction(function () use ($customer, $order, $lock) {
        $referrer = $this->findReferrer($customer->referred_by);

        if (!$referrer) {
          Log::error("Referrer not found", ['referral_code' => $customer->referred_by]);
          return false;
        }

        $this->awardReferrerRewards($referrer);
        $this->markCustomerAsRewarded($customer);
        $this->createTransactionRecord($referrer, $customer, $order);
        $this->clearReferrerCache($referrer);
        $this->dispatchRewardEvent($referrer, $customer);

        Log::info("Referral reward granted", [
          'referrer_id' => $referrer->id,
          'referred_id' => $customer->id,
          'amount' => $this->rewardCash,
        ]);

        return true;
      });
    } catch (\Exception $e) {
      Log::error("Referral reward failed", [
        'customer_id' => $customer->id,
        'error' => $e->getMessage(),
      ]);
      return false;
    } finally {
      $lock->release();
    }
  }

  protected function isEligibleForReward(Customer $customer): bool
  {
    return !empty($customer->referred_by) && !$customer->referral_rewarded;
  }

  protected function isFirstDeliveredOrder(Customer $customer): bool
  {
    return Order::where('customer_id', $customer->id)
      ->where('status', 'delivered')
      ->count() === 1;
  }

  protected function acquireProcessLock(int $customerId): ?\Illuminate\Cache\Lock
  {
    $lockKey = "referral:first_delivery:{$customerId}";
    $lock = Cache::lock($lockKey, 10);

    return $lock->get() ? $lock : null;
  }

  protected function findReferrer(string $referralCode): ?Customer
  {
    return Customer::where('referral_code', $referralCode)->first();
  }

  protected function awardReferrerRewards(Customer $referrer): void
  {
    $referrer->increment('points_balance', $this->rewardPoints);
    $referrer->increment('wallet_balance', $this->rewardCash);
  }

  protected function markCustomerAsRewarded(Customer $customer): void
  {
    $customer->referral_rewarded = true;
    $customer->save();
  }

  protected function createTransactionRecord(
    Customer $referrer,
    Customer $customer,
    Order $order
  ): void {
    Transaction::create([
      'user_id' => $referrer->id,
      'type' => 'credit',
      'purpose' => 'referral_reward',
      'amount' => $this->rewardCash,
      'reference' => Str::uuid(),
      'status' => 'completed',
      'metadata' => [
        'referred_user_id' => $customer->id,
        'order_id' => $order->id,
        'points_awarded' => $this->rewardPoints,
      ],
    ]);
  }

  protected function clearReferrerCache(Customer $referrer): void
  {
    Cache::forget("wallet:{$referrer->id}");
    Cache::forget("referral:{$referrer->id}");
  }

  protected function dispatchRewardEvent(Customer $referrer, Customer $customer): void
  {
    // Ensure we pass App\Models\User instances to the event (some listeners/type hints expect User)
    $referrerUser = Customer::find($referrer->id) ?? $referrer;
    $customerUser = Customer::find($customer->id) ?? $customer;

    event(new ReferralRewarded($referrerUser, $customerUser, $this->rewardCash, $this->rewardPoints));
  }
}
