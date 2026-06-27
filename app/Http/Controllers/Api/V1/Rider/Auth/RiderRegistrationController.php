<?php

namespace App\Http\Controllers\Api\V1\Rider\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiderRegistrationRequest;
use App\Services\Rider\RiderRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RiderRegistrationController extends Controller
{
  protected RiderRegistrationService $registrationService;

  public function __construct(RiderRegistrationService $registrationService)
  {
    $this->registrationService = $registrationService;
  }

  /**
   * Register a new rider
   */
  public function register(RiderRegistrationRequest $request)
  {
    try {
      $rider = $this->registrationService->register($request->validated());

      return response()->json([
        'status' => true,
        'message' => 'Registration successful. Await admin approval.',
        'data' => [
          'rider_id' => $rider->id,
          'email' => $rider->email,
          'status' => $rider->status->value,
          'status_label' => $rider->status->label()
        ]
      ], 201);
    } catch (\Exception $e) {
      Log::error('Registration failed: ' . $e->getMessage());

      return response()->json([
        'status' => false,
        'message' => 'Registration failed',
        'error' => config('app.debug') ? $e->getMessage() : null
      ], 500);
    }
  }
}
