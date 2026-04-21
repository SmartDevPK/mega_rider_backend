<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PromoCampaign;
use App\Models\PromoUsage;
use App\Exceptions\PromoException;
use Illuminate\Support\Facades\DB;

class PromoService
{
    /**
     * Apply promo to an order
     */
    public function applyPromo(Order $order, string $code): array
    {
        $campaign = $this->getValidCampaign($code);

        if (!$campaign) {
            throw new PromoException('PROMO_NOT_FOUND', 404);
        }

        if ($this->isPromoAlreadyUsed($order, $campaign)) {
            throw new PromoException('PROMO_ALREADY_USED', 400);
        }

        // -----------------------------
        // FIX: Use correct base amount
        // -----------------------------
        $baseAmount = $this->getBaseAmount($order);

        if ($baseAmount <= 0) {
            throw new PromoException('ORDER_NOT_PRICED_YET', 422);
        }

        $discount = $this->calculateDiscount($baseAmount, $campaign->percentage);

        if ($discount <= 0) {
            throw new PromoException('INVALID_DISCOUNT', 422);
        }

        return DB::transaction(function () use ($order, $campaign, $discount) {

            $campaign = PromoCampaign::where('id', $campaign->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($campaign->balance < $discount) {
                throw new PromoException('PROMO_UNAVAILABLE', 429);
            }

            // Deduct promo balance
            $campaign->decrement('balance', $discount);

            // Update order
            $order->update([
                'discount_amount' => $discount,
            ]);

            // Record usage
            PromoUsage::create([
                'order_id' => $order->id,
                'promo_campaign_id' => $campaign->id,
                'discount_amount' => $discount,
            ]);

            return $this->recalculateOrderTotals($order->fresh());
        });
    }

    /**
     * Get valid promo campaign
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
     * Prevent duplicate usage
     */
    private function isPromoAlreadyUsed(Order $order, PromoCampaign $campaign): bool
    {
        return PromoUsage::where('order_id', $order->id)
            ->where('promo_campaign_id', $campaign->id)
            ->exists();
    }

    /**
     * Decide what amount promo applies to
     */
    private function getBaseAmount(Order $order): float
    {
        return (float) (
            $order->delivery_fee
            ?? $order->price
            ?? $order->total_amount
            ?? 0
        );
    }

    /**
     * Calculate discount safely
     */
    private function calculateDiscount(float $amount, float $percentage): float
    {
        return round(($amount * $percentage) / 100, 2);
    }

    /**
     * Recalculate order totals
     */
    private function recalculateOrderTotals(Order $order): array
    {
        $base = $this->getBaseAmount($order);
        $discount = $order->discount_amount ?? 0;

        $deliveryFee = max(0, $base - $discount);
        $surgeFee = $order->surge_fee ?? 0;
        $insurance = $order->insurance_fee ?? 0;
        $processorFee = 50;

        return [
            'base_amount' => round($base, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'discount' => round($discount, 2),
            'surge_fee' => round($surgeFee, 2),
            'insurance_fee' => round($insurance, 2),
            'payment_processor_fee' => $processorFee,
            'total_fees' => round($deliveryFee + $surgeFee + $insurance + $processorFee, 2),
        ];
    }
}
