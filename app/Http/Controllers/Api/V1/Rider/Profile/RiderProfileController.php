<?php

namespace App\Http\Controllers\Api\V1\Rider\Profile;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Models\RiderAuthentication;
use App\Notifications\PhoneVerificationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RiderProfileController extends Controller
{
  /**
   * Get rider profile
   */
  public function profile(Request $request)
  {
    return response()->json([
      'status' => true,
      'data' => $request->user()
    ]);
  }

  /**
   * Update rider profile
   */
  public function updateProfile(Request $request)
  {
    $rider = $request->user();

    $request->validate([
      'phone_number' => 'sometimes|string|unique:riders,phone_number,' . $rider->id,
      'address' => 'sometimes|string|max:255',
    ]);

    $rider->update($request->only([
      'phone_number',
      'address'
    ]));

    return response()->json([
      'status' => true,
      'message' => 'Profile updated successfully',
      'data' => $rider
    ]);
  }

  /**
   * Update rider name
   */
  public function updateName(Request $request)
  {
    $rider = $request->user();

    $validated = $request->validate([
      'firstname' => 'required|string|max:255',
      'lastname' => 'required|string|max:255',
    ]);

    $rider->first_name = $validated['firstname'];
    $rider->last_name = $validated['lastname'];
    $rider->save();

    return response()->json([
      'success' => true,
      'message' => 'Rider name updated successfully'
    ]);
  }

  /**
   * Update rider phone number with OTP verification
   */
  public function updatePhone(Request $request)
  {
    $rider = $request->user();

    $validated = $request->validate([
      'phone' => 'required|string|max:20|unique:riders,phone_number,' . $rider->id,
    ]);

    DB::beginTransaction();

    try {
      $otpCode = random_int(100000, 999999);
      $rider->phone_number = $validated['phone'];
      $rider->save();

      $authRequest = RiderAuthentication::create([
        'rider_id' => $rider->id,
        'code' => $otpCode,
        'attempts' => 0,
        'created_at' => now(),
      ]);

      $rider->notify(new PhoneVerificationNotification($otpCode));

      DB::commit();

      return response()->json([
        'success' => true,
        'auth_request_id' => $authRequest->id,
        'message' => 'OTP created successfully. Please verify your new phone number.'
      ]);
    } catch (\Exception $e) {
      DB::rollBack();

      Log::error('Phone update failed: ' . $e->getMessage(), [
        'rider_id' => $rider->id
      ]);

      return response()->json([
        'success' => false,
        'message' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
      ], 500);
    }
  }

  /**
   * Verify phone number OTP
   */
  public function verifyPhone(Request $request)
  {
    $rider = $request->user();

    $request->validate([
      'auth_request_id' => 'required|exists:rider_authentications,id',
      'code' => 'required|string|size:6'
    ]);

    $authRequest = RiderAuthentication::where('id', $request->auth_request_id)
      ->where('rider_id', $rider->id)
      ->first();

    if (!$authRequest) {
      return response()->json([
        'success' => false,
        'message' => 'Invalid authentication request'
      ], 404);
    }

    // Check expiry (15 minutes)
    if ($authRequest->created_at->diffInMinutes(now()) > 15) {
      return response()->json([
        'success' => false,
        'message' => 'OTP has expired. Please request a new one.'
      ], 400);
    }

    // Check attempts
    if ($authRequest->attempts >= 5) {
      return response()->json([
        'success' => false,
        'message' => 'Too many failed attempts. Please request a new OTP.'
      ], 400);
    }

    // Verify code
    if ($authRequest->code != $request->code) {
      $authRequest->increment('attempts');

      return response()->json([
        'success' => false,
        'message' => 'Invalid OTP code',
        'remaining_attempts' => 5 - $authRequest->attempts
      ], 400);
    }

    $authRequest->delete();

    return response()->json([
      'success' => true,
      'message' => 'Phone number verified successfully'
    ]);
  }

  /**
   * Update availability status
   */
  public function updateAvailability(Request $request)
  {
    $rider = $request->user();

    $request->validate([
      'is_available' => 'required|boolean'
    ]);

    $rider->is_available = $request->is_available;
    $rider->save();

    return response()->json([
      'success' => true,
      'message' => $request->is_available
        ? 'You are now online and available for deliveries'
        : 'You are now offline',
      'data' => [
        'is_available' => $rider->is_available,
        'status_updated_at' => now()->toIso8601String()
      ]
    ], 200);
  }
}
