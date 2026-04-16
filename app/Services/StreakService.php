<?php

namespace App\Services;

use App\Models\CustomerDailyStreak;
use App\Models\CustomerReward;
use Carbon\Carbon;

class StreakService
{
    /**
     * Update or create today's streak record for a customer.
     *
     * @param int $customerId
     * @param int $deliveryCount
     * @return CustomerDailyStreak
     */
    public function updateStreak(int $customerId, int $deliveryCount = 1): CustomerDailyStreak
    {
        $today = Carbon::today();

        $streak = CustomerDailyStreak::updateOrCreate(
            [
                'customer_id' => $customerId,
                'date' => $today,
            ],
            [
                'delivery_count' => $deliveryCount,
            ]
        );

        return $streak;
    }

    /**
     * Check if the customer has reached the reward threshold (5 deliveries today)
     * and grant the reward if not already claimed.
     *
     * @param int $customerId
     * @return CustomerReward|null
     */
   public function checkAndReward(int $customerId): ?CustomerReward
{
    $today = Carbon::today();

    $streak = CustomerDailyStreak::where('customer_id', $customerId)
        ->whereDate('date', $today)
        ->first();

    if (!$streak) return null;

    $streak->refresh();

    if ($streak->reward_claimed) {
        return null;
    }

    if ($streak->delivery_count >= 5) {

        $reward = CustomerReward::create([
            'customer_id' => $customerId,
            'type' => 'streak_bonus',
            'reference_date' => $today,
            'amount' => 100.00,
        ]);

        $streak->update([
            'reward_claimed' => true,
        ]);

        return $reward;
    }

    return null;
}

}