<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * FeeStructureService
 * 
 * Handles calculation of various fees including:
 * - Payment processor fees (Paystack, Stripe, Flutterwave)
 * - Service fees
 * - Delivery fees
 * - Surge pricing
 * - Commission calculations
 */
class FeeStructureService
{
    // =========================================================================
    // CONSTANTS
    // =========================================================================

    /**
     * Paystack fee structure
     * Standard: 1.5% + ₦100
     */
    private const PAYSTACK_PERCENTAGE = 0.015;
    private const PAYSTACK_FIXED = 100;

    /**
     * Stripe fee structure (International)
     * Standard: 2.9% + $0.30
     */
    private const STRIPE_PERCENTAGE = 0.029;
    private const STRIPE_FIXED = 0.30;
    private const USD_TO_NGN = 1550; // Exchange rate

    /**
     * Flutterwave fee structure
     * Standard: 1.4% + ₦100
     */
    private const FLUTTERWAVE_PERCENTAGE = 0.014;
    private const FLUTTERWAVE_FIXED = 100;

    /**
     * Service fee structure
     */
    private const SERVICE_FEE_PERCENTAGE = 0.05; // 5%
    private const MINIMUM_SERVICE_FEE = 50;
    private const MAXIMUM_SERVICE_FEE = 2000;

    /**
     * Commission structure
     */
    private const RIDER_COMMISSION_PERCENTAGE = 0.80; // Rider gets 80%
    private const PLATFORM_COMMISSION_PERCENTAGE = 0.20; // Platform gets 20%

    /**
     * Minimum delivery fees
     */
    private const MINIMUM_DELIVERY_FEE = 200;
    private const BASE_DELIVERY_FEE = 500;
    private const FEE_PER_KM = 100;

    /**
     * Available payment gateways
     */
    private const SUPPORTED_GATEWAYS = [
        'paystack',
        'stripe',
        'flutterwave',
        'monnify',
        'interswitch',
    ];

    // =========================================================================
    // PROCESSOR FEE CALCULATIONS
    // =========================================================================

    /**
     * Calculate payment processor fee
     * 
     * @param float $subtotal Amount before processor fee
     * @param string $gateway Payment gateway ('paystack', 'stripe', 'flutterwave')
     * @param bool $inNaira Return fee in Naira (convert if needed)
     * @return float
     * @throws InvalidArgumentException
     */
    public function calculateProcessorFee(float $subtotal, string $gateway = 'paystack', bool $inNaira = true): float
    {
        $this->validateGateway($gateway);

        $fee = match ($gateway) {
            'paystack' => $this->calculatePaystackFee($subtotal),
            'stripe' => $this->calculateStripeFee($subtotal, $inNaira),
            'flutterwave' => $this->calculateFlutterwaveFee($subtotal),
            default => 0.00,
        };

        return round($fee, 2);
    }

    /**
     * Calculate Paystack fee
     * Formula: 1.5% + ₦100
     */
    private function calculatePaystackFee(float $subtotal): float
    {
        $fee = ($subtotal * self::PAYSTACK_PERCENTAGE) + self::PAYSTACK_FIXED;

        // Cap at maximum (optional - Paystack doesn't have a cap)
        // return min($fee, 2000);

        return $fee;
    }

    /**
     * Calculate Stripe fee
     * Formula: 2.9% + $0.30
     */
    private function calculateStripeFee(float $subtotal, bool $inNaira = true): float
    {
        $feeInUsd = ($subtotal * self::STRIPE_PERCENTAGE) + self::STRIPE_FIXED;

        if ($inNaira) {
            return $feeInUsd * self::USD_TO_NGN;
        }

        return $feeInUsd;
    }

    /**
     * Calculate Flutterwave fee
     * Formula: 1.4% + ₦100
     */
    private function calculateFlutterwaveFee(float $subtotal): float
    {
        return ($subtotal * self::FLUTTERWAVE_PERCENTAGE) + self::FLUTTERWAVE_FIXED;
    }

    /**
     * Validate gateway is supported
     * 
     * @throws InvalidArgumentException
     */
    private function validateGateway(string $gateway): void
    {
        if (!in_array($gateway, self::SUPPORTED_GATEWAYS)) {
            throw new InvalidArgumentException("Unsupported payment gateway: {$gateway}");
        }
    }

    // =========================================================================
    // SERVICE FEE CALCULATIONS
    // =========================================================================

    /**
     * Calculate service fee
     * 
     * @param float $subtotal Order subtotal
     * @param float $percentage Optional custom percentage
     * @return float
     */
    public function calculateServiceFee(float $subtotal, ?float $percentage = null): float
    {
        $percentage = $percentage ?? self::SERVICE_FEE_PERCENTAGE;
        $fee = $subtotal * $percentage;

        // Apply min/max limits
        $fee = max(self::MINIMUM_SERVICE_FEE, $fee);
        $fee = min(self::MAXIMUM_SERVICE_FEE, $fee);

        return round($fee, 2);
    }

    // =========================================================================
    // DELIVERY FEE CALCULATIONS
    // =========================================================================

    /**
     * Calculate delivery fee based on distance
     * 
     * @param float $distance Distance in kilometers
     * @param float|null $baseFee Optional base fee override
     * @return float
     */
    public function calculateDeliveryFee(float $distance, ?float $baseFee = null): float
    {
        $baseFee = $baseFee ?? self::BASE_DELIVERY_FEE;
        $distanceFee = $distance * self::FEE_PER_KM;

        $totalFee = $baseFee + $distanceFee;

        // Apply minimum delivery fee
        return max(self::MINIMUM_DELIVERY_FEE, round($totalFee, 2));
    }

    /**
     * Calculate surge pricing multiplier
     * 
     * @param float $baseFee Original delivery fee
     * @param float $surgeMultiplier Surge multiplier (e.g., 1.5 for 50% surge)
     * @return float
     */
    public function applySurgePricing(float $baseFee, float $surgeMultiplier): float
    {
        // Limit surge multiplier to reasonable range (1.0 - 3.0)
        $surgeMultiplier = max(1.0, min(3.0, $surgeMultiplier));

        return round($baseFee * $surgeMultiplier, 2);
    }

    // =========================================================================
    // COMMISSION CALCULATIONS
    // =========================================================================

    /**
     * Calculate rider earnings from order total
     * 
     * @param float $totalAmount Total order amount
     * @return float
     */
    public function calculateRiderEarnings(float $totalAmount): float
    {
        $earnings = $totalAmount * self::RIDER_COMMISSION_PERCENTAGE;

        // Deduct any applicable taxes/fees

        return round($earnings, 2);
    }

    /**
     * Calculate platform commission from order total
     * 
     * @param float $totalAmount Total order amount
     * @return float
     */
    public function calculatePlatformCommission(float $totalAmount): float
    {
        return round($totalAmount * self::PLATFORM_COMMISSION_PERCENTAGE, 2);
    }

    // =========================================================================
    // COMPREHENSIVE CALCULATIONS
    // =========================================================================

    /**
     * Calculate complete order fee breakdown
     * 
     * @param array $params Order parameters
     * @return array Complete fee breakdown
     */
    public function calculateFullBreakdown(array $params): array
    {
        $subtotal = $params['subtotal'] ?? 0;
        $distance = $params['distance'] ?? 0;
        $gateway = $params['gateway'] ?? 'paystack';
        $surgeMultiplier = $params['surge_multiplier'] ?? 1.0;

        // Calculate fees
        $deliveryFee = $this->calculateDeliveryFee($distance);
        $deliveryFeeWithSurge = $this->applySurgePricing($deliveryFee, $surgeMultiplier);

        $serviceFee = $this->calculateServiceFee($subtotal);
        $processorFee = $this->calculateProcessorFee($subtotal, $gateway);

        $totalBeforeDiscount = $subtotal + $deliveryFeeWithSurge + $serviceFee + $processorFee;

        $discount = $params['discount'] ?? 0;
        $totalAmount = max(0, $totalBeforeDiscount - $discount);

        $riderEarnings = $this->calculateRiderEarnings($deliveryFeeWithSurge);
        $platformCommission = $this->calculatePlatformCommission($deliveryFeeWithSurge);

        return [
            'breakdown' => [
                'subtotal' => round($subtotal, 2),
                'delivery_fee' => round($deliveryFeeWithSurge, 2),
                'service_fee' => round($serviceFee, 2),
                'processor_fee' => round($processorFee, 2),
                'discount' => round($discount, 2),
                'total_amount' => round($totalAmount, 2),
            ],
            'earnings' => [
                'rider_earnings' => round($riderEarnings, 2),
                'platform_commission' => round($platformCommission, 2),
            ],
            'details' => [
                'distance_km' => $distance,
                'surge_multiplier' => $surgeMultiplier,
                'gateway' => $gateway,
            ],
        ];
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Get fee structure for a specific gateway
     * 
     * @return array Fee structure details
     */
    public function getGatewayFeeStructure(string $gateway): array
    {
        $this->validateGateway($gateway);

        return match ($gateway) {
            'paystack' => [
                'percentage' => self::PAYSTACK_PERCENTAGE * 100,
                'fixed' => self::PAYSTACK_FIXED,
                'currency' => 'NGN',
                'formula' => '1.5% + ₦100',
            ],
            'stripe' => [
                'percentage' => self::STRIPE_PERCENTAGE * 100,
                'fixed' => self::STRIPE_FIXED,
                'currency' => 'USD',
                'formula' => '2.9% + $0.30',
            ],
            'flutterwave' => [
                'percentage' => self::FLUTTERWAVE_PERCENTAGE * 100,
                'fixed' => self::FLUTTERWAVE_FIXED,
                'currency' => 'NGN',
                'formula' => '1.4% + ₦100',
            ],
            default => [],
        };
    }

    /**
     * Check if amount is eligible for free delivery
     * 
     * @param float $subtotal Order subtotal
     * @param float $threshold Free delivery threshold
     * @return bool
     */
    public function isFreeDeliveryEligible(float $subtotal, float $threshold = 5000): bool
    {
        return $subtotal >= $threshold;
    }

    /**
     * Calculate estimated total for customer
     * 
     * @param float $subtotal Order subtotal
     * @param float $distance Distance in kilometers
     * @param float $discount Applied discount
     * @return array
     */
    public function estimateTotal(float $subtotal, float $distance, float $discount = 0): array
    {
        $deliveryFee = $this->calculateDeliveryFee($distance);
        $serviceFee = $this->calculateServiceFee($subtotal);

        $total = $subtotal + $deliveryFee + $serviceFee - $discount;

        return [
            'subtotal' => round($subtotal, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'service_fee' => round($serviceFee, 2),
            'discount' => round($discount, 2),
            'total' => round(max(0, $total), 2),
        ];
    }
}
