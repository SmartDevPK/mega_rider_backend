<?php

namespace App\Http\Controllers\Api\V1\Rider\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RiderHourlyAnalyticsController extends Controller
{
  /**
   * Get Rider Hourly Order Distribution
   * 
   * Returns hourly order distribution for today
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getHourlyOrderDistribution(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $cacheKey = "rider_hourly_orders_{$rider->id}_" . now()->toDateString();
      $hourlyData = Cache::remember($cacheKey, 300, function () use ($rider) {
        return $this->fetchHourlyOrderDistribution($rider);
      });

      return response()->json([
        'success' => true,
        'data' => $hourlyData
      ], 200);
    } catch (\Exception $e) {
      Log::error('Hourly order distribution error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve hourly distribution'
      ], 500);
    }
  }

  /**
   * Get Hourly Order Distribution by Date Range
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getHourlyOrderDistributionByRange(Request $request)
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
        'date' => 'required|date'
      ]);

      $date = \Carbon\Carbon::parse($request->date);
      $cacheKey = "rider_hourly_orders_{$rider->id}_{$date->toDateString()}";

      $hourlyData = Cache::remember($cacheKey, 300, function () use ($rider, $date) {
        return $this->fetchHourlyOrderDistributionByDate($rider, $date);
      });

      return response()->json([
        'success' => true,
        'data' => [
          'date' => $date->toDateString(),
          'distribution' => $hourlyData
        ]
      ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      Log::error('Hourly distribution by range error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve hourly distribution'
      ], 500);
    }
  }

  /**
   * Get Peak Hours Analysis
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getPeakHoursAnalysis(Request $request)
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
        'end_date' => 'required|date|after_or_equal:start_date'
      ]);

      $startDate = \Carbon\Carbon::parse($request->start_date);
      $endDate = \Carbon\Carbon::parse($request->end_date);

      $cacheKey = "rider_peak_hours_{$rider->id}_{$startDate->toDateString()}_{$endDate->toDateString()}";

      $peakAnalysis = Cache::remember($cacheKey, 300, function () use ($rider, $startDate, $endDate) {
        return $this->calculatePeakHours($rider, $startDate, $endDate);
      });

      return response()->json([
        'success' => true,
        'data' => $peakAnalysis
      ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      Log::error('Peak hours analysis error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve peak hours analysis'
      ], 500);
    }
  }

  /**
   * Fetch hourly order distribution for today
   *
   * @param Rider $rider
   * @return array
   */
  private function fetchHourlyOrderDistribution($rider): array
  {
    $startOfDay = now()->startOfDay();
    $endOfDay = now()->endOfDay();

    $orderStats = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->whereBetween('created_at', [$startOfDay, $endOfDay])
      ->select(
        DB::raw('HOUR(created_at) as hour'),
        DB::raw('COUNT(*) as total_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN price ELSE 0 END) as total_earnings')
      )
      ->groupBy(DB::raw('HOUR(created_at)'))
      ->orderBy('hour', 'asc')
      ->get()
      ->keyBy('hour');

    $result = [];
    for ($hour = 0; $hour < 24; $hour++) {
      $stats = $orderStats->get($hour);
      $hourFormatted = date('g A', strtotime("$hour:00"));

      $result[] = [
        'hour' => $hour,
        'hour_label' => $hourFormatted,
        'total_orders' => $stats ? (int) $stats->total_orders : 0,
        'total_earnings' => $stats ? (float) $stats->total_earnings : 0,
        'time_period' => $this->getTimePeriod($hour)
      ];
    }

    return $result;
  }

  /**
   * Fetch hourly order distribution by specific date
   *
   * @param Rider $rider
   * @param \Carbon\Carbon $date
   * @return array
   */
  private function fetchHourlyOrderDistributionByDate($rider, $date): array
  {
    $startOfDay = $date->copy()->startOfDay();
    $endOfDay = $date->copy()->endOfDay();

    $orderStats = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->whereBetween('created_at', [$startOfDay, $endOfDay])
      ->select(
        DB::raw('HOUR(created_at) as hour'),
        DB::raw('COUNT(*) as total_orders'),
        DB::raw('SUM(CASE WHEN status = "delivered" THEN price ELSE 0 END) as total_earnings')
      )
      ->groupBy(DB::raw('HOUR(created_at)'))
      ->orderBy('hour', 'asc')
      ->get()
      ->keyBy('hour');

    $result = [];
    for ($hour = 0; $hour < 24; $hour++) {
      $stats = $orderStats->get($hour);
      $hourFormatted = date('g A', strtotime("$hour:00"));

      $result[] = [
        'hour' => $hour,
        'hour_label' => $hourFormatted,
        'total_orders' => $stats ? (int) $stats->total_orders : 0,
        'total_earnings' => $stats ? (float) $stats->total_earnings : 0,
        'time_period' => $this->getTimePeriod($hour)
      ];
    }

    return $result;
  }

  /**
   * Calculate peak hours analysis
   *
   * @param Rider $rider
   * @param \Carbon\Carbon $startDate
   * @param \Carbon\Carbon $endDate
   * @return array
   */
  private function calculatePeakHours($rider, $startDate, $endDate): array
  {
    $hourStats = DB::table('orders')
      ->where('rider_id', $rider->id)
      ->whereBetween('created_at', [$startDate, $endDate])
      ->select(
        DB::raw('HOUR(created_at) as hour'),
        DB::raw('COUNT(*) as total_orders'),
        DB::raw('SUM(price) as total_earnings')
      )
      ->groupBy(DB::raw('HOUR(created_at)'))
      ->get();

    if ($hourStats->isEmpty()) {
      return [
        'peak_hour' => null,
        'peak_earnings_hour' => null,
        'hourly_average' => 0,
        'top_hours' => []
      ];
    }

    // Find peak hour (most orders)
    $peakHour = $hourStats->sortByDesc('total_orders')->first();
    // Find peak earnings hour
    $peakEarningsHour = $hourStats->sortByDesc('total_earnings')->first();

    // Get top 5 hours
    $topHours = $hourStats->sortByDesc('total_orders')->take(5)->map(function ($stat) {
      return [
        'hour' => (int) $stat->hour,
        'hour_label' => date('g A', strtotime("{$stat->hour}:00")),
        'total_orders' => (int) $stat->total_orders,
        'total_earnings' => (float) $stat->total_earnings
      ];
    })->values()->toArray();

    return [
      'peak_hour' => [
        'hour' => (int) $peakHour->hour,
        'hour_label' => date('g A', strtotime("{$peakHour->hour}:00")),
        'total_orders' => (int) $peakHour->total_orders,
        'time_period' => $this->getTimePeriod($peakHour->hour)
      ],
      'peak_earnings_hour' => [
        'hour' => (int) $peakEarningsHour->hour,
        'hour_label' => date('g A', strtotime("{$peakEarningsHour->hour}:00")),
        'total_earnings' => (float) $peakEarningsHour->total_earnings
      ],
      'hourly_average' => round($hourStats->avg('total_orders'), 2),
      'top_hours' => $topHours
    ];
  }

  /**
   * Get time period label based on hour
   *
   * @param int $hour
   * @return string
   */
  private function getTimePeriod(int $hour): string
  {
    if ($hour >= 5 && $hour < 12) {
      return 'Morning';
    } elseif ($hour >= 12 && $hour < 17) {
      return 'Afternoon';
    } elseif ($hour >= 17 && $hour < 21) {
      return 'Evening';
    } else {
      return 'Night';
    }
  }
}
