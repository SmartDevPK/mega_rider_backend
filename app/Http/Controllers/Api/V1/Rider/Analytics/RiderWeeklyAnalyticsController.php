<?php

namespace App\Http\Controllers\Api\V1\Rider\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RiderWeeklyAnalyticsController extends Controller
{
  /**
   * Get Rider Weekly Order Counts
   * 
   * Returns weekly order counts for the authenticated rider
   * covering the current month's weekly activity.
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getWeeklyOrderCounts(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $cacheKey = "rider_weekly_orders_{$rider->id}_" . now()->format('Y_m');
      $weeklyData = Cache::remember($cacheKey, 300, function () use ($rider) {
        return $this->fetchWeeklyOrderCounts($rider);
      });

      return response()->json([
        'success' => true,
        'data' => $weeklyData
      ], 200);
    } catch (\Exception $e) {
      Log::error('Weekly order counts error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve weekly order counts'
      ], 500);
    }
  }

  /**
   * Get Rider Weekly Order Analytics with detailed metrics
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getWeeklyOrderAnalytics(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $weeks = $request->input('weeks', 4);
      $cacheKey = "rider_weekly_analytics_{$rider->id}_" . now()->format('Y_m_d');

      $analytics = Cache::remember($cacheKey, 300, function () use ($rider, $weeks) {
        return $this->fetchWeeklyOrderAnalytics($rider, $weeks);
      });

      return response()->json([
        'success' => true,
        'data' => $analytics
      ], 200);
    } catch (\Exception $e) {
      Log::error('Weekly analytics error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve weekly analytics'
      ], 500);
    }
  }

  /**
   * Fetch weekly order counts from database
   *
   * @param Rider $rider
   * @return array
   */
  private function fetchWeeklyOrderCounts($rider): array
  {
    $firstDayOfMonth = now()->startOfMonth();
    $periodStart = $firstDayOfMonth->copy()->startOfWeek();
    $periodEnd = now()->endOfWeek();

    $orderStats = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->whereBetween('created_at', [$periodStart, $periodEnd])
      ->select(
        DB::raw('DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) as week_start'),
        DB::raw('COUNT(*) as total_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as completed_orders'),
        DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN price ELSE 0 END) as total_earnings')
      )
      ->groupBy(DB::raw('week_start'))
      ->orderBy('week_start', 'asc')
      ->get();

    if ($orderStats->isEmpty()) {
      return $this->getEmptyWeeklyResponse($periodStart, $periodEnd);
    }

    $result = [];
    foreach ($orderStats as $stat) {
      $weekStart = $stat->week_start;
      $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
      $totalOrders = (int) $stat->total_orders;
      $completedOrders = (int) $stat->completed_orders;

      $result[] = [
        'week_start' => $weekStart,
        'week_end' => $weekEnd,
        'week_number' => (int) date('W', strtotime($weekStart)),
        'total_orders' => $totalOrders,
        'completed_orders' => $completedOrders,
        'cancelled_orders' => (int) $stat->cancelled_orders,
        'total_earnings' => (float) ($stat->total_earnings ?? 0),
        'completion_rate' => $totalOrders > 0
          ? round(($completedOrders / $totalOrders) * 100, 1)
          : 0
      ];
    }

    return $result;
  }

  /**
   * Fetch weekly order analytics with week-over-week comparison
   *
   * @param Rider $rider
   * @param int $weeks
   * @return array
   */
  private function fetchWeeklyOrderAnalytics($rider, int $weeks): array
  {
    $periodEnd = now();
    $periodStart = now()->subWeeks($weeks - 1)->startOfWeek();

    $orderStats = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->whereBetween('created_at', [$periodStart, $periodEnd])
      ->select(
        DB::raw('DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) as week_start'),
        DB::raw('COUNT(*) as total_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as completed_orders'),
        DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN price ELSE 0 END) as total_earnings')
      )
      ->groupBy(DB::raw('week_start'))
      ->orderBy('week_start', 'asc')
      ->get()
      ->keyBy('week_start');

    $weeklyData = [];
    $currentWeek = $periodStart->copy();

    for ($i = 0; $i < $weeks; $i++) {
      $weekStart = $currentWeek->copy();
      $weekEnd = $currentWeek->copy()->endOfWeek();
      $weekKey = $weekStart->toDateString();

      $stats = $orderStats->get($weekKey);
      $totalOrders = $stats ? (int) $stats->total_orders : 0;
      $completedOrders = $stats ? (int) $stats->completed_orders : 0;
      $totalEarnings = $stats ? (float) $stats->total_earnings : 0;

      $weeklyData[] = [
        'week' => [
          'number' => (int) $weekStart->weekOfYear,
          'start_date' => $weekStart->toDateString(),
          'end_date' => $weekEnd->toDateString(),
          'label' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d, Y')
        ],
        'total_orders' => $totalOrders,
        'completed_orders' => $completedOrders,
        'cancelled_orders' => $stats ? (int) $stats->cancelled_orders : 0,
        'total_earnings' => $totalEarnings,
        'average_order_value' => $completedOrders > 0 ? round($totalEarnings / $completedOrders, 2) : 0,
        'completion_rate' => $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0
      ];

      $currentWeek->addWeek();
    }

    return [
      'summary' => $this->getWeeklySummary($weeklyData),
      'weekly_breakdown' => $weeklyData,
      'growth_metrics' => $this->calculateWeeklyGrowth($weeklyData),
      'best_week' => $this->getBestWeek($weeklyData),
      'trend' => $this->calculateTrend($weeklyData)
    ];
  }

  /**
   * Get empty weekly response with week ranges
   *
   * @param \Carbon\Carbon $periodStart
   * @param \Carbon\Carbon $periodEnd
   * @return array
   */
  private function getEmptyWeeklyResponse($periodStart, $periodEnd): array
  {
    $result = [];
    $currentWeek = $periodStart->copy();

    while ($currentWeek <= $periodEnd) {
      $weekStart = $currentWeek->copy();
      $weekEnd = $currentWeek->copy()->endOfWeek();

      $result[] = [
        'week_start' => $weekStart->toDateString(),
        'week_end' => $weekEnd->toDateString(),
        'week_number' => (int) $weekStart->weekOfYear,
        'total_orders' => 0,
        'completed_orders' => 0,
        'cancelled_orders' => 0,
        'total_earnings' => 0,
        'completion_rate' => 0
      ];

      $currentWeek->addWeek();
    }

    return $result;
  }

  /**
   * Calculate week-over-week growth
   *
   * @param array $weeklyData
   * @return array
   */
  private function calculateWeeklyGrowth(array $weeklyData): array
  {
    $growth = [];

    for ($i = 1; $i < count($weeklyData); $i++) {
      $currentOrders = $weeklyData[$i]['total_orders'];
      $previousOrders = $weeklyData[$i - 1]['total_orders'];
      $currentEarnings = $weeklyData[$i]['total_earnings'];
      $previousEarnings = $weeklyData[$i - 1]['total_earnings'];

      $growth[] = [
        'week' => $weeklyData[$i]['week']['number'],
        'orders_growth' => $previousOrders > 0
          ? round((($currentOrders - $previousOrders) / $previousOrders) * 100, 1)
          : ($currentOrders > 0 ? 100 : 0),
        'earnings_growth' => $previousEarnings > 0
          ? round((($currentEarnings - $previousEarnings) / $previousEarnings) * 100, 1)
          : ($currentEarnings > 0 ? 100 : 0)
      ];
    }

    return $growth;
  }

  /**
   * Get weekly summary statistics
   *
   * @param array $weeklyData
   * @return array
   */
  private function getWeeklySummary(array $weeklyData): array
  {
    $totalOrders = array_sum(array_column($weeklyData, 'total_orders'));
    $totalCompleted = array_sum(array_column($weeklyData, 'completed_orders'));
    $totalEarnings = array_sum(array_column($weeklyData, 'total_earnings'));
    $totalWeeks = count($weeklyData);

    return [
      'total_orders' => $totalOrders,
      'total_completed' => $totalCompleted,
      'total_cancelled' => array_sum(array_column($weeklyData, 'cancelled_orders')),
      'total_earnings' => $totalEarnings,
      'average_weekly_orders' => $totalWeeks > 0 ? round($totalOrders / $totalWeeks, 1) : 0,
      'average_weekly_earnings' => $totalWeeks > 0 ? round($totalEarnings / $totalWeeks, 2) : 0,
      'average_completion_rate' => $totalOrders > 0 ? round(($totalCompleted / $totalOrders) * 100, 1) : 0
    ];
  }

  /**
   * Get best week based on earnings
   *
   * @param array $weeklyData
   * @return array|null
   */
  private function getBestWeek(array $weeklyData): ?array
  {
    if (empty($weeklyData)) {
      return null;
    }

    $bestWeek = null;
    $maxEarnings = -1;

    foreach ($weeklyData as $week) {
      if ($week['total_earnings'] > $maxEarnings) {
        $maxEarnings = $week['total_earnings'];
        $bestWeek = $week;
      }
    }

    return $bestWeek;
  }

  /**
   * Calculate overall trend
   *
   * @param array $weeklyData
   * @return string
   */
  private function calculateTrend(array $weeklyData): string
  {
    if (count($weeklyData) < 2) {
      return 'stable';
    }

    $firstHalf = array_slice($weeklyData, 0, floor(count($weeklyData) / 2));
    $secondHalf = array_slice($weeklyData, floor(count($weeklyData) / 2));

    $firstHalfAvg = array_sum(array_column($firstHalf, 'total_orders')) / count($firstHalf);
    $secondHalfAvg = array_sum(array_column($secondHalf, 'total_orders')) / count($secondHalf);

    if ($secondHalfAvg > $firstHalfAvg * 1.1) {
      return 'increasing';
    } elseif ($secondHalfAvg < $firstHalfAvg * 0.9) {
      return 'decreasing';
    }

    return 'stable';
  }
}
