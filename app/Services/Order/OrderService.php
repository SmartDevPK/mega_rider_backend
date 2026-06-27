<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class OrderService
{
    /*
    |--------------------------------------------------------------------------
    | CREATE ORDER
    |--------------------------------------------------------------------------
    */
    public function createOrder(array $data, int $customerId): Order
    {
        $data['order_id'] = $this->generateOrderId();
        $data['customer_id'] = $customerId;
        $data['status'] = 'pending';

        // NEW FIELDS
        $data['step'] = 'pickup';
        $data['meta'] = $data['meta'] ?? [];

        // Handle image upload
        if (
            !empty($data['package_image']) &&
            $data['package_image'] instanceof \Illuminate\Http\UploadedFile
        ) {
            $data['package_image'] = $data['package_image']->store('package_images', 'public');
        } else {
            $data['package_image'] = null;
        }

        return Order::create($data);
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER ID GENERATOR
    |--------------------------------------------------------------------------
    */
    private function generateOrderId(): string
    {
        do {
            $id = 'MDX' . strtoupper(Str::random(5));
        } while (Order::where('order_id', $id)->exists());

        return $id;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ORDER TYPE & PRICING
    |--------------------------------------------------------------------------
    */
    public function updateOrderType(Order $order, int $orderTypeId): array
    {
        $orderType = OrderType::find($orderTypeId);

        if (!$orderType) {
            throw new \Exception('ORDER_TYPE_NOT_FOUND', 404);
        }

        if (!$this->hasCoordinates($order)) {
            throw new \Exception('ORDER_COORDINATES_MISSING', 400);
        }

        $order->order_type_id = $orderTypeId;
        $order->date_modified = Carbon::now();

        $pricing = $this->calculatePricing($order, $orderType);

        $order->fill($pricing);
        $order->save();

        return [
            'order_id' => $order->order_id,
            'order_type_id' => $orderTypeId,
            'pricing' => $pricing
        ];
    }

    private function hasCoordinates(Order $order): bool
    {
        return $order->pickup_latitude &&
            $order->pickup_longitude &&
            $order->dropoff_latitude &&
            $order->dropoff_longitude;
    }

    /*
    |--------------------------------------------------------------------------
    | PRICING ENGINE
    |--------------------------------------------------------------------------
    */
    protected function calculatePricing(Order $order, OrderType $orderType): array
    {
        $distanceKm = $this->calculateDistance(
            $order->pickup_latitude,
            $order->pickup_longitude,
            $order->dropoff_latitude,
            $order->dropoff_longitude
        );

        $deliveryFee = $distanceKm <= $orderType->base_distance
            ? $orderType->base_price
            : $orderType->base_price +
            ($distanceKm - $orderType->base_distance) * $orderType->price_per_km;

        $surgeMultiplier = $order->zone_id
            ? Cache::get("surge:zone:{$order->zone_id}", 0)
            : 0;

        return [
            'delivery_fee' => $deliveryFee,
            'surge_multiplier' => $surgeMultiplier,
            'surge_fee' => $deliveryFee * $surgeMultiplier,
            'total_amount' => $deliveryFee + ($deliveryFee * $surgeMultiplier),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | DISTANCE CALCULATION
    |--------------------------------------------------------------------------
    */
    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) *
            cos(deg2rad($lat2)) *
            sin($dLng / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER SUMMARY
    |--------------------------------------------------------------------------
    */
    public function getOrderSummary(Order $order): array
    {
        $deliveryFee = $this->calculateDeliveryFee($order);
        $surgeMultiplier = $this->getSurgeMultiplier($order->zone_id);

        $surgeFee = $deliveryFee * $surgeMultiplier;

        $insuranceFee = $order->insurance_flag
            ? (1.5 / 100) * ($order->package_worth ?? 0)
            : 0;

        $subtotal = $deliveryFee + $surgeFee + $insuranceFee;

        $processorFee = ($subtotal * 0.015) + 100;

        return [
            'delivery_fee' => round($deliveryFee, 2),
            'surge_fee' => round($surgeFee, 2),
            'insurance_fee' => round($insuranceFee, 2),
            'processor_fee' => round($processorFee, 2),
            'total_amount' => round($subtotal + $processorFee, 2),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | DELIVERY FEE
    |--------------------------------------------------------------------------
    */
    private function calculateDeliveryFee(Order $order): float
    {
        $orderType = $order->orderType;

        if (!$orderType) {
            throw new \Exception('Order type not assigned');
        }

        $distance = $order->distance ?? $this->calculateDistance(
            $order->pickup_latitude,
            $order->pickup_longitude,
            $order->dropoff_latitude,
            $order->dropoff_longitude
        );

        if ($distance <= $orderType->base_distance) {
            return $orderType->base_price;
        }

        return $orderType->base_price +
            (($distance - $orderType->base_distance) * $orderType->price_per_km);
    }

    /*
    |--------------------------------------------------------------------------
    | SURGE MULTIPLIER
    |--------------------------------------------------------------------------
    */
    private function getSurgeMultiplier(?int $zoneId): float
    {
        if (!$zoneId) return 0.0;

        return Cache::remember("surge:zone:{$zoneId}", 30, function () use ($zoneId) {

            $activeOrders = Order::whereIn('status', ['pending'])
                ->where('zone_id', $zoneId)
                ->count();

            $availableRiders = User::availableDrivers($zoneId)->count();

            $ratio = $activeOrders / max($availableRiders, 1);

            return match (true) {
                $ratio <= 1   => 0.0,
                $ratio <= 1.5 => 0.2,
                $ratio <= 2   => 0.5,
                $ratio <= 3   => 1.0,
                default       => 1.5,
            };
        });
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT BREAKDOWN
    |--------------------------------------------------------------------------
    */
    public function getPaymentBreakdown(Order $order): array
    {
        $deliveryFee = $order->delivery_fee ?? 0;
        $discount = $order->discount_amount ?? 0;
        $surgeFee = $order->surge_fee ?? 0;

        $insuranceFee = $order->insurance_flag
            ? ($order->insurance_fee ?? 0)
            : 0;

        $processorFee = $order->payment_processor_fee ?? 0;

        $total = $deliveryFee
            - $discount
            + $surgeFee
            + $insuranceFee
            + $processorFee;

        return [
            'date_paid' => optional($order->paid_at)->format('Y-m-d'),
            'delivery_fee' => round($deliveryFee, 2),
            'discount' => round($discount, 2),
            'surge_fee' => round($surgeFee, 2),
            'insurance_fee' => round($insuranceFee, 2),
            'processor_fee' => round($processorFee, 2),
            'total_amount' => round($total, 2),
            'customer' => [
                'firstname' => $order->customer->firstname ?? null
            ]
        ];
    }
}
