<?php

namespace App\Http\Controllers\Api\V1\Rider\Delivery;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RiderEarningsController extends Controller
{
  /**
   * Get weekly earnings summary
   */
  public function weeklyEarnings(Request $request)
  {
    try {
      $rider = $request->user();

      $startOfWeek = now()->startOfWeek();
      $endOfWeek = now()->endOfWeek();

      $weeklyEarnings = $rider->riderOrders()
        ->where('status', 'delivered')
        ->whereBetween('delivered_at', [$startOfWeek, $endOfWeek])
        ->sum('price');

      // Get daily breakdown
      $dailyEarnings = [];
      for ($i = 0; $i < 7; $i++) {
        $day = $startOfWeek->copy()->addDays($i);
        $dailyTotal = $rider->riderOrders()
          ->where('status', 'delivered')
          ->whereDate('delivered_at', $day)
          ->sum('price');

        $dailyEarnings[] = [
          'day' => $day->format('l'),
          'date' => $day->toDateString(),
          'earnings' => (float) $dailyTotal,
          'deliveries_count' => $rider->riderOrders()
            ->where('status', 'delivered')
            ->whereDate('delivered_at', $day)
            ->count()
        ];
      }

      // Get previous week comparison
      $lastWeekStart = now()->subWeek()->startOfWeek();
      $lastWeekEnd = now()->subWeek()->endOfWeek();
      $previousWeekEarnings = $rider->riderOrders()
        ->where('status', 'delivered')
        ->whereBetween('delivered_at', [$lastWeekStart, $lastWeekEnd])
        ->sum('price');

      $percentageChange = $previousWeekEarnings > 0
        ? (($weeklyEarnings - $previousWeekEarnings) / $previousWeekEarnings) * 100
        : ($weeklyEarnings > 0 ? 100 : 0);

      return response()->json([
        'success' => true,
        'message' => 'Weekly earnings retrieved successfully',
        'data' => [
          'current_week' => [
            'start_date' => $startOfWeek->toDateString(),
            'end_date' => $endOfWeek->toDateString(),
            'total_earnings' => (float) $weeklyEarnings,
            'daily_breakdown' => $dailyEarnings
          ],
          'comparison' => [
            'previous_week_earnings' => (float) $previousWeekEarnings,
            'percentage_change' => round($percentageChange, 2),
            'trend' => $percentageChange >= 0 ? 'up' : 'down'
          ]
        ]
      ], 200);
    } catch (\Exception $e) {
      Log::error('Weekly earnings error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to load weekly earnings'
      ], 500);
    }
  }
}
