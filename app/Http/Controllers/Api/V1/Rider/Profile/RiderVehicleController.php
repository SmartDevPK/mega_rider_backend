<?php

namespace App\Http\Controllers\Api\V1\Rider\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RiderVehicleController extends Controller
{
  /**
   * Update vehicle details
   */
  public function updateVehicle(Request $request)
  {
    $rider = $request->user();

    if (!$rider) {
      return response()->json([
        'success' => false,
        'message' => 'Account does not exist'
      ], 404);
    }

    $validated = $request->validate([
      'vehicle_name' => 'required|string|max:255',
      'vehicle_color' => 'required|string|max:255',
      'vehicle_number_plate' => 'required|string|max:255|unique:riders,vehicle_plate_number,' . $rider->id,
    ]);

    $rider->vehicle_name = $validated['vehicle_name'];
    $rider->vehicle_color = $validated['vehicle_color'];
    $rider->vehicle_plate_number = $validated['vehicle_number_plate'];
    $rider->save();

    return response()->json([
      'success' => true,
      'message' => 'Rider vehicle details updated successfully'
    ]);
  }
}
