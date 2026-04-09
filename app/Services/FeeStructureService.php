<?php

namespace App\Services;

class FeeStructureService
{
    /**
     * Calculate payment processor fee (e.g., Paystack).
     * 
     * @param float $subtotal Amount before processor fee.
     * @param string $gateway 'paystack', 'stripe', etc.
     * @return float
     */
    public function calculateProcessorFee(float $subtotal, string $gateway = 'paystack'): float
    {
        switch ($gateway) {
            case 'paystack':
                // 1.5% + ₦100 (example)
                $percentage = 0.015;
                $fixed = 100;
                return ($subtotal * $percentage) + $fixed;
            default:
                return 0;
        }
    }
}