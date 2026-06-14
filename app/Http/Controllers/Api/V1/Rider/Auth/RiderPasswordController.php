<?php

namespace App\Http\Controllers\Api\V1\Rider\Auth;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\Request;

class RiderPasswordController extends Controller
{
  /**
   * Set password for approved and email-verified rider
   */
  public function setPassword(Request $request)
  {
    $request->validate([
      'email' => 'required|email|exists:riders,email',
      'password' => 'required|min:8|confirmed',
      'password_confirmation' => 'required'
    ]);

    $rider = Rider::where('email', $request->email)->first();

    if (!$rider) {
      return response()->json([
        'status' => false,
        'message' => 'Rider not found'
      ], 404);
    }

    // Check if password is already set
    if (!is_null($rider->password)) {
      return response()->json([
        'status' => false,
        'message' => 'Password already set. Please login instead.',
        'data' => ['can_login' => true]
      ], 400);
    }

    // Check if rider can set password
    if (!$rider->canSetPassword()) {
      return $this->passwordNotAllowedResponse($rider);
    }

    // Set password
    $rider->setPasswordAndActivate($request->password);

    return response()->json([
      'status' => true,
      'message' => 'Password set successfully. You can now login.',
      'data' => ['email' => $rider->email, 'can_login' => true]
    ]);
  }

  protected function passwordNotAllowedResponse(Rider $rider)
  {
    $message = 'Cannot set password. ';

    if (!$rider->isApproved()) {
      $message .= 'Your application is ' . ($rider->isPending() ? 'still pending approval.' : 'has been rejected.');
    } elseif (!$rider->hasVerifiedEmail()) {
      $message .= 'Please verify your email first.';
    }

    return response()->json([
      'status' => false,
      'message' => $message,
      'data' => [
        'is_approved' => $rider->isApproved(),
        'email_verified' => $rider->hasVerifiedEmail(),
        'has_password' => !is_null($rider->password),
        'next_step' => !$rider->hasVerifiedEmail() ? 'verify_email' : 'wait_for_approval'
      ]
    ], 403);
  }
}
