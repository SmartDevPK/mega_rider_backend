<?php

namespace App\Http\Controllers\Api\V1\Rider\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RiderMonthlyAnalyticsController extends Controller
{
  /**
   * Get Rider Monthly Order Counts
   * 
   * Returns monthly order counts for the authenticated rider
   * starting from the current month until the end of the current year.
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getMonthlyOrderCounts(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $cacheKey = "rider_monthly_orders_{$rider->id}_" . now()->year;
      $monthlyData = Cache::remember($cacheKey, 300, function () use ($rider) {
        return $this->fetchMonthlyOrderCounts($rider);
      });

      return response()->json([
        'success' => true,
        'data' => $monthlyData
      ], 200);
    } catch (\Exception $e) {
      Log::error('Monthly order counts error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve monthly order counts'
      ], 500);
    }
  }

  /**
   * Get Full Year Monthly Order Counts
   * 
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getFullYearMonthlyOrderCounts(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $year = $request->input('year', now()->year);
      $cacheKey = "rider_full_year_orders_{$rider->id}_{$year}";

      $monthlyData = Cache::remember($cacheKey, 300, function () use ($rider, $year) {
        return $this->fetchFullYearOrderCounts($rider, $year);
      });

      return response()->json([
        'success' => true,
        'data' => [
          'year' => (int) $year,
          'summary' => $this->getYearlySummary($rider, $year),
          'monthly_breakdown' => $monthlyData
        ]
      ], 200);
    } catch (\Exception $e) {
      Log::error('Full year order counts error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve yearly order counts'
      ], 500);
    }
  }

  /**
   * Fetch monthly order counts from database
   *
   * @param Rider $rider
   * @return array
   */
  private function fetchMonthlyOrderCounts($rider): array
  {
    $currentYear = now()->year;
    $currentMonth = now()->month;

    $orderStats = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->whereYear('created_at', $currentYear)
      ->whereMonth('created_at', '>=', $currentMonth)
      ->select(
        DB::raw('MONTH(created_at) as month'),
        DB::raw('COUNT(*) as total_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as completed_orders'),
        DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN price ELSE 0 END) as total_earnings')
      )
      ->groupBy(DB::raw('MONTH(created_at)'))
      ->orderBy('month', 'asc')
      ->get();

    if ($orderStats->isEmpty()) {
      return [];
    }

    $result = [];
    foreach ($orderStats as $stat) {
      $result[] = [
        'month' => (int) $stat->month,
        'month_name' => $this->getMonthName($stat->month),
        'total_orders' => (int) $stat->total_orders,
        'completed_orders' => (int) $stat->completed_orders,
        'cancelled_orders' => (int) $stat->cancelled_orders,
        'total_earnings' => (float) ($stat->total_earnings ?? 0),
        'completion_rate' => $stat->total_orders > 0
          ? round(($stat->completed_orders / $stat->total_orders) * 100, 1)
          : 0
      ];
    }

    return $result;
  }

  /**
   * Fetch full year order counts
   *
   * @param Rider $rider
   * @param int $year
   * @return array
   */
  private function fetchFullYearOrderCounts($rider, int $year): array
  {
    $orderStats = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->whereYear('created_at', $year)
      ->select(
        DB::raw('MONTH(created_at) as month'),
        DB::raw('COUNT(*) as total_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as completed_orders'),
        DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN price ELSE 0 END) as total_earnings')
      )
      ->groupBy(DB::raw('MONTH(created_at)'))
      ->orderBy('month', 'asc')
      ->get()
      ->keyBy('month');

    $allMonths = [];
    for ($month = 1; $month <= 12; $month++) {
      $stats = $orderStats->get($month);

      $allMonths[] = [
        'month' => $month,
        'month_name' => $this->getMonthName($month),
        'total_orders' => (int) ($stats->total_orders ?? 0),
        'completed_orders' => (int) ($stats->completed_orders ?? 0),
        'cancelled_orders' => (int) ($stats->cancelled_orders ?? 0),
        'total_earnings' => (float) ($stats->total_earnings ?? 0),
        'completion_rate' => ($stats->total_orders ?? 0) > 0
          ? round(($stats->completed_orders / $stats->total_orders) * 100, 1)
          : 0
      ];
    }

    return $allMonths;
  }

  /**
   * Get yearly summary statistics
   *
   * @param Rider $rider
   * @param int $year
   * @return array
   */
  private function getYearlySummary($rider, int $year): array
  {
    $summary = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->whereYear('created_at', $year)
      ->select(
        DB::raw('COUNT(*) as total_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as completed_orders'),
        DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN price ELSE 0 END) as total_earnings'),
        DB::raw('AVG(CASE WHEN status = "delivered" THEN price ELSE NULL END) as average_order_value')
      )
      ->first();

    $totalOrders = (int) ($summary->total_orders ?? 0);
    $completedOrders = (int) ($summary->completed_orders ?? 0);

    return [
      'total_orders' => $totalOrders,
      'completed_orders' => $completedOrders,
      'cancelled_orders' => (int) ($summary->cancelled_orders ?? 0),
      'total_earnings' => (float) ($summary->total_earnings ?? 0),
      'average_order_value' => (float) ($summary->average_order_value ?? 0),
      'completion_rate' => $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0,
      'best_month' => $this->getBestMonth($rider->id, $year),
      'worst_month' => $this->getWorstMonth($rider->id, $year)
    ];
  }

  /**
   * Get best month (highest earnings)
   *
   * @param int $riderId
   * @param int $year
   * @return array|null
   */
  private function getBestMonth(int $riderId, int $year): ?array
  {
    $bestMonth = DB::table('orders')
      ->where('rider_id', $riderId)
      ->whereYear('created_at', $year)
      ->where('status', 'delivered')
      ->select(
        DB::raw('MONTH(created_at) as month'),
        DB::raw('SUM(price) as total_earnings'),
        DB::raw('COUNT(*) as total_orders')
      )
      ->groupBy(DB::raw('MONTH(created_at)'))
      ->orderBy('total_earnings', 'desc')
      ->first();

    if (!$bestMonth) {
      return null;
    }

    return [
      'month' => (int) $bestMonth->month,
      'month_name' => $this->getMonthName($bestMonth->month),
      'total_earnings' => (float) $bestMonth->total_earnings,
      'total_orders' => (int) $bestMonth->total_orders
    ];
  }

  /**
   * Get worst month (lowest earnings)
   *
   * @param int $riderId
   * @param int $year
   * @return array|null
   */
  private function getWorstMonth(int $riderId, int $year): ?array
  {
    $worstMonth = DB::table('orders')
      ->where('rider_id', $riderId)
      ->whereYear('created_at', $year)
      ->where('status', 'delivered')
      ->select(
        DB::raw('MONTH(created_at) as month'),
        DB::raw('SUM(price) as total_earnings'),
        DB::raw('COUNT(*) as total_orders')
      )
      ->groupBy(DB::raw('MONTH(created_at)'))
      ->orderBy('total_earnings', 'asc')
      ->first();

    if (!$worstMonth) {
      return null;
    }

    return [
      'month' => (int) $worstMonth->month,
      'month_name' => $this->getMonthName($worstMonth->month),
      'total_earnings' => (float) $worstMonth->total_earnings,
      'total_orders' => (int) $worstMonth->total_orders
    ];
  }

  /**
   * Get month name from month number
   *
   * @param int $monthNumber
   * @return string
   */
  private function getMonthName(int $monthNumber): string
  {
    return \DateTime::createFromFormat('!m', $monthNumber)->format('F');
  }
}
