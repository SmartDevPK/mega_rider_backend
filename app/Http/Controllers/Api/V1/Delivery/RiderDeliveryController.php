<?php

namespace App\Http\Controllers\Api\V1\Rider\Delivery;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RiderDeliveryController extends Controller
{
  /**
   * Get delivery history
   */
  public function deliveryHistory(Request $request)
  {
    try {
      $rider = $request->user();

      $perPage = $request->get('per_page', 15);
      $status = $request->get('status');

      $query = $rider->riderOrders()
        ->where('status', 'delivered')
        ->orderBy('delivered_at', 'desc');

      if ($status && in_array($status, ['delivered', 'cancelled', 'pending'])) {
        $query->where('status', $status);
      }

      $deliveries = $query->paginate($perPage);

      return response()->json([
        'success' => true,
        'message' => 'Delivery history retrieved successfully',
        'data' => [
          'current_page' => $deliveries->currentPage(),
          'per_page' => $deliveries->perPage(),
          'total' => $deliveries->total(),
          'last_page' => $deliveries->lastPage(),
          'deliveries' => $deliveries->map(function ($order) {
            return [
              'id' => $order->id,
              'order_id' => $order->order_id,
              'item_name' => $order->item_name ?? $order->package_name,
              'pickup_address' => $order->pickup_address,
              'dropoff_address' => $order->dropoff_address,
              'price' => (float) $order->price,
              'distance' => $order->distance,
              'status' => $order->status,
              'delivered_at' => $order->delivered_at?->toIso8601String(),
              'rating' => $order->rating ?? null,
            ];
          })
        ]
      ], 200);
    } catch (\Exception $e) {
      Log::error('Delivery history error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to load delivery history'
      ], 500);
    }
  }
}
