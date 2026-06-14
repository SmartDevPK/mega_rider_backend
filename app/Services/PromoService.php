<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\PromoCampaign;
use App\Models\PromoUsage;
use App\Exceptions\PromoException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PromoService
 * 
 * Handles promo code operations including:
 * - Applying promo codes to orders
 * - Calculating discounts
 * - Tracking promo usage
 * - Preventing abuse
 */
class PromoService
{
    // =========================================================================
    // CONSTANTS
    // =========================================================================

    private const DEFAULT_PROCESSOR_FEE = 50;
    private const MAX_USAGE_PER_USER = 1;
    private const MIN_ORDER_AMOUNT = 100;
    private const MAX_DISCOUNT_PERCENTAGE = 90; // Max 90% discount

    // =========================================================================
    // MAIN METHODS
    // =========================================================================

    /**
     * Apply promo to an order
     * 
     * @throws PromoException
     */
    public function applyPromo(Order $order, string $code, ?int $userId = null): array
    {
        // Get valid campaign
        $campaign = $this->getValidCampaign($code);
        
        if (!$campaign) {
            throw new PromoException( 'Invalid or expired promo code.', 404);
        }
        
        // Check if promo is already used on this order
        if ($this->isPromoAlreadyUsedOnOrder($order, $campaign)) {
            throw new PromoException( 'This promo code has already been applied to this order.', 400);
        }
        
        // Check if user has reached usage limit
        if ($userId && $this->hasUserReachedLimit($userId, $campaign)) {
            throw new PromoException( 'You have reached the maximum usage limit for this promo.', 400);
        }
        
        // Get base amount for discount calculation
        $baseAmount = $this->getBaseAmount($order);
        
        if ($baseAmount <= 0) {
            throw new PromoException( 'Cannot apply promo to unpriced order.', 422);
        }
        
        // Check minimum order value
        if ($baseAmount < self::MIN_ORDER_AMOUNT) {
            throw new PromoException('Minimum order amount of ₦" . number_format(self::MIN_ORDER_AMOUNT, 2) . " required.', 422);
        }
        
        // Calculate discount
        $discount = $this->calculateDiscount($baseAmount, $campaign->discount_percentage, $campaign->max_discount_amount);
        
        if ($discount <= 0) {
            throw new PromoException( 'Unable to calculate discount for this promo.', 422);
        }
        
        // Process in transaction
        return DB::transaction(function () use ($order, $campaign, $discount, $userId) {
            // Lock campaign for update (prevent race conditions)
            $campaign = PromoCampaign::where('id', $campaign->id)
                ->lockForUpdate()
                ->firstOrFail();
            
            // Check campaign balance
            if ($campaign->balance < $discount) {
                throw new PromoException( 'Promo budget has been exhausted.', 429);
            }
            
            // Deduct promo balance
            $campaign->decrement('balance', $discount);
            
            // Update order with discount
            $order->update([
                'discount_amount' => $discount,
                'promo_campaign_id' => $campaign->id,
            ]);
            
            // Record usage
            PromoUsage::create([
                'order_id' => $order->id,
                'promo_campaign_id' => $campaign->id,
                'user_id' => $userId,
                'discount_amount' => $discount,
                'code' => $campaign->code,
                'applied_at' => now(),
            ]);
            
            Log::info('Promo applied successfully', [
                'order_id' => $order->id,
                'promo_code' => $campaign->code,
                'discount' => $discount,
                'user_id' => $userId,
            ]);
            
            return $this->recalculateOrderTotals($order->fresh());
        });
    }

    /**
     * Remove promo from an order
     */
    public function removePromo(Order $order): array
    {
        if (!$order->promo_campaign_id || !$order->discount_amount) {
            throw new PromoException( 'No promo code is applied to this order.', 400);
        }
        
        return DB::transaction(function () use ($order) {
            // Get the campaign
            $campaign = PromoCampaign::find($order->promo_campaign_id);
            
            if ($campaign) {
                // Return balance to campaign
                $campaign->increment('balance', $order->discount_amount);
            }
            
            // Remove discount from order
            $order->update([
                'discount_amount' => null,
                'promo_campaign_id' => null,
            ]);
            
            // Delete usage record (optional, or mark as removed)
            PromoUsage::where('order_id', $order->id)->delete();
            
            Log::info('Promo removed successfully', [
                'order_id' => $order->id,
                'promo_code' => $campaign?->code,
            ]);
            
            return $this->recalculateOrderTotals($order->fresh());
        });
    }

    /**
     * Validate promo code without applying
     */
    public function validatePromo(string $code, ?int $userId = null): array
    {
        $campaign = $this->getValidCampaign($code);
        
        if (!$campaign) {
            return [
                'valid' => false,
                'message' => 'Invalid or expired promo code.',
                'code' => 'PROMO_NOT_FOUND',
            ];
        }
        
        if ($userId && $this->hasUserReachedLimit($userId, $campaign)) {
            return [
                'valid' => false,
                'message' => 'You have reached the maximum usage limit for this promo.',
                'code' => 'PROMO_LIMIT_REACHED',
            ];
        }
        
        return [
            'valid' => true,
            'campaign' => [
                'code' => $campaign->code,
                'discount_percentage' => $campaign->discount_percentage,
                'max_discount_amount' => $campaign->max_discount_amount,
                'description' => $campaign->description,
            ],
        ];
    }

    // =========================================================================
    // VALIDATION METHODS
    // =========================================================================

    /**
     * Get valid promo campaign
     */
    private function getValidCampaign(string $code): ?PromoCampaign
    {
        return PromoCampaign::where('code', $code)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where('balance', '>', 0)
            ->first();
    }

    /**
     * Check if promo is already used on this order
     */
    private function isPromoAlreadyUsedOnOrder(Order $order, PromoCampaign $campaign): bool
    {
        return PromoUsage::where('order_id', $order->id)
            ->where('promo_campaign_id', $campaign->id)
            ->exists();
    }

    /**
     * Check if user has reached usage limit for a campaign
     */
    private function hasUserReachedLimit(int $userId, PromoCampaign $campaign): bool
    {
        $usageCount = PromoUsage::where('user_id', $userId)
            ->where('promo_campaign_id', $campaign->id)
            ->count();
        
        $limit = $campaign->max_usage_per_user ?? self::MAX_USAGE_PER_USER;
        
        return $usageCount >= $limit;
    }

    // =========================================================================
    // CALCULATION METHODS
    // =========================================================================

    /**
     * Get base amount for discount calculation
     */
    private function getBaseAmount(Order $order): float
    {
        // Priority: delivery_fee > subtotal > total_amount
        if ($order->delivery_fee && $order->delivery_fee > 0) {
            return (float) $order->delivery_fee;
        }
        
        if ($order->price && $order->price > 0) {
            return (float) $order->price;
        }
        
        if ($order->total_amount && $order->total_amount > 0) {
            return (float) $order->total_amount;
        }
        
        return 0.00;
    }

    /**
     * Calculate discount amount
     */
    private function calculateDiscount(float $amount, float $percentage, ?float $maxDiscount = null): float
    {
        // Cap percentage at maximum
        $percentage = min($percentage, self::MAX_DISCOUNT_PERCENTAGE);
        
        // Calculate discount
        $discount = ($amount * $percentage) / 100;
        
        // Apply max discount limit
        if ($maxDiscount && $maxDiscount > 0) {
            $discount = min($discount, $maxDiscount);
        }
        
        // Ensure discount doesn't exceed amount
        $discount = min($discount, $amount);
        
        return round($discount, 2);
    }

    /**
     * Recalculate order totals
     */
    private function recalculateOrderTotals(Order $order): array
    {
        $baseAmount = $this->getBaseAmount($order);
        $discount = $order->discount_amount ?? 0;
        
        $deliveryFee = max(0, $baseAmount - $discount);
        $surgeFee = $order->surge_fee ?? 0;
        $insuranceFee = $order->insurance_fee ?? 0;
        $processorFee = self::DEFAULT_PROCESSOR_FEE;
        
        $totalFees = $deliveryFee + $surgeFee + $insuranceFee + $processorFee;
        
        return [
            'base_amount' => round($baseAmount, 2),
            'discount' => round($discount, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'surge_fee' => round($surgeFee, 2),
            'insurance_fee' => round($insuranceFee, 2),
            'payment_processor_fee' => $processorFee,
            'total_fees' => round($totalFees, 2),
            'final_amount' => round($totalFees, 2),
        ];
    }

    // =========================================================================
    // STATISTICS METHODS
    // =========================================================================

    /**
     * Get promo usage statistics
     */
    public function getPromoStats(string $code): array
    {
        $campaign = PromoCampaign::where('code', $code)->first();
        
        if (!$campaign) {
            return [
                'total_used' => 0,
                'total_discount_given' => 0,
                'remaining_balance' => 0,
            ];
        }
        
        $stats = PromoUsage::where('promo_campaign_id', $campaign->id)
            ->select(
                DB::raw('COUNT(*) as total_used'),
                DB::raw('SUM(discount_amount) as total_discount_given')
            )
            ->first();
        
        return [
            'total_used' => (int) ($stats->total_used ?? 0),
            'total_discount_given' => (float) ($stats->total_discount_given ?? 0),
            'remaining_balance' => (float) $campaign->balance,
            'initial_balance' => (float) $campaign->initial_balance,
        ];
    }
}