<?php

namespace App\Http\Controllers\Api\V1\Rider\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RiderMetricsController extends Controller
{
  /**
   * Get Rider Metrics Dashboard
   * 
   * Returns comprehensive performance metrics for the authenticated rider
   * within a specified date range.
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getMetricsDashboard(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $request->validate([
        'start_date' => 'required|date|before_or_equal:end_date',
        'end_date' => 'required|date|after_or_equal:start_date'
      ]);

      $startDate = Carbon::parse($request->start_date)->startOfDay();
      $endDate = Carbon::parse($request->end_date)->endOfDay();

      $cacheKey = "rider_metrics_{$rider->id}_{$startDate->toDateString()}_{$endDate->toDateString()}";
      $metrics = Cache::remember($cacheKey, 60, function () use ($rider, $startDate, $endDate) {
        return $this->calculateRiderMetrics($rider, $startDate, $endDate);
      });

      return response()->json([
        'success' => true,
        'data' => $metrics
      ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      Log::error('Rider metrics dashboard error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve rider metrics'
      ], 500);
    }
  }

  /**
   * Get Metrics Dashboard with Comparison
   * 
   * Enhanced version with period-over-period comparison
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getMetricsDashboardWithComparison(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'compare_previous' => 'boolean'
      ]);

      $startDate = Carbon::parse($request->start_date)->startOfDay();
      $endDate = Carbon::parse($request->end_date)->endOfDay();
      $comparePrevious = $request->input('compare_previous', true);

      $cacheKey = "rider_metrics_compare_{$rider->id}_{$startDate->toDateString()}_{$endDate->toDateString()}";

      $result = Cache::remember($cacheKey, 60, function () use ($rider, $startDate, $endDate, $comparePrevious) {
        $currentMetrics = $this->calculateRiderMetrics($rider, $startDate, $endDate);

        $data = [
          'current_period' => $currentMetrics,
          'period' => [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_days' => $startDate->diffInDays($endDate) + 1
          ]
        ];

        if ($comparePrevious) {
          $daysDiff = $startDate->diffInDays($endDate) + 1;
          $previousStartDate = $startDate->copy()->subDays($daysDiff);
          $previousEndDate = $endDate->copy()->subDays($daysDiff);

          $previousMetrics = $this->calculateRiderMetrics($rider, $previousStartDate, $previousEndDate);

          $data['previous_period'] = [
            'start_date' => $previousStartDate->toDateString(),
            'end_date' => $previousEndDate->toDateString(),
            'metrics' => $previousMetrics
          ];

          $data['comparison'] = $this->calculateMetricsComparison($currentMetrics, $previousMetrics);
        }

        return $data;
      });

      return response()->json([
        'success' => true,
        'data' => $result
      ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      Log::error('Rider metrics comparison error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve metrics comparison'
      ], 500);
    }
  }

  /**
   * Get Quick Metrics (Last 30 days)
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getQuickMetrics(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $endDate = now();
      $startDate = now()->subDays(30);

      $cacheKey = "rider_quick_metrics_{$rider->id}";
      $metrics = Cache::remember($cacheKey, 300, function () use ($rider, $startDate, $endDate) {
        return $this->calculateRiderMetrics($rider, $startDate, $endDate);
      });

      return response()->json([
        'success' => true,
        'data' => $metrics
      ], 200);
    } catch (\Exception $e) {
      Log::error('Quick metrics error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve quick metrics'
      ], 500);
    }
  }

  /**
   * Calculate comprehensive rider metrics
   *
   * @param Rider $rider
   * @param Carbon $startDate
   * @param Carbon $endDate
   * @return array
   */
  private function calculateRiderMetrics($rider, $startDate, $endDate): array
  {
    $completedDeliveries = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->where('status', 'delivered')
      ->whereBetween('delivered_at', [$startDate, $endDate])
      ->get();

    $acceptedDeliveries = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->whereIn('status', ['assigned', 'picked_up', 'delivered'])
      ->whereBetween('created_at', [$startDate, $endDate])
      ->count();

    $cancelledDeliveries = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->where('status', 'cancelled')
      ->whereBetween('created_at', [$startDate, $endDate])
      ->count();

    $totalCompleted = $completedDeliveries->count();
    $totalAccepted = $acceptedDeliveries;
    $totalCancelled = $cancelledDeliveries;

    $deliverySuccessRate = $totalAccepted > 0
      ? round(($totalCompleted / $totalAccepted) * 100, 2)
      : 0;

    $averageDeliveryTime = $this->calculateAverageDeliveryTime($completedDeliveries);
    $totalDistance = $completedDeliveries->sum('distance');
    $totalEarnings = $completedDeliveries->sum('price');
    $averageEarningPerDelivery = $totalCompleted > 0
      ? round($totalEarnings / $totalCompleted, 2)
      : 0;

    return [
      'total_accepted_deliveries' => $totalAccepted,
      'total_completed_deliveries' => $totalCompleted,
      'total_cancelled_deliveries' => $totalCancelled,
      'delivery_success_rate' => $deliverySuccessRate,
      'average_delivery_time_minutes' => round($averageDeliveryTime, 2),
      'average_delivery_time_hours' => round($averageDeliveryTime / 60, 2),
      'distance_covered_km' => round($totalDistance, 2),
      'total_earnings' => round($totalEarnings, 2),
      'average_earning_per_delivery' => $averageEarningPerDelivery,
      'busiest_day' => $this->getBusiestDay($rider->id, $startDate, $endDate),
      'peak_hour' => $this->getPeakHour($rider->id, $startDate, $endDate),
      'daily_average' => $this->calculateDailyAverage($totalCompleted, $startDate, $endDate)
    ];
  }

  /**
   * Calculate average delivery time
   *
   * @param \Illuminate\Support\Collection $deliveries
   * @return float
   */
  private function calculateAverageDeliveryTime($deliveries): float
  {
    if ($deliveries->isEmpty()) {
      return 0;
    }

    $totalMinutes = 0;
    $validCount = 0;

    foreach ($deliveries as $delivery) {
      $startTime = $delivery->created_at;
      $endTime = $delivery->delivered_at ?? $delivery->date_modified;

      if ($startTime && $endTime) {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        $totalMinutes += $start->diffInMinutes($end);
        $validCount++;
      }
    }

    return $validCount > 0 ? $totalMinutes / $validCount : 0;
  }

  /**
   * Get the busiest day of the week
   *
   * @param int $riderId
   * @param Carbon $startDate
   * @param Carbon $endDate
   * @return array|null
   */
  private function getBusiestDay(int $riderId, $startDate, $endDate): ?array
  {
    $dayStats = DB::table('orders')
      ->where('rider_id', $riderId)
      ->whereBetween('created_at', [$startDate, $endDate])
      ->select(
        DB::raw('DAYOFWEEK(created_at) as day_of_week'),
        DB::raw('COUNT(*) as total_orders')
      )
      ->groupBy(DB::raw('DAYOFWEEK(created_at)'))
      ->orderBy('total_orders', 'desc')
      ->first();

    if (!$dayStats) {
      return null;
    }

    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $dayIndex = $dayStats->day_of_week - 1;

    return [
      'day' => $days[$dayIndex] ?? 'Unknown',
      'total_orders' => (int) $dayStats->total_orders
    ];
  }

  /**
   * Get peak delivery hour
   *
   * @param int $riderId
   * @param Carbon $startDate
   * @param Carbon $endDate
   * @return array|null
   */
  private function getPeakHour(int $riderId, $startDate, $endDate): ?array
  {
    $hourStats = DB::table('orders')
      ->where('rider_id', $riderId)
      ->whereBetween('created_at', [$startDate, $endDate])
      ->select(
        DB::raw('HOUR(created_at) as hour'),
        DB::raw('COUNT(*) as total_orders')
      )
      ->groupBy(DB::raw('HOUR(created_at)'))
      ->orderBy('total_orders', 'desc')
      ->first();

    if (!$hourStats) {
      return null;
    }

    $hour = (int) $hourStats->hour;
    $hourFormatted = date('g A', strtotime("$hour:00"));

    return [
      'hour' => $hour,
      'hour_label' => $hourFormatted,
      'total_orders' => (int) $hourStats->total_orders
    ];
  }

  /**
   * Calculate daily average orders
   *
   * @param int $totalOrders
   * @param Carbon $startDate
   * @param Carbon $endDate
   * @return float
   */
  private function calculateDailyAverage(int $totalOrders, $startDate, $endDate): float
  {
    $days = $startDate->diffInDays($endDate) + 1;
    return $days > 0 ? round($totalOrders / $days, 2) : 0;
  }

  /**
   * Calculate metrics comparison between two periods
   *
   * @param array $current
   * @param array $previous
   * @return array
   */
  private function calculateMetricsComparison(array $current, array $previous): array
  {
    return [
      'completed_deliveries' => [
        'current' => $current['total_completed_deliveries'],
        'previous' => $previous['total_completed_deliveries'],
        'change' => $this->calculatePercentageChange(
          $current['total_completed_deliveries'],
          $previous['total_completed_deliveries']
        )
      ],
      'total_earnings' => [
        'current' => $current['total_earnings'],
        'previous' => $previous['total_earnings'],
        'change' => $this->calculatePercentageChange(
          $current['total_earnings'],
          $previous['total_earnings']
        )
      ],
      'delivery_success_rate' => [
        'current' => $current['delivery_success_rate'],
        'previous' => $previous['delivery_success_rate'],
        'change' => round($current['delivery_success_rate'] - $previous['delivery_success_rate'], 2)
      ],
      'average_delivery_time' => [
        'current' => $current['average_delivery_time_minutes'],
        'previous' => $previous['average_delivery_time_minutes'],
        'change' => round($current['average_delivery_time_minutes'] - $previous['average_delivery_time_minutes'], 2),
        'improved' => $current['average_delivery_time_minutes'] <= $previous['average_delivery_time_minutes']
      ],
      'distance_covered' => [
        'current' => $current['distance_covered_km'],
        'previous' => $previous['distance_covered_km'],
        'change' => $this->calculatePercentageChange(
          $current['distance_covered_km'],
          $previous['distance_covered_km']
        )
      ]
    ];
  }

  /**
   * Calculate percentage change between two values
   *
   * @param float $current
   * @param float $previous
   * @return float
   */
  private function calculatePercentageChange(float $current, float $previous): float
  {
    if ($previous == 0) {
      return $current > 0 ? 100 : 0;
    }
    return round((($current - $previous) / $previous) * 100, 2);
  }
}
