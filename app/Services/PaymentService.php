<?php
// app/Services/PaymentService.php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Process payment for an order
     */
    public function process($orderId, array $data)
    {
        return DB::transaction(function () use ($orderId, $data) {
            // Find the order
            $order = Order::find($orderId);
            
            if (!$order) {
                throw new \Exception("Order not found with ID: {$orderId}");
            }

            // Update payment information
            $updateData = [
                'payment_method' => $data['payment_method'],
                'payment_status' => 'paid',
                'date_payment_confirmed' => now()
            ];

            // Add payment reference if provided
            if (isset($data['payment_reference'])) {
                $updateData['payment_reference'] = $data['payment_reference'];
            }

            // Update the order
            $order->update($updateData);

            Log::info('Payment processed successfully', [
                'order_id' => $order->id,
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'] ?? $order->package_worth
            ]);

            return $order->fresh();
        });
    }

    /**
     * Verify payment with gateway
     */
    public function verifyPayment($reference)
    {
        // Add payment gateway verification logic here
        // For Paystack, Flutterwave, etc.
        
        return [
            'status' => 'success',
            'message' => 'Payment verified successfully'
        ];
    }

    /**
     * Process refund
     */
    public function refund($orderId, $amount = null)
    {
        return DB::transaction(function () use ($orderId, $amount) {
            $order = Order::find($orderId);
            
            if (!$order) {
                throw new \Exception("Order not found");
            }

            if ($order->payment_status !== 'paid') {
                throw new \Exception("Order is not paid");
            }

            $order->update([
                'payment_status' => 'refunded',
                'refund_amount' => $amount ?? $order->package_worth,
                'refunded_at' => now()
            ]);

            return $order;
        });
    }
}