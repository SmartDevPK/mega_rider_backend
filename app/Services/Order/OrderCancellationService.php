<?php

namespace App\Services\Order;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderCancellationService
{
    public function cancel(Order $order, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $order = Order::where('id', $order->id)->lockForUpdate()->first();

            // Allow cancellation only for orders that are not yet delivered or already cancelled
            if (!in_array($order->status, ['pending', 'assigned', 'picked_up'])) {
                throw new \Exception('Order cannot be cancelled at this stage');
            }

            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->cancellation_reason = $reason;
            $order->save();

            return $order;
        });
    }
}
