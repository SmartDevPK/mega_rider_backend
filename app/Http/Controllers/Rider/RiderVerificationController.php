<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Services\Rider\RiderService; // Add this
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RiderVerificationController extends Controller
{
    protected RiderService $riderService; // Add this property

    // Add constructor
    public function __construct(RiderService $riderService)
    {
        $this->riderService = $riderService;
    }

    /**
     * Send verification OTP to rider's email
     */
    public function sendVerification(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:riders,email',
            ]);

            $rider = Rider::where('email', $request->email)->first();

            // Check if email already verified
            if ($rider->hasVerifiedEmail()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email already verified. You can proceed to set your password.',
                    'data' => [
                        'email_verified' => true,
                        'can_set_password' => $rider->canSetPassword()
                    ]
                ], 400);
            }

            // Check if rider is approved
            if (!$rider->isApproved()) {
                $status = $rider->isPending() ? 'pending approval' : 'rejected';
                return response()->json([
                    'status' => false,
                    'message' => "Your application is {$status}. You cannot verify email at this time.",
                    'data' => [
                        'application_status' => $rider->status->value
                    ]
                ], 403);
            }

            // FIXED: Use RiderService instead of model method
            $this->riderService->sendVerificationOtp($rider);

            return response()->json([
                'status' => true,
                'message' => 'Verification code sent to your email. Please check your inbox.',
                'data' => [
                    'email' => $rider->email,
                    'expires_in_minutes' => 15,
                    'resend_available_after' => 60
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to send verification OTP', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to send verification code',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Verify OTP and mark email as verified
     */
/**
 * Verify OTP and mark email as verified
 */
public function verifyOtp(Request $request)  // ← CHANGE THIS: only Request parameter
{
    try {
        $request->validate([
            'email' => 'required|email|exists:riders,email',
            'otp' => 'required|string|size:8',
        ]);

        $rider = Rider::where('email', $request->email)->first();

        // Check if already verified
        if ($rider->hasVerifiedEmail()) {
            return response()->json([
                'status' => false,
                'message' => 'Email already verified',
                'data' => [
                    'verified' => true,
                    'can_proceed_to_password' => $rider->canSetPassword()
                ]
            ], 400);
        }

        // Verify OTP using RiderService
        if (!$this->riderService->verifyOtp($rider, $request->otp)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired verification code. Please request a new one.',
                'data' => [
                    'invalid_otp' => true,
                    'remaining_attempts' => $this->riderService->getRemainingAttempts($rider)
                ]
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Email verified successfully. You can now set your password.',
            'data' => [
                'email_verified' => true,
                'can_set_password' => $rider->canSetPassword(),
                'next_step' => 'set_password'
            ]
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        Log::error('OTP verification failed', [
            'email' => $request->email ?? 'unknown',
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Verification failed',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}

public function getRemainingAttempts(Rider $rider): int
{
    return max(0, 5 - $rider->otp_attempts);
}

    /**
     * Resend verification OTP
     */
    public function resendOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:riders,email',
            ]);

            $rider = Rider::where('email', $request->email)->first();

            // Check if already verified
            if ($rider->hasVerifiedEmail()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email already verified',
                ], 400);
            }

            // Check if rider is approved
            if (!$rider->isApproved()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your application must be approved first',
                ], 403);
            }

            // FIXED: Use RiderService instead of model method
            $this->riderService->resendVerificationOtp($rider);

            return response()->json([
                'status' => true,
                'message' => 'New verification code sent to your email.',
                'data' => [
                    'email' => $rider->email,
                    'expires_in_minutes' => 15
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to resend verification code',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}