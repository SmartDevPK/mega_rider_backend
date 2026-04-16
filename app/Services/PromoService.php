<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PromoCampaign;
use App\Models\PromoUsage;
use App\Exceptions\PromoException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PromoService
{
    /**
     * Apply a promo code to an order.
     *
     * @throws PromoException
     */
    public function applyPromo(Order $order, string $code): array
    {
        $campaign = $this->getValidCampaign($code);

        if (! $campaign) {
            throw new PromoException('PROMO_NOT_FOUND', 404);
        }

        // Prevent duplicate usage
        if ($this->isPromoAlreadyUsed($order, $campaign)) {
            throw new PromoException('PROMO_ALREADY_USED', 400);
        }

        $deliveryFee = $order->delivery_fee ?? 0;
        $discount = ($deliveryFee * $campaign->percentage) / 100;

        if ($discount <= 0) {
            throw new PromoException('INVALID_DISCOUNT', 422);
        }

        DB::transaction(function () use ($campaign, $order, $discount) {
            $campaign = PromoCampaign::where('id', $campaign->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($campaign->balance < $discount) {
                throw new PromoException('PROMO_UNAVAILABLE', 429);
            }

            // Deduct balance
            $campaign->decrement('balance', $discount);

            // Apply discount to order
            $order->update(['discount_amount' => $discount]);

            // Record usage
            PromoUsage::create([
                'order_id' => $order->id,
                'promo_campaign_id' => $campaign->id,
                'discount_amount' => $discount,
            ]);
        });

        return $this->recalculateOrderTotals($order);
    }

    /**
     * Get a valid, active promo campaign.
     */
    private function getValidCampaign(string $code): ?PromoCampaign
    {
        return PromoCampaign::where('code', $code)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();
    }

    /**
     * Check if a promo has already been used for this order.
     */
    private function isPromoAlreadyUsed(Order $order, PromoCampaign $campaign): bool
    {
        return PromoUsage::where('order_id', $order->id)
            ->where('promo_campaign_id', $campaign->id)
            ->exists();
    }

    /**
     * Recalculate totals after discount is applied.
     */
    private function recalculateOrderTotals(Order $order): array
    {
        $deliveryFee = max(0, ($order->delivery_fee ?? 0) - ($order->discount_amount ?? 0));
        $surgeFee = $order->surge_fee ?? 0;
        $insurance = $order->insurance_fee ?? 0;
        $processorFee = 50;

        return [
            'delivery_fee' => round($deliveryFee, 2),
            'discount' => round($order->discount_amount ?? 0, 2),
            'surge_fee' => round($surgeFee, 2),
            'insurance_fee' => round($insurance, 2),
            'payment_processor_fee' => $processorFee,
            'total_fees' => round($deliveryFee + $surgeFee + $insurance + $processorFee, 2),
        ];
    }
}