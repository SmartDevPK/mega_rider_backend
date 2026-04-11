<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class OrderService
{
    // ----------------------------------------------------
    // CREATE ORDER
    // ----------------------------------------------------
    public function createOrder(array $data, int $customerId): Order
    {
        $data['order_id'] = $this->generateOrderId();
        $data['customer_id'] = $customerId;
        $data['status'] = 'pending';

        if (isset($data['package_image']) && $data['package_image'] instanceof \Illuminate\Http\UploadedFile) {
            $data['package_image'] = $data['package_image']->store('package_images', 'public');
        } else {
            $data['package_image'] = null;
        }

        return Order::create($data);
    }

    // ----------------------------------------------------
    // GENERATE UNIQUE ORDER ID
    // ----------------------------------------------------
    private function generateOrderId(): string
    {
        do {
            $id = 'MDX' . strtoupper(Str::random(5));
        } while (Order::where('order_id', $id)->exists());

        return $id;
    }

    // ----------------------------------------------------
    // UPDATE ORDER TYPE
    // ----------------------------------------------------
    public function updateOrderType(Order $order, int $orderTypeId): array
    {
        $orderType = OrderType::find($orderTypeId);

        if (!$orderType) {
            throw new \Exception('ORDER_TYPE_NOT_FOUND', 404);
        }

        if (!$order->pickup_latitude || !$order->pickup_longitude ||
            !$order->dropoff_latitude || !$order->dropoff_longitude) {
            throw new \Exception('ORDER_COORDINATES_MISSING', 400);
        }

        $order->order_type_id = $orderTypeId;
        $order->date_modified = Carbon::now();

        $pricing = $this->calculatePricing($order, $orderType);

        $order->delivery_fee      = $pricing['delivery_fee'];
        $order->surge_multiplier  = $pricing['surge_multiplier'];
        $order->surge_fee         = $pricing['surge_fee'];
        $order->total_amount      = $pricing['total_amount'];

        $order->save();

        return [
            'order_id'      => $order->order_id,
            'order_type_id' => $orderTypeId,
            'pricing'       => $pricing
        ];
    }

    // ----------------------------------------------------
    // CALCULATE PRICING (BASE + SURGE)
    // ----------------------------------------------------
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
            : $orderType->base_price + ($distanceKm - $orderType->base_distance) * $orderType->price_per_km;

        $zoneId = $order->zone_id;
        $surgeMultiplier = is_null($zoneId) ? 0 : Cache::get("surge:zone:{$zoneId}", 0);

        $surgeFee = $deliveryFee * $surgeMultiplier;
        $totalAmount = $deliveryFee + $surgeFee;

        return [
            'delivery_fee'     => $deliveryFee,
            'surge_multiplier' => $surgeMultiplier,
            'surge_fee'        => $surgeFee,
            'total_amount'     => $totalAmount
        ];
    }

    // ----------------------------------------------------
    // CALCULATE DISTANCE (Haversine)
    // ----------------------------------------------------
    protected function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    // ----------------------------------------------------
    // GET FULL ORDER PRICING SUMMARY
    // ----------------------------------------------------
    public function getOrderSummary(Order $order): array
{
    $zoneId = $order->zone_id;
    $surgeMultiplier = $this->getSurgeMultiplier($zoneId);

    $deliveryFee = $this->calculateDeliveryFee($order);
    $surgeFee = $deliveryFee * $surgeMultiplier;

    $discount = 0;

    $insuranceFee = 0;
    if ($order->insurance_flag) {
        $insurancePercentage = 1.5; // Configurable
        $insuranceFee = ($insurancePercentage / 100) * ($order->package_worth ?? 0);
    }

    $subtotal = $deliveryFee + $surgeFee + $insuranceFee - $discount;
    $processorFee = ($subtotal * 0.015) + 100; // Example for Paystack
    $totalAmount = $subtotal + $processorFee;

    return [
        'delivery_fee'      => round($deliveryFee, 2),
        'discount'          => round($discount, 2),
        'surge_multiplier'  => $surgeMultiplier,
        'surge_fee'         => round($surgeFee, 2),
        'insurance_fee'     => round($insuranceFee, 2),
        'processor_fee'     => round($processorFee, 2),
        'total_amount'      => round($totalAmount, 2),
    ];
}

// ----------------------------------------------------
// DELIVERY FEE BASED ON ORDER TYPE & DISTANCE
// ----------------------------------------------------
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

    $extra = $distance - $orderType->base_distance;
    return $orderType->base_price + ($extra * $orderType->price_per_km);
}

// ----------------------------------------------------
// SURGE MULTIPLIER (CACHED PER ZONE)
// ----------------------------------------------------
private function getSurgeMultiplier(?int $zoneId): float
{
    if (!$zoneId) {
        return 0.0;
    }

    $cacheKey = "surge:zone:{$zoneId}";

    return Cache::remember($cacheKey, 30, function () use ($zoneId) {

        // Count of active orders in this zone
        $activeOrders = Order::whereIn('status', ['pending', 'searching'])
            ->where('zone_id', $zoneId)
            ->count();

        // Count of available riders/drivers in this zone
        $availableRiders = User::availableDrivers($zoneId)->count();

        $ratio = $activeOrders / max($availableRiders, 1);

        // Map ratio to surge multiplier
        return match(true) {
            $ratio <= 1    => 0.0,
            $ratio <= 1.5  => 0.2,
            $ratio <= 2    => 0.5,
            $ratio <= 3    => 1.0,
            default        => 1.5,
        };
    });
}

public function getPaymentBreakdown(Order $order): array
{
    $deliveryFee = $order->delivery_fee ?? 0;
    $discount = $order->discount_amount ?? 0;

    // 🔥 Surge
    $surgeFee = $order->surge_fee ?? 0;

    // Optional dynamic surge (if you want real-time)
    /*
    $zoneId = $order->zone_id;
    $cacheKey = "surge:zone:{$zoneId}";
    $multiplier = Cache::get($cacheKey, 0);
    $surgeFee = $deliveryFee * $multiplier;
    */

    // 🛡️ Insurance
    $insuranceFee = $order->is_insured ? ($order->insurance_fee ?? 0) : 0;

    // 💳 Processor fee
    $processorFee = $order->payment_processor_fee ?? 0;

    // 🧮 Total
    $totalAmount =
        $deliveryFee
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
        'total_amount' => round($totalAmount, 2),

        'customer' => [
            'firstname' => $order->customer->firstname ?? null
        ]
    ];
}


}