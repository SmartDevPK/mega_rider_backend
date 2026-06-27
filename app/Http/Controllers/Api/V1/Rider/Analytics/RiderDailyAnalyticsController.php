<?php

namespace App\Http\Controllers\Api\V1\Rider\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RiderDailyAnalyticsController extends Controller
{
  /**
   * Get Rider Daily Order Counts
   * 
   * Returns daily order counts for the authenticated rider
   * for the current week (Sunday to Today).
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getDailyOrderCounts(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $cacheKey = "rider_daily_orders_{$rider->id}_" . now()->format('Y_m_d');
      $dailyData = Cache::remember($cacheKey, 60, function () use ($rider) {
        return $this->fetchDailyOrderCounts($rider);
      });

      return response()->json([
        'success' => true,
        'data' => $dailyData
      ], 200);
    } catch (\Exception $e) {
      Log::error('Daily order counts error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve daily order counts'
      ], 500);
    }
  }

  /**
   * Get Rider Daily Order Counts with Date Range
   * 
   * Returns daily order counts for a custom date range
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getDailyOrderCountsByDateRange(Request $request)
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
      ]);

      $startDate = Carbon::parse($request->start_date)->startOfDay();
      $endDate = Carbon::parse($request->end_date)->endOfDay();

      $cacheKey = "rider_daily_orders_range_{$rider->id}_{$startDate->toDateString()}_{$endDate->toDateString()}";
      $dailyData = Cache::remember($cacheKey, 300, function () use ($rider, $startDate, $endDate) {
        return $this->fetchDailyOrderCountsByRange($rider, $startDate, $endDate);
      });

      $summary = $this->calculateDailySummary($dailyData);

      return response()->json([
        'success' => true,
        'data' => [
          'period' => [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_days' => $startDate->diffInDays($endDate) + 1
          ],
          'summary' => $summary,
          'daily_breakdown' => $dailyData
        ]
      ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      Log::error('Daily order counts by range error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve daily order counts'
      ], 500);
    }
  }

  /**
   * Fetch daily order counts from database
   *
   * @param Rider $rider
   * @return array
   */
  private function fetchDailyOrderCounts($rider): array
  {
    $weekStart = now()->startOfWeek();
    $today = now()->endOfDay();

    $orderStats = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->whereBetween('created_at', [$weekStart, $today])
      ->select(
        DB::raw('DATE(created_at) as date'),
        DB::raw('COUNT(*) as total_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as completed_orders'),
        DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN price ELSE 0 END) as total_earnings')
      )
      ->groupBy(DB::raw('DATE(created_at)'))
      ->orderBy('date', 'asc')
      ->get()
      ->keyBy('date');

    $result = [];
    $currentDay = $weekStart->copy();

    while ($currentDay <= $today) {
      $dateKey = $currentDay->toDateString();
      $stats = $orderStats->get($dateKey);

      $totalOrders = $stats ? (int) $stats->total_orders : 0;
      $completedOrders = $stats ? (int) $stats->completed_orders : 0;
      $totalEarnings = $stats ? (float) $stats->total_earnings : 0;

      $result[] = [
        'date' => $dateKey,
        'day_name' => $currentDay->format('l'),
        'day_of_week' => (int) $currentDay->format('w'),
        'total_orders' => $totalOrders,
        'completed_orders' => $completedOrders,
        'cancelled_orders' => $stats ? (int) $stats->cancelled_orders : 0,
        'total_earnings' => $totalEarnings,
        'average_order_value' => $completedOrders > 0 ? round($totalEarnings / $completedOrders, 2) : 0,
        'completion_rate' => $totalOrders > 0
          ? round(($completedOrders / $totalOrders) * 100, 1)
          : 0
      ];

      $currentDay->addDay();
    }

    return $result;
  }

  /**
   * Fetch daily order counts by date range
   *
   * @param Rider $rider
   * @param Carbon $startDate
   * @param Carbon $endDate
   * @return array
   */
  private function fetchDailyOrderCountsByRange($rider, Carbon $startDate, Carbon $endDate): array
  {
    $orderStats = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->whereBetween('created_at', [$startDate, $endDate])
      ->select(
        DB::raw('DATE(created_at) as date'),
        DB::raw('COUNT(*) as total_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as completed_orders'),
        DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN price ELSE 0 END) as total_earnings')
      )
      ->groupBy(DB::raw('DATE(created_at)'))
      ->orderBy('date', 'asc')
      ->get()
      ->keyBy('date');

    $result = [];
    $currentDay = $startDate->copy();

    while ($currentDay <= $endDate) {
      $dateKey = $currentDay->toDateString();
      $stats = $orderStats->get($dateKey);

      $totalOrders = $stats ? (int) $stats->total_orders : 0;
      $completedOrders = $stats ? (int) $stats->completed_orders : 0;
      $totalEarnings = $stats ? (float) $stats->total_earnings : 0;

      $result[] = [
        'date' => $dateKey,
        'day_name' => $currentDay->format('l'),
        'day_of_week' => (int) $currentDay->format('w'),
        'total_orders' => $totalOrders,
        'completed_orders' => $completedOrders,
        'cancelled_orders' => $stats ? (int) $stats->cancelled_orders : 0,
        'total_earnings' => $totalEarnings,
        'average_order_value' => $completedOrders > 0 ? round($totalEarnings / $completedOrders, 2) : 0,
        'completion_rate' => $totalOrders > 0
          ? round(($completedOrders / $totalOrders) * 100, 1)
          : 0
      ];

      $currentDay->addDay();
    }

    return $result;
  }

  /**
   * Calculate daily summary statistics
   *
   * @param array $dailyData
   * @return array
   */
  private function calculateDailySummary(array $dailyData): array
  {
    if (empty($dailyData)) {
      return [
        'total_orders' => 0,
        'completed_orders' => 0,
        'cancelled_orders' => 0,
        'total_earnings' => 0,
        'average_daily_orders' => 0,
        'average_daily_earnings' => 0,
        'best_day' => null,
        'worst_day' => null
      ];
    }

    $totalOrders = array_sum(array_column($dailyData, 'total_orders'));
    $totalCompleted = array_sum(array_column($dailyData, 'completed_orders'));
    $totalEarnings = array_sum(array_column($dailyData, 'total_earnings'));
    $totalDays = count($dailyData);

    // Find best and worst days
    $bestDay = null;
    $worstDay = null;
    $maxOrders = -1;
    $minOrders = PHP_INT_MAX;

    foreach ($dailyData as $day) {
      if ($day['total_orders'] > $maxOrders) {
        $maxOrders = $day['total_orders'];
        $bestDay = $day;
      }
      if ($day['total_orders'] < $minOrders && $day['total_orders'] > 0) {
        $minOrders = $day['total_orders'];
        $worstDay = $day;
      }
    }

    return [
      'total_orders' => $totalOrders,
      'completed_orders' => $totalCompleted,
      'cancelled_orders' => $totalOrders - $totalCompleted,
      'total_earnings' => $totalEarnings,
      'average_daily_orders' => round($totalOrders / $totalDays, 1),
      'average_daily_earnings' => round($totalEarnings / $totalDays, 2),
      'average_completion_rate' => $totalOrders > 0 ? round(($totalCompleted / $totalOrders) * 100, 1) : 0,
      'best_day' => $bestDay ? [
        'date' => $bestDay['date'],
        'day_name' => $bestDay['day_name'],
        'total_orders' => $bestDay['total_orders'],
        'total_earnings' => $bestDay['total_earnings']
      ] : null,
      'worst_day' => $worstDay ? [
        'date' => $worstDay['date'],
        'day_name' => $worstDay['day_name'],
        'total_orders' => $worstDay['total_orders'],
        'total_earnings' => $worstDay['total_earnings']
      ] : null
    ];
  }
}
