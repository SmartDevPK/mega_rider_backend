<?php
namespace App\Actions\Streak;

use App\Models\Order;
use App\Models\CustomerDailyStreak;
use App\Models\CustomerReward;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateDailyStreak
{
    // $orderId is the UUID string from orders.order_id
    public function execute(int $customerId, string $orderId)
    {
        return DB::transaction(function () use ($customerId, $orderId) {

            $order = Order::where('order_id', $orderId)
                ->where('customer_id', $customerId)
                ->where('status', 'delivered')
                ->first();

            if (!$order) {
                throw new \Exception('INVALID_ORDER');
            }

            if ($order->streak_counted) {
                return [
                    'delivery_count' => 0,
                    'reward_claimed' => false,
                    'message' => 'Already counted'
                ];
            }

            $today = Carbon::now('Africa/Lagos')->toDateString();

            $streak = CustomerDailyStreak::firstOrCreate(
                ['customer_id' => $customerId, 'date' => $today],
                ['delivery_count' => 0, 'reward_claimed' => false]
            );

            $streak->increment('delivery_count');

            $order->update(['streak_counted' => true]);

            $seconds = Carbon::now('Africa/Lagos')
                ->endOfDay()
                ->diffInSeconds();

            Cache::put("streak:{$customerId}:{$today}", $streak->delivery_count, $seconds);

            $rewardData = null;

            if ($streak->delivery_count >= 10 && !$streak->reward_claimed) {

                $amount = 1000;

                CustomerReward::create([
                    'customer_id' => $customerId,
                    'type' => 'daily_streak',
                    'reference_date' => $today,
                    'amount' => $amount,
                    'order_id' => $order->id,      // integer primary key
                ]);

                $streak->update(['reward_claimed' => true]);

                $rewardData = [
                    'reward_claimed' => true,
                    'amount' => $amount,
                ];
            }

            return [
                'delivery_count' => $streak->delivery_count,
                'reward_claimed' => $streak->reward_claimed,
                'reward' => $rewardData,
            ];
        });
    }
}