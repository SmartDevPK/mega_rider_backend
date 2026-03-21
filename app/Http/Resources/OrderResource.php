<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'customer' => new UserResource($this->whenLoaded('customer')),
            'driver' => new UserResource($this->whenLoaded('driver')),
            
            // Pickup Information
            'pickup' => [
                'address' => $this->pickup_address,
                'latitude' => (float) $this->pickup_latitude,
                'longitude' => (float) $this->pickup_longitude,
                'city' => $this->pickup_city,
                'state' => $this->pickup_state
            ],
            
            // Delivery Information
            'delivery' => [
                'address' => $this->delivery_address,
                'latitude' => (float) $this->delivery_latitude,
                'longitude' => (float) $this->delivery_longitude,
                'city' => $this->dropoff_city,
                'state' => $this->dropoff_state
            ],
            
            // Sender Information
            'sender' => [
                'name' => $this->sender_name,
                'email' => $this->sender_email,
                'phone' => $this->sender_phone
            ],
            
            // Receiver Information
            'receiver' => [
                'name' => $this->receiver_name,
                'email' => $this->receiver_email,
                'phone' => $this->receiver_phone
            ],
            
            // Package Information
            'package' => [
                'name' => $this->package_name,
                'image' => $this->package_image ? asset('storage/' . $this->package_image) : null,
                'worth' => (float) $this->package_worth,
                'formatted_worth' => '₦' . number_format($this->package_worth, 2),
                'insurance' => (bool) $this->package_insurance,
                'insurance_fee' => $this->insurance_fee ? (float) $this->insurance_fee : null,
                'formatted_insurance_fee' => $this->insurance_fee ? '₦' . number_format($this->insurance_fee, 2) : null
            ],
            
            // Order Details
            'vehicle_type' => $this->vehicle_type,
            'instructions' => $this->order_instruction,
            'travel_time' => $this->travel_time,
            
            // Status
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Formatted timestamps
            'created_at_formatted' => $this->created_at?->format('M d, Y H:i A'),
            'updated_at_formatted' => $this->updated_at?->format('M d, Y H:i A')
        ];
    }
}