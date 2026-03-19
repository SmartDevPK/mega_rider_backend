<?php
// app/Services/TipService.php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TipService
{
    /**
     * Add a tip to an order
     */
    public function create($orderId, array $data)
    {
        return DB::transaction(function () use ($orderId, $data) {
            // Find the order
            $order = Order::find($orderId);
            
            if (!$order) {
                throw new \Exception("Order not found with ID: {$orderId}");
            }

            // Check if order belongs to authenticated user
            if ($order->customer_id !== auth()->id()) {
                throw new \Exception("You do not have permission to add tip to this order");
            }

            // Add tip to order
            $tipData = [
                'tip_amount' => $data['amount'],
                'tip_method' => $data['method'] ?? 'cash',
                'tipped_at' => now()
            ];

            // If you have a tips table, create record there
            // $tip = Tip::create([
            //     'order_id' => $order->id,
            //     'amount' => $data['amount'],
            //     'method' => $data['method'] ?? 'cash',
            //     'user_id' => auth()->id()
            // ]);

            // Or store tip directly in orders table
            $order->update([
                'tip_amount' => $data['amount'],
                'tip_method' => $data['method'] ?? 'cash',
                'tip_added_at' => now()
            ]);

            Log::info('Tip added successfully', [
                'order_id' => $order->id,
                'amount' => $data['amount'],
                'method' => $data['method'] ?? 'cash'
            ]);

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_id,
                'tip_amount' => $data['amount'],
                'tip_method' => $data['method'] ?? 'cash',
                'message' => 'Tip added successfully'
            ];
        });
    }

    /**
     * Get tip for an order
     */
    public function getTip($orderId)
    {
        $order = Order::find($orderId);
        
        if (!$order) {
            throw new \Exception("Order not found");
        }

        return [
            'tip_amount' => $order->tip_amount ?? 0,
            'tip_method' => $order->tip_method ?? null,
            'tipped_at' => $order->tip_added_at ?? null
        ];
    }
}