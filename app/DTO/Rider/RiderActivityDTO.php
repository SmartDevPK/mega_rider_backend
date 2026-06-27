<?php

namespace App\DTO\Rider;

class RiderActivityDTO
{
    public function __construct(
        public string $item_name,
        public string $order_id,
        public string $order_status,
        public string $drop_off_address,
        public string $ett,
        public float $price,
        public ?string $order_image = null,
        public ?string $pickup_address = null,
        public ?string $cancelled_at = null,
        public ?string $cancellation_reason = null
    ) {}

    /**
     * Create DTO from order model
     */
    public static function fromOrder($order): self
    {
        // Calculate estimated travel time
        $ett = self::formatEstimatedTravelTime($order);
        
        // Get order image URL
        $orderImage = null;
        if ($order->package_image) {
            $orderImage = asset('storage/' . $order->package_image);
        } elseif ($order->order_image) {
            $orderImage = asset('storage/' . $order->order_image);
        }

        return new self(
            item_name: $order->item_name ?? $order->package_name ?? 'N/A',
            order_id: $order->order_id ?? 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
            order_status: ucfirst(str_replace('_', ' ', $order->status)),
            drop_off_address: $order->dropoff_address,
            ett: $ett,
            price: (float) ($order->price ?? 0),
            order_image: $orderImage,
            pickup_address: $order->pickup_address ?? null,
            cancelled_at: $order->cancelled_at?->toIso8601String(),
            cancellation_reason: $order->cancellation_reason
        );
    }

    /**
     * Format estimated travel time
     */
    private static function formatEstimatedTravelTime($order): string
    {
        if ($order->estimated_travel_time) {
            $minutes = $order->estimated_travel_time;
            if ($minutes < 60) {
                return "{$minutes} mins";
            }
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            return $remainingMinutes > 0 
                ? "{$hours} hr {$remainingMinutes} mins" 
                : "{$hours} hr";
        }

        if ($order->distance) {
            return "{$order->distance} km";
        }

        return 'N/A';
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return array_filter([
            'item_name' => $this->item_name,
            'order_id' => $this->order_id,
            'order_status' => $this->order_status,
            'drop_off_address' => $this->drop_off_address,
            'ett' => $this->ett,
            'price' => $this->price,
            'order_image' => $this->order_image,
            'pickup_address' => $this->pickup_address,
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
        ], fn($value) => !is_null($value));
    }
}