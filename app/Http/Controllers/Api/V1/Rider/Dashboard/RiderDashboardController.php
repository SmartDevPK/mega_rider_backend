<?php

namespace App\Http\Controllers\Api\V1\Rider\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Rider\RiderActivityService;
use App\Services\RiderDashboardService;
use App\Http\Requests\Rider\RiderActivitiesRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RiderDashboardController extends Controller
{
  protected RiderDashboardService $dashboardService;
  protected RiderActivityService $activityService;

  public function __construct(
    RiderDashboardService $dashboardService,
    RiderActivityService $activityService
  ) {
    $this->dashboardService = $dashboardService;
    $this->activityService = $activityService;
  }

  /**
   * Get rider dashboard
   */
  public function dashboard(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $dashboardData = $this->dashboardService->getDashboardData($rider);

      return response()->json([
        'success' => true,
        'message' => 'Dashboard loaded successfully',
        'data' => [
          'accepted_deliveries' => $dashboardData['accepted_deliveries'],
          'completed_deliveries' => $dashboardData['completed_deliveries'],
          'amount_earned' => (int) $dashboardData['amount_earned'],
          'current_delivery' => $dashboardData['current_delivery'],
          'rider_info' => [
            'id' => $rider->id,
            'name' => $rider->full_name,
            'rating' => $rider->rating,
            'total_trips' => $rider->total_trips ?? 0,
            'is_available' => $rider->is_available
          ]
        ]
      ], 200);
    } catch (\Exception $e) {
      Log::error('Rider dashboard error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to load dashboard'
      ], 500);
    }
  }

  /**
   * Get rider activities
   */
  public function getActivities(RiderActivitiesRequest $request)
  {
    try {
      $this->activityService->initialize($request);
      $result = $this->activityService->getActivities();

      return response()->json($result, 200);
    } catch (\Exception $e) {
      Log::error('Rider activities endpoint error', [
        'rider_id' => auth()->id(),
        'error' => $e->getMessage()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to load activities. Please try again later.',
        'has_next_page' => false,
        'next_cursor' => null,
        'activities' => []
      ], 500);
    }
  }
}
