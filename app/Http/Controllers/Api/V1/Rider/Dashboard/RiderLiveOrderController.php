<?php

namespace App\Http\Controllers\Api\V1\Rider\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RiderLiveOrderController extends Controller
{
  /**
   * Get Rider's Live Order (Active Delivery)
   * 
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getLiveOrder(Request $request)
  {
    try {
      $rider = $request->user();

      // Check if authenticated user is actually a rider
      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      // Use cache for better performance (30 seconds TTL)
      $cacheKey = "rider_live_order_{$rider->id}";

      $liveOrder = Cache::remember($cacheKey, 30, function () use ($rider) {
        return $this->fetchLiveOrder($rider);
      });

      if (!$liveOrder) {
        return response()->json([
          'success' => false,
          'message' => 'No active order found'
        ], 404);
      }

      return response()->json([
        'success' => true,
        'data' => $liveOrder
      ], 200);
    } catch (\Exception $e) {
      Log::error('Live order fetch error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id,
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to fetch live order'
      ], 500);
    }
  }

  /**
   * Fetch live order from database
   * 
   * @param Rider $rider
   * @return array|null
   */
  private function fetchLiveOrder($rider): ?array
  {
    // Find active order (assigned or picked_up)
    $order = $rider->riderOrders()
      ->whereIn('status', ['assigned', 'picked_up'])
      ->where('is_draft', false)
      ->latest('date_modified')
      ->first();

    if (!$order) {
      return null;
    }

    // Calculate estimated travel time if not set
    $estimatedTravelTime = $order->estimated_travel_time;
    $ettFormatted = $this->formatTravelTime($estimatedTravelTime);

    // Calculate progress percentage based on status and step
    $progressPercentage = $this->calculateProgressPercentage($order);

    // Format distance
    $distanceFormatted = $order->distance ? $order->distance . ' km' : 'N/A';

    // Get order image URL
    $orderImage = null;
    if ($order->package_image) {
      $orderImage = asset('storage/' . $order->package_image);
    } elseif ($order->image_file_name) {
      $orderImage = asset('storage/' . $order->image_file_name);
    }

    // Build response
    return [
      'id' => $order->id,
      'order_id' => $order->order_id ?? 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
      'item_name' => $order->item_name ?? $order->package_name ?? 'N/A',
      'order_status' => $this->formatStatus($order->status),
      'status_code' => $order->status,
      'pickup_address' => $order->pickup_address,
      'dropoff_address' => $order->dropoff_address,
      'price' => (float) ($order->price ?? 0),
      'distance' => $distanceFormatted,
      'ett' => $ettFormatted,
      'estimated_travel_time' => $estimatedTravelTime,
      'order_image' => $orderImage,
      'pickup_contact' => [
        'name' => $order->sender_name ?? null,
        'phone' => $order->sender_phone ?? null,
      ],
      'dropoff_contact' => [
        'name' => $order->receiver_name ?? null,
        'phone' => $order->receiver_phone ?? null,
      ],
      'special_instructions' => $order->special_instructions ?? null,
      'step' => $order->step ?? 'pickup',
      'progress_percentage' => $progressPercentage,
      'pickup_coordinates' => [
        'latitude' => $order->pickup_latitude,
        'longitude' => $order->pickup_longitude,
      ],
      'dropoff_coordinates' => [
        'latitude' => $order->dropoff_latitude,
        'longitude' => $order->dropoff_longitude,
      ],
      'created_at' => $order->created_at?->toIso8601String(),
      'assigned_at' => $order->date_modified?->toIso8601String(),
    ];
  }

  /**
   * Format travel time for display
   * 
   * @param int|null $minutes
   * @return string
   */
  private function formatTravelTime(?int $minutes): string
  {
    if (!$minutes) {
      return 'N/A';
    }

    if ($minutes < 60) {
      return "{$minutes} mins";
    }

    $hours = floor($minutes / 60);
    $remainingMinutes = $minutes % 60;

    if ($remainingMinutes > 0) {
      return "{$hours} hr {$remainingMinutes} mins";
    }

    return "{$hours} hr";
  }

  /**
   * Format status for display
   * 
   * @param string $status
   * @return string
   */
  private function formatStatus(string $status): string
  {
    return match ($status) {
      'assigned' => 'Accepted',
      'picked_up' => 'Picked Up',
      'delivered' => 'Delivered',
      'cancelled' => 'Cancelled',
      default => ucfirst(str_replace('_', ' ', $status))
    };
  }

  /**
   * Calculate progress percentage based on order status and step
   * 
   * @param $order
   * @return int
   */
  private function calculateProgressPercentage($order): int
  {
    // Define progress mapping
    $progressMap = [
      'assigned' => 25,
      'picked_up' => 50,
    ];

    // Base progress from status
    $baseProgress = $progressMap[$order->status] ?? 0;

    // Adjust based on step (if available)
    if ($order->step) {
      $stepProgress = [
        'pickup' => 25,
        'dropoff' => 50,
        'item' => 75,
        'review' => 90,
      ];

      $baseProgress = $stepProgress[$order->step] ?? $baseProgress;
    }

    return $baseProgress;
  }
}
