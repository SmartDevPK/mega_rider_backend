<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Rider;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

class RiderDashboardController extends Controller
{
    /**
     * Get Rider Dashboard Statistics
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard(Request $request)
    {
        try {
            // 1. Get authenticated rider
            $rider = auth()->user();
            
            if (!$rider || !($rider instanceof Rider)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login as a rider.'
                ], 401);
            }
            
            // 2. Get Nigerian current date/time
            $today = now()->startOfDay();
            $todayDate = $today->toDateString();
            
            // 3. Get dashboard statistics
            $stats = $this->getDashboardStats($rider->id, $todayDate);
            
            // 4. Get current active delivery
            $currentDelivery = $this->getCurrentActiveDelivery($rider->id);
            
            // 5. Return response
            return response()->json([
                'success' => true,
                'data' => [
                    'accepted_deliveries' => $stats['accepted_count'],
                    'completed_deliveries' => $stats['completed_count'],
                    'amount_earned' => (float) $stats['total_earned'],
                    'current_delivery' => $currentDelivery,
                    'last_updated_at' => now()->toDateTimeString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Rider Dashboard Error', [
                'rider_id' => auth()->id(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to load dashboard. Please try again later.'
            ], 500);
        }
    }
    
    /**
     * Get dashboard statistics for rider
     */
    private function getDashboardStats(int $riderId, string $todayDate): array
    {
        $stats = Order::where('rider_id', $riderId)
            ->whereDate('date_modified', $todayDate)
            ->select([
                DB::raw('COUNT(CASE WHEN status IN ("accepted", "Accepted") THEN 1 END) as accepted_count'),
                DB::raw('COUNT(CASE WHEN status IN ("delivered", "Delivered", "completed", "Completed") THEN 1 END) as completed_count'),
                DB::raw('SUM(CASE WHEN status IN ("delivered", "Delivered", "completed", "Completed") THEN rider_payout ELSE 0 END) as total_earned')
            ])
            ->first();
        
        return [
            'accepted_count' => (int) ($stats->accepted_count ?? 0),
            'completed_count' => (int) ($stats->completed_count ?? 0),
            'total_earned' => (float) ($stats->total_earned ?? 0)
        ];
    }
    
    /**
     * Get current active delivery for rider
     */
    private function getCurrentActiveDelivery(int $riderId): ?array
    {
        $activeOrder = Order::where('rider_id', $riderId)
            ->whereIn('status', ['accepted', 'Accepted', 'picked_up', 'Picked Up', 'in_transit', 'In Transit'])
            ->whereDate('date_modified', now()->toDateString())
            ->orderBy('date_modified', 'desc')
            ->first();
        
        if (!$activeOrder) {
            return null;
        }
        
        return [
            'order_id' => $activeOrder->id,
            'item_name' => $activeOrder->item_name ?? 'N/A',
            'pickup_address' => $activeOrder->pickup_address ?? 'N/A',
            'dropoff_address' => $activeOrder->dropoff_address ?? 'N/A',
            'delivery_fee' => (float) ($activeOrder->delivery_fee ?? 0),
            'rider_payout' => (float) ($activeOrder->rider_payout ?? 0),
            'distance' => $activeOrder->distance ?? '0 km',
            'order_image' => $this->getOrderImageUrl($activeOrder),
            'status' => $activeOrder->status,
            'estimated_delivery_time' => $this->calculateEstimatedDeliveryTime($activeOrder->distance ?? '0')
        ];
    }
    
    /**
     * Get order image URL
     */
    private function getOrderImageUrl($order): ?string
    {
        if (isset($order->image_file_name) && $order->image_file_name) {
            return url('/storage/orders/' . $order->image_file_name);
        }
        
        if (isset($order->order_image) && $order->order_image) {
            return $order->order_image;
        }
        
        return null;
    }
    
    /**
     * Calculate estimated delivery time based on distance
     */
    private function calculateEstimatedDeliveryTime(string $distance): string
    {
        // Extract numeric value from distance string (e.g., "12km" -> 12)
        preg_match('/(\d+(?:\.\d+)?)/', $distance, $matches);
        $distanceValue = isset($matches[1]) ? (float) $matches[1] : 5;
        
        // Estimate: 15 minutes per km average
        $minutes = ceil($distanceValue * 15);
        
        if ($minutes < 30) {
            return '30 minutes';
        } elseif ($minutes < 60) {
            return '45 minutes';
        } elseif ($minutes < 120) {
            return '1 hour';
        } else {
            return '2+ hours';
        }
    }
    
    /**
     * Get detailed delivery history (optional endpoint)
     */
    public function deliveryHistory(Request $request)
    {
        try {
            $rider = auth()->user();
            
            if (!$rider || !($rider instanceof Rider)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            
            $query = Order::where('rider_id', $rider->id);
            
            if ($status) {
                $query->where('status', $status);
            }
            
            if ($fromDate) {
                $query->whereDate('date_modified', '>=', $fromDate);
            }
            
            if ($toDate) {
                $query->whereDate('date_modified', '<=', $toDate);
            }
            
            $orders = $query->orderBy('date_modified', 'desc')
                ->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'current_page' => $orders->currentPage(),
                    'data' => $orders->items(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Delivery History Error', [
                'rider_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to load delivery history'
            ], 500);
        }
    }
    
    /**
     * Get weekly earnings summary
     */
    public function weeklyEarnings(Request $request)
    {
        try {
            $rider = auth()->user();
            
            if (!$rider || !($rider instanceof Rider)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            $weeklyEarnings = Order::where('rider_id', $rider->id)
                ->where('status', 'delivered')
                ->whereDate('date_modified', '>=', now()->subDays(7))
                ->select(
                    DB::raw('DATE(date_modified) as delivery_date'),
                    DB::raw('COUNT(*) as deliveries_count'),
                    DB::raw('SUM(rider_payout) as total_earned')
                )
                ->groupBy(DB::raw('DATE(date_modified)'))
                ->orderBy('delivery_date', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'weekly_summary' => $weeklyEarnings,
                    'total_week_earnings' => $weeklyEarnings->sum('total_earned'),
                    'total_week_deliveries' => $weeklyEarnings->sum('deliveries_count'),
                    'week_start_date' => now()->subDays(7)->toDateString(),
                    'week_end_date' => now()->toDateString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Weekly Earnings Error', [
                'rider_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to load weekly earnings'
            ], 500);
        }
    }
    
    /**
     * Update rider online status
     */
    public function updateStatus(Request $request)
    {
        try {
            $validated = $request->validate([
                'is_online' => 'required|boolean'
            ]);
            
            $rider = auth()->user();
            
            if (!$rider || !($rider instanceof Rider)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            $rider->is_online = $validated['is_online'];
            $rider->last_status_update = now();
            $rider->save();
            
            return response()->json([
                'success' => true,
                'message' => $validated['is_online'] ? 'You are now online' : 'You are now offline',
                'data' => [
                    'is_online' => $rider->is_online,
                    'last_status_update' => $rider->last_status_update
                ]
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Update Rider Status Error', [
                'rider_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to update status'
            ], 500);
        }
    }
}