<?php

namespace App\Http\Resources\Rider;

use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'accepted_deliveries' => $this['accepted_deliveries'],
            'completed_deliveries' => $this['completed_deliveries'],
            'amount_earned' => (int) $this['amount_earned'],
            'current_delivery' => $this['current_delivery'] ? [
                'price' => $this['current_delivery']['price'],
                'pickup_address' => $this['current_delivery']['pickup_address'],
                'dropoff_address' => $this['current_delivery']['dropoff_address'],
                'etd' => $this['current_delivery']['etd'],
                'item_name' => $this['current_delivery']['item_name'],
                'order_image' => $this['current_delivery']['order_image'],
            ] : null,
            
            // Metadata for debugging (optional)
            '_meta' => [
                'timestamp' => now()->toIso8601String(),
                'timezone' => 'Africa/Lagos'
            ]
        ];
    }
}