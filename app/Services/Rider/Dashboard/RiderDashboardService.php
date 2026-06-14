<?php

namespace App\Services\Rider\Dashboard;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

class RiderDashboardService
{
    /**
     * The authenticated rider's ID.
     */
    protected int $riderId;

    /**
     * Current date in Lagos timezone.
     */
    protected string $today;

    /**
     * Cache duration in seconds.
     */
    protected int $cacheDuration;

    /**
     * Create a new rider dashboard service instance.
     */
    public function __construct()
    {
        $this->riderId = $this->getAuthenticatedRiderId();
        $this->today = $this->getCurrentDate();
        $this->cacheDuration = config('rider.dashboard_cache_duration', 300); // 5 minutes default
    }

    /**
     * Get complete dashboard data for the authenticated rider.
     *
     * @return array Dashboard data
     */
    public function getDashboardData(): array
    {
        try {
            $cacheKey = $this->generateCacheKey();

            return Cache::remember($cacheKey, $this->cacheDuration, function () {
                return $this->buildDashboardData();
            });
        } catch (\Exception $e) {
            Log::error('Failed to fetch rider dashboard data', [
                'rider_id' => $this->riderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getEmptyDashboardData();
        }
    }

    /**
     * Get dashboard data with real-time updates (bypass cache).
     *
     * @return array Real-time dashboard data
     */
    public function getRealTimeDashboardData(): array
    {
        return $this->buildDashboardData();
    }

    /**
     * Build the complete dashboard data array.
     */
    protected function buildDashboardData(): array
    {
        return [
            'accepted_deliveries' => $this->getAcceptedDeliveriesCount(),
            'completed_deliveries' => $this->getCompletedDeliveriesCount(),
            'amount_earned' => $this->getAmountEarned(),
            'current_delivery' => $this->getCurrentDelivery(),
            'pending_deliveries' => $this->getPendingDeliveriesCount(),
            'total_deliveries_today' => $this->getTotalDeliveriesToday(),
            'average_rating' => $this->getAverageRating(),
            'weekly_earnings' => $this->getWeeklyEarnings(),
        ];
    }

    /**
     * Get empty dashboard data structure.
     */
    protected function getEmptyDashboardData(): array
    {
        return [
            'accepted_deliveries' => 0,
            'completed_deliveries' => 0,
            'amount_earned' => 0.00,
            'current_delivery' => null,
            'pending_deliveries' => 0,
            'total_deliveries_today' => 0,
            'average_rating' => 0.00,
            'weekly_earnings' => 0.00,
        ];
    }

    /**
     * Get authenticated rider ID.
     */
    protected function getAuthenticatedRiderId(): int
    {
        $riderId = Auth::id();

        if (!$riderId) {
            throw new \RuntimeException('No authenticated rider found');
        }

        return $riderId;
    }

    /**
     * Get current date in Lagos timezone.
     */
    protected function getCurrentDate(): string
    {
        return Carbon::now('Africa/Lagos')->toDateString();
    }

    /**
     * Generate cache key for dashboard data.
     */
    protected function generateCacheKey(): string
    {
        return "rider_dashboard:{$this->riderId}:{$this->today}";
    }

    /**
     * Clear dashboard cache for the rider.
     */
    public function clearDashboardCache(): bool
    {
        $cacheKey = $this->generateCacheKey();
        return Cache::forget($cacheKey);
    }

    /**
     * Get accepted deliveries count for today.
     * Orders with status 'assigned' or 'picked_up'
     */
    protected function getAcceptedDeliveriesCount(): int
    {
        return Order::where('rider_id', $this->riderId)
            ->whereIn('status', ['assigned', 'picked_up'])
            ->where(function ($query) {
                $query->whereDate('date_accepted', $this->today)
                    ->orWhereDate('created_at', $this->today);
            })
            ->count();
    }

    /**
     * Get completed deliveries count for today.
     * Orders with status 'delivered'
     */
    protected function getCompletedDeliveriesCount(): int
    {
        return Order::where('rider_id', $this->riderId)
            ->where('status', 'delivered')
            ->where(function ($query) {
                $query->whereDate('date_delivered', $this->today)
                    ->orWhereDate('delivered_at', $this->today)
                    ->orWhereDate('updated_at', $this->today);
            })
            ->count();
    }

    /**
     * Calculate total amount earned today.
     */
    protected function getAmountEarned(): float
    {
        $total = Order::where('rider_id', $this->riderId)
            ->where('status', 'delivered')
            ->where(function ($query) {
                $query->whereDate('date_delivered', $this->today)
                    ->orWhereDate('delivered_at', $this->today)
                    ->orWhereDate('updated_at', $this->today);
            })
            ->sum('price');

        return (float) $total;
    }

    /**
     * Get current active delivery for the rider.
     */
    protected function getCurrentDelivery(): ?array
    {
        $order = Order::where('rider_id', $this->riderId)
            ->whereIn('status', ['assigned', 'picked_up'])
            ->where('is_draft', false)
            ->latest('date_modified')
            ->first();

        if (!$order) {
            return null;
        }

        return $this->formatDeliveryData($order);
    }

    /**
     * Get pending deliveries count.
     * Orders with status 'pending' or 'assigned'
     */
    protected function getPendingDeliveriesCount(): int
    {
        return Order::where('rider_id', $this->riderId)
            ->whereIn('status', ['pending', 'assigned'])
            ->where('is_draft', false)
            ->count();
    }

    /**
     * Get total deliveries count for today.
     */
    protected function getTotalDeliveriesToday(): int
    {
        return Order::where('rider_id', $this->riderId)
            ->whereDate('created_at', $this->today)
            ->count();
    }

    /**
     * Get rider's average rating.
     */
    protected function getAverageRating(): float
    {
        $rating = Order::where('rider_id', $this->riderId)
            ->whereNotNull('rider_rating')
            ->avg('rider_rating');

        return $rating ? round((float) $rating, 2) : 0.00;
    }

    /**
     * Get total earnings for the current week.
     */
    protected function getWeeklyEarnings(): float
    {
        $startOfWeek = Carbon::now('Africa/Lagos')->startOfWeek();
        $endOfWeek = Carbon::now('Africa/Lagos')->endOfWeek();

        $total = Order::where('rider_id', $this->riderId)
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$startOfWeek, $endOfWeek])
            ->sum('price');

        return (float) $total;
    }

    /**
     * Format delivery data for response.
     */
    protected function formatDeliveryData(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_id' => $order->order_id,
            'item_name' => $order->item_name ?? $order->package_name ?? 'N/A',
            'pickup_address' => $order->pickup_address,
            'dropoff_address' => $order->dropoff_address,
            'price' => (float) $order->price,
            'distance' => $order->distance ? $order->distance . ' km' : 'N/A',
            'estimated_delivery_time' => $this->calculateEstimatedDeliveryTime($order),
            'order_image' => $this->getOrderImageUrl($order),
            'status' => $order->status,
            'step' => $order->step,
            'sender_phone' => $order->sender_phone,
            'receiver_phone' => $order->receiver_phone,
            'sender_name' => $order->sender_name ?? null,
            'receiver_name' => $order->receiver_name ?? null,
        ];
    }

    /**
     * Get order image URL.
     */
    protected function getOrderImageUrl(Order $order): ?string
    {
        if (!$order->package_image) {
            return null;
        }

        // Check if it's already a full URL
        if (filter_var($order->package_image, FILTER_VALIDATE_URL)) {
            return $order->package_image;
        }

        return url('/Images/' . ltrim($order->package_image, '/'));
    }

    /**
     * Calculate estimated delivery time.
     */
    protected function calculateEstimatedDeliveryTime(Order $order): string
    {
        if ($order->distance) {
            // Assuming average speed of 30 km/h
            $minutes = ($order->distance / 30) * 60;
            return ceil($minutes) . ' minutes';
        }

        return 'N/A';
    }

    /**
     * Get delivery statistics for the rider.
     */
    public function getDeliveryStats(): array
    {
        return [
            'total_deliveries' => Order::where('rider_id', $this->riderId)->count(),
            'completed_deliveries' => Order::where('rider_id', $this->riderId)
                ->where('status', 'delivered')
                ->count(),
            'cancelled_deliveries' => Order::where('rider_id', $this->riderId)
                ->where('status', 'cancelled')
                ->count(),
            'total_earnings' => (float) Order::where('rider_id', $this->riderId)
                ->where('status', 'delivered')
                ->sum('price'),
            'average_delivery_time' => $this->getAverageDeliveryTime(),
        ];
    }

    /**
     * Get average delivery time in minutes.
     */
    protected function getAverageDeliveryTime(): ?float
    {
        $completedOrders = Order::where('rider_id', $this->riderId)
            ->where('status', 'delivered')
            ->whereNotNull('delivered_at')
            ->whereNotNull('created_at')
            ->get();

        if ($completedOrders->isEmpty()) {
            return null;
        }

        $totalMinutes = $completedOrders->sum(function ($order) {
            return Carbon::parse($order->created_at)
                ->diffInMinutes(Carbon::parse($order->delivered_at));
        });

        return round($totalMinutes / $completedOrders->count(), 2);
    }
}
