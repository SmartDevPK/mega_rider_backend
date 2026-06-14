<?php

namespace App\Http\Controllers\Api\V1\Rider\Auth;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Models\RiderAuthentication;
use App\Services\Rider\RiderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RiderVerificationController extends Controller
{
  protected RiderService $riderService;

  public function __construct(RiderService $riderService)
  {
    $this->riderService = $riderService;
  }

  /**
   * Check rider status (approval, verification, password setup)
   */
  public function checkStatus(Request $request)
  {
    $request->validate(['email' => 'required|email']);

    $rider = Rider::where('email', $request->email)->first();

    if (!$rider) {
      return response()->json([
        'status' => false,
        'message' => 'No rider found with this email'
      ], 404);
    }

    $response = $this->buildStatusResponse($rider);

    return response()->json($response);
  }

  /**
   * Resend OTP for Phone Verification
   */
  public function resendPhoneOtp(Request $request)
  {
    $rider = $request->user();

    if (!$rider) {
      return response()->json([
        'success' => false,
        'message' => 'Account does not exist'
      ], 404);
    }

    $request->validate([
      'auth_request_id' => 'required|exists:rider_authentications,id'
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

    DB::beginTransaction();

    try {
      $newOtpCode = random_int(100000, 999999);

      $authRequest->update([
        'code' => $newOtpCode,
        'attempts' => 0,
        'created_at' => now()
      ]);

      DB::commit();

      Log::info('Phone OTP resent', [
        'rider_id' => $rider->id,
        'auth_request_id' => $authRequest->id
      ]);

      return response()->json([
        'success' => true,
        'auth_request_id' => $authRequest->id,
        'message' => 'New OTP sent successfully'
      ]);
    } catch (\Exception $e) {
      DB::rollBack();

      return response()->json([
        'success' => false,
        'message' => 'Something went wrong'
      ], 500);
    }
  }

  /**
   * Build status response based on rider state
   */
  protected function buildStatusResponse(Rider $rider): array
  {
    $response = [
      'status' => true,
      'data' => [
        'email' => $rider->email,
        'status' => $rider->status->value ?? $rider->status,
        'status_label' => ucfirst($rider->status->value ?? $rider->status),
        'email_verified' => $rider->hasVerifiedEmail(),
        'can_set_password' => $rider->canSetPassword(),
        'has_password' => !is_null($rider->password),
        'can_resend_otp' => $rider->isApproved() && !$rider->hasVerifiedEmail(),
        'can_send_verification' => $rider->isApproved() && !$rider->hasVerifiedEmail()
      ]
    ];

    // Add OTP status if email not verified
    if ($rider->isApproved() && !$rider->hasVerifiedEmail()) {
      $response['data']['otp_expired'] = $this->riderService->isOtpExpired($rider);
      $response['data']['otp_remaining_attempts'] = $this->riderService->getRemainingAttempts($rider);
    }

    // Add rejection reason if rejected
    if ($rider->isRejected() && $rider->rejection_reason) {
      $response['data']['rejection_reason'] = $rider->rejection_reason;
      $response['message'] = 'Your application has been rejected. Reason: ' . $rider->rejection_reason;
    } elseif ($rider->isApproved()) {
      $this->addApprovedStatusMessage($response, $rider);
    } elseif ($rider->isPending()) {
      $response['message'] = 'Your application is pending admin approval.';
      $response['data']['next_step'] = 'wait_for_approval';
    } elseif ($rider->isRejected()) {
      $response['message'] = 'Your application has been rejected.';
      $response['data']['next_step'] = 'contact_support';
    }

    return $response;
  }

  /**
   * Add approved status message based on next steps
   */
  protected function addApprovedStatusMessage(array &$response, Rider $rider): void
  {
    if (!$rider->hasVerifiedEmail()) {
      $response['message'] = 'Your account is approved. Please verify your email first.';
      $response['data']['next_step'] = 'verify_email';
    } elseif (is_null($rider->password)) {
      $response['message'] = 'Your account is approved and email verified. Please set your password.';
      $response['data']['next_step'] = 'set_password';
    } else {
      $response['message'] = 'Your account is active. You can now login.';
      $response['data']['next_step'] = 'login';
    }
  }
}
