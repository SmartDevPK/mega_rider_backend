<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;

class RiderAuthController extends Controller
{
    /**
     * Show login form (API response)
     */
    public function showLoginForm()
    {
        return response()->json([
            'status' => true,
            'message' => 'Please provide your credentials to login.',
            'data' => [
                'required_fields' => ['email', 'password'],
                'example' => [
                    'email' => 'rider@example.com',
                    'password' => 'your_password'
                ]
            ]
        ]);
    }

    /**
     * Handle rider login and issue token
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'device_name' => 'nullable|string|max:255',
                'fcm_token' => 'nullable|string'
            ]);

            // Find rider by email
            $rider = Rider::where('email', $request->email)->first();

            // Check if rider exists
            if (!$rider) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            // Check if rider can login
            if (!$rider->canLogin()) {
                $errorMessage = 'Cannot login. ';
                
                if (!$rider->isApproved()) {
                    $errorMessage .= 'Your application is ' . ($rider->isPending() ? 'still pending approval.' : 'has been rejected.');
                } elseif (!$rider->hasVerifiedEmail()) {
                    $errorMessage .= 'Please verify your email address first.';
                } elseif (is_null($rider->password)) {
                    $errorMessage .= 'Please set your password first.';
                }
                
                return response()->json([
                    'status' => false,
                    'message' => $errorMessage,
                    'data' => [
                        'is_approved' => $rider->isApproved(),
                        'email_verified' => $rider->hasVerifiedEmail(),
                        'has_password' => !is_null($rider->password),
                        'next_step' => $this->getNextStep($rider)
                    ]
                ], 403);
            }

            // Verify password
            if (!Hash::check($request->password, $rider->password)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            // Generate token
            $deviceName = $request->device_name ?? $request->userAgent() ?? 'unknown_device';
            $token = $rider->createToken($deviceName)->plainTextToken;
            
            // Store FCM token if provided
            if ($request->fcm_token) {
                $rider->fcm_token = $request->fcm_token;
                $rider->save();
            }

            // Update login tracking
            $rider->last_login_at = now();
            $rider->last_login_ip = $request->ip();
            $rider->save();

            // Log login activity
            Log::info('Rider logged in', [
                'rider_id' => $rider->id,
                'email' => $rider->email,
                'ip' => $request->ip(),
                'device' => $deviceName
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Login successful',
                'data' => [
                    'rider' => [
                        'id' => $rider->id,
                        'first_name' => $rider->first_name,
                        'last_name' => $rider->last_name,
                        'email' => $rider->email,
                        'phone_number' => $rider->phone_number,
                        'vehicle_type' => $rider->vehicle_type,
                        'vehicle_plate_number' => $rider->vehicle_plate_number,
                        'status' => $rider->status->value,
                        'is_approved' => $rider->isApproved(),
                        'has_completed_profile' => $this->hasCompletedProfile($rider)
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration', 20160)
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Login failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Login failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Logout rider and revoke token
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            
            Log::info('Rider logged out', [
                'rider_id' => $request->user()->id,
                'email' => $request->user()->email
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Logged out successfully'
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Logout failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'status' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Refresh token
     */
    public function refreshToken(Request $request)
    {
        try {
            $user = $request->user();
            
            // Revoke current token
            $user->currentAccessToken()->delete();
            
            // Create new token
            $deviceName = $request->device_name ?? $request->userAgent() ?? 'unknown_device';
            $newToken = $user->createToken($deviceName)->plainTextToken;
            
            return response()->json([
                'status' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $newToken,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration', 20160)
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Token refresh failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to refresh token'
            ], 500);
        }
    }

    /**
     * Get authenticated rider details
     */
    public function me(Request $request)
    {
        $rider = $request->user();
        
        return response()->json([
            'status' => true,
            'data' => [
                'rider' => [
                    'id' => $rider->id,
                    'first_name' => $rider->first_name,
                    'last_name' => $rider->last_name,
                    'full_name' => $rider->full_name,
                    'email' => $rider->email,
                    'phone_number' => $rider->phone_number,
                    'gender' => $rider->gender,
                    'address' => $rider->address,
                    'vehicle_type' => $rider->vehicle_type,
                    'vehicle_color' => $rider->vehicle_color,
                    'vehicle_plate_number' => $rider->vehicle_plate_number,
                    'status' => $rider->status->value,
                    'is_approved' => $rider->isApproved(),
                    'email_verified' => $rider->hasVerifiedEmail(),
                    'image_url' => $rider->image_url,
                    'created_at' => $rider->created_at,
                    'last_login_at' => $rider->last_login_at
                ]
            ]
        ]);
    }

    /**
     * Change password (authenticated)
     */
    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
                'new_password_confirmation' => 'required|string|min:8'
            ]);

            $rider = $request->user();

            // Verify current password
            if (!Hash::check($request->current_password, $rider->password)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            // Update password
            $rider->password = Hash::make($request->new_password);
            $rider->save();

            Log::info('Rider changed password', [
                'rider_id' => $rider->id,
                'email' => $rider->email
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Password changed successfully'
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to change password'
            ], 500);
        }
    }

    /**
     * Forgot password - send reset OTP
     */
   /**
 * Forgot password - send reset OTP
 */
public function forgotPassword(Request $request)
{
    try {
        $request->validate([
            'email' => 'required|email|exists:riders,email'
        ]);

        $rider = Rider::where('email', $request->email)->first();

        // Generate and store alphanumeric token in database
        $resetToken = $rider->generatePasswordResetToken();
        
        // Always send email - even in development
        try {
            Mail::send('emails.password-reset-otp', [
                'firstName' => $rider->first_name,
                'lastName' => $rider->last_name,
                'token' => $resetToken,
                'email' => $rider->email,
                'expires_in_minutes' => 15
            ], function ($message) use ($rider) {
                $message->to($rider->email)
                        ->subject('Password Reset Code - ' . config('app.name'));
            });
            
            Log::info('Password reset token sent', [
                'email' => $rider->email,
                'token' => $resetToken // Remove in production
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'email' => $rider->email,
                'error' => $e->getMessage()
            ]);
            
            // Still return success but with error note
            if (config('app.debug')) {
                return response()->json([
                    'status' => true,
                    'message' => 'Password reset code generated but email sending failed',
                    'data' => [
                        'email' => $rider->email,
                        'reset_token' => $resetToken, // Only in debug mode
                        'expires_in_minutes' => 15,
                        'email_error' => $e->getMessage()
                    ]
                ], 200);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Password reset code sent to your email',
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
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('Forgot password failed', [
            'email' => $request->email,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'status' => false,
            'message' => 'Failed to process request',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

     
      /**
     * Resend password reset token
     */
    public function resendResetToken(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:riders,email'
            ]);

            $rider = Rider::where('email', $request->email)->first();
            
            // Clear existing token
            $rider->clearPasswordResetToken();
            
            // Generate new token
            $resetToken = $rider->generatePasswordResetToken();
            
            if (config('app.env') === 'local') {
                return response()->json([
                    'status' => true,
                    'message' => 'New reset code generated (testing mode)',
                    'data' => [
                        'email' => $rider->email,
                        'reset_token' => $resetToken,
                        'expires_in_minutes' => 15
                    ]
                ], 200);
            }
            
            // Send email
            Mail::send('emails.password-reset-otp', [
                'firstName' => $rider->first_name,
                'lastName' => $rider->last_name,
                'token' => $resetToken,
                'email' => $rider->email,
                'expires_in_minutes' => 15
            ], function ($message) use ($rider) {
                $message->to($rider->email)
                        ->subject('New Password Reset Code - ' . config('app.name'));
            });
            
            return response()->json([
                'status' => true,
                'message' => 'New reset code sent to your email',
                'data' => [
                    'email' => $rider->email,
                    'expires_in_minutes' => 15
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to resend reset code'
            ], 500);
        }
    }

    /**
     * Resend password reset OTP
     */
    public function resendResetOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:riders,email'
            ]);

            $rider = Rider::where('email', $request->email)->first();
            
            // Clear existing OTP
            Cache::forget('password_reset_' . $rider->email);
            Cache::forget('password_reset_attempts_' . $rider->email);
            
            // Generate new OTP
            $resetOtp = $this->generateResetOtp();
            
            // Store new OTP
            Cache::put('password_reset_' . $rider->email, $resetOtp, now()->addMinutes(15));
            
            if (config('app.env') === 'local') {
                return response()->json([
                    'status' => true,
                    'message' => 'New reset code generated (testing mode)',
                    'data' => [
                        'email' => $rider->email,
                        'otp' => $resetOtp,
                        'expires_in_minutes' => 15
                    ]
                ], 200);
            }
            
            // Send email
            Mail::send('emails.password-reset-otp', [
                'firstName' => $rider->first_name,
                'lastName' => $rider->last_name,
                'otp' => $resetOtp,
                'email' => $rider->email,
                'expires_in_minutes' => 15
            ], function ($message) use ($rider) {
                $message->to($rider->email)
                        ->subject('New Password Reset Code - ' . config('app.name'));
            });
            
            return response()->json([
                'status' => true,
                'message' => 'New reset code sent to your email',
                'data' => [
                    'email' => $rider->email,
                    'expires_in_minutes' => 15
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to resend reset code'
            ], 500);
        }
    }

    /**
     * Verify token validity
     */
    public function verifyToken(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'status' => true,
            'message' => 'Token is valid',
            'data' => [
                'valid' => true,
                'rider_id' => $user->id,
                'email' => $user->email
            ]
        ]);
    }

    /**
     * Get next step for rider based on current status
     */
    private function getNextStep(Rider $rider): string
    {
        if (!$rider->isApproved()) {
            return 'wait_for_approval';
        }
        if (!$rider->hasVerifiedEmail()) {
            return 'verify_email';
        }
        if (is_null($rider->password)) {
            return 'set_password';
        }
        return 'login';
    }

    /**
     * Check if rider has completed profile
     */
    private function hasCompletedProfile(Rider $rider): bool
    {
        return !empty($rider->vehicle_type) 
            && !empty($rider->vehicle_plate_number)
            && !empty($rider->address);
    }

    /**
     * Generate 6-digit OTP
     */
    private function generateResetOtp(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

      public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:riders,email',
                'token' => 'required|string|size:8',
                'password' => 'required|string|min:8|confirmed',
                'password_confirmation' => 'required|string|min:8'
            ]);

            $rider = Rider::where('email', $request->email)->first();
            
            if (!$rider) {
                return response()->json([
                    'status' => false,
                    'message' => 'Rider not found'
                ], 404);
            }
            
            // Verify token using the model method
            if (!$rider->verifyPasswordResetToken($request->token)) {
                $remainingAttempts = $rider->getRemainingResetAttempts();
                
                if ($remainingAttempts === 0) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many failed attempts. Please request a new reset code.'
                    ], 422);
                }
                
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid or expired reset code',
                    'data' => [
                        'remaining_attempts' => $remainingAttempts
                    ]
                ], 422);
            }

            // Reset password
            $rider->password = Hash::make($request->password);
            $rider->save();
            
            // Clear reset token
            $rider->clearPasswordResetToken();
            
            // Revoke all tokens for security (if using Sanctum)
            if (method_exists($rider, 'tokens')) {
                $rider->tokens()->delete();
            }

            Log::info('Password reset successfully', [
                'email' => $rider->email,
                'rider_id' => $rider->id
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Password reset successfully. Please login with your new password.',
                'data' => [
                    'email' => $rider->email,
                    'can_login' => true
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Reset password failed', [
                'email' => $request->email ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to reset password',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

     /**
     * Get authenticated rider profile
     */
    public function profile(Request $request)
    {
        try {
            $rider = $request->user();
            
            if (!$rider) {
                return response()->json([
                    'status' => false,
                    'message' => 'Rider not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Profile retrieved successfully',
                'data' => [
                    'rider' => [
                        // Personal Information
                        'id' => $rider->id,
                        'first_name' => $rider->first_name,
                        'last_name' => $rider->last_name,
                        'full_name' => $rider->full_name,
                        'email' => $rider->email,
                        'phone_number' => $rider->phone_number,
                        'gender' => $rider->gender,
                        'address' => $rider->address,
                        'nin' => $rider->nin,
                        
                        // Vehicle Information
                        'vehicle_type' => $rider->vehicle_type,
                        'vehicle_color' => $rider->vehicle_color,
                        'vehicle_plate_number' => $rider->vehicle_plate_number,
                        'driver_license_number' => $rider->driver_license_number,
                        
                        // Files/Images
                        'image_url' => $rider->image_url,
                        'proof_of_address_url' => $rider->proof_of_address_url,
                        'driver_license_url' => $rider->driver_license_url,
                        
                        // Guarantor Information
                        'guarantor' => [
                            'name' => $rider->guarantor_name,
                            'phone' => $rider->guarantor_phone,
                            'relationship' => $rider->guarantor_relationship,
                            'address' => $rider->guarantor_address,
                            'occupation' => $rider->guarantor_occupation,
                        ],
                        
                        // Next of Kin
                        'next_of_kin' => [
                            'name' => $rider->nok_name,
                            'phone' => $rider->nok_phone,
                            'relationship' => $rider->nok_relationship,
                            'address' => $rider->nok_address,
                        ],
                        
                        // Work History
                        'work_history' => [
                            'previous_place_of_work' => $rider->previous_place_of_work,
                            'years_of_work' => $rider->years_of_work,
                        ],
                        
                        // Account Status
                        'status' => [
                            'value' => $rider->status->value,
                            'label' => ucfirst($rider->status->value),
                            'is_approved' => $rider->isApproved(),
                            'is_pending' => $rider->isPending(),
                            'is_rejected' => $rider->isRejected(),
                        ],
                        
                        // Verification Status
                        'verification' => [
                            'email_verified' => $rider->hasVerifiedEmail(),
                            'email_verified_at' => $rider->email_verified_at,
                            'profile_completed' => $rider->hasCompletedProfile(),
                            'profile_completion_percentage' => $rider->getProfileCompletionPercentage(),
                        ],
                        
                        // Timestamps
                        'created_at' => $rider->created_at,
                        'updated_at' => $rider->updated_at,
                        'last_login_at' => $rider->last_login_at,
                        'password_set_at' => $rider->password_set_at,
                    ]
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch rider profile', [
                'rider_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch profile',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update rider profile (authenticated)
     */
       /**
     * Update Guarantor Information
     */
    public function updateGuarantor(Request $request)
    {
        try {
            $rider = $request->user();
            
            $request->validate([
                'guarantor_name' => 'required|string|max:255',
                'guarantor_phone' => 'required|string|max:20',
                'guarantor_relationship' => 'required|string|max:50',
                'guarantor_address' => 'nullable|string|max:500',
                'guarantor_occupation' => 'nullable|string|max:255',
            ]);

            $rider->update([
                'guarantor_name' => $request->guarantor_name,
                'guarantor_phone' => $request->guarantor_phone,
                'guarantor_relationship' => $request->guarantor_relationship,
                'guarantor_address' => $request->guarantor_address,
                'guarantor_occupation' => $request->guarantor_occupation,
            ]);

            Log::info('Guarantor information updated', [
                'rider_id' => $rider->id,
                'email' => $rider->email
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Guarantor information updated successfully',
                'data' => [
                    'guarantor' => [
                        'name' => $rider->guarantor_name,
                        'phone' => $rider->guarantor_phone,
                        'relationship' => $rider->guarantor_relationship,
                        'address' => $rider->guarantor_address,
                        'occupation' => $rider->guarantor_occupation,
                    ]
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update guarantor information', [
                'rider_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to update guarantor information',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update Next of Kin Information
     */
    public function updateNextOfKin(Request $request)
    {
        try {
            $rider = $request->user();
            
            $request->validate([
                'nok_name' => 'required|string|max:255',
                'nok_phone' => 'required|string|max:20',
                'nok_relationship' => 'required|string|max:50',
                'nok_address' => 'nullable|string|max:500',
            ]);

            $rider->update([
                'nok_name' => $request->nok_name,
                'nok_phone' => $request->nok_phone,
                'nok_relationship' => $request->nok_relationship,
                'nok_address' => $request->nok_address,
            ]);

            Log::info('Next of kin information updated', [
                'rider_id' => $rider->id,
                'email' => $rider->email
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Next of kin information updated successfully',
                'data' => [
                    'next_of_kin' => [
                        'name' => $rider->nok_name,
                        'phone' => $rider->nok_phone,
                        'relationship' => $rider->nok_relationship,
                        'address' => $rider->nok_address,
                    ]
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update next of kin information', [
                'rider_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to update next of kin information',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update both Guarantor and Next of Kin together
     */
    public function updateGuarantorAndNextOfKin(Request $request)
    {
        try {
            $rider = $request->user();
            
            $request->validate([
                // Guarantor Validation
                'guarantor_name' => 'required|string|max:255',
                'guarantor_phone' => 'required|string|max:20',
                'guarantor_relationship' => 'required|string|max:50',
                'guarantor_address' => 'nullable|string|max:500',
                'guarantor_occupation' => 'nullable|string|max:255',
                
                // Next of Kin Validation
                'nok_name' => 'required|string|max:255',
                'nok_phone' => 'required|string|max:20',
                'nok_relationship' => 'required|string|max:50',
                'nok_address' => 'nullable|string|max:500',
            ]);

            // Update both
            $rider->update([
                'guarantor_name' => $request->guarantor_name,
                'guarantor_phone' => $request->guarantor_phone,
                'guarantor_relationship' => $request->guarantor_relationship,
                'guarantor_address' => $request->guarantor_address,
                'guarantor_occupation' => $request->guarantor_occupation,
                'nok_name' => $request->nok_name,
                'nok_phone' => $request->nok_phone,
                'nok_relationship' => $request->nok_relationship,
                'nok_address' => $request->nok_address,
            ]);

            Log::info('Guarantor and next of kin information updated', [
                'rider_id' => $rider->id,
                'email' => $rider->email
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Guarantor and next of kin information updated successfully',
                'data' => [
                    'guarantor' => [
                        'name' => $rider->guarantor_name,
                        'phone' => $rider->guarantor_phone,
                        'relationship' => $rider->guarantor_relationship,
                        'address' => $rider->guarantor_address,
                        'occupation' => $rider->guarantor_occupation,
                    ],
                    'next_of_kin' => [
                        'name' => $rider->nok_name,
                        'phone' => $rider->nok_phone,
                        'relationship' => $rider->nok_relationship,
                        'address' => $rider->nok_address,
                    ]
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update guarantor and next of kin', [
                'rider_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to update information',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get Guarantor Information
     */
    public function getGuarantor(Request $request)
    {
        try {
            $rider = $request->user();
            
            return response()->json([
                'status' => true,
                'message' => 'Guarantor information retrieved successfully',
                'data' => [
                    'guarantor' => [
                        'name' => $rider->guarantor_name,
                        'phone' => $rider->guarantor_phone,
                        'relationship' => $rider->guarantor_relationship,
                        'address' => $rider->guarantor_address,
                        'occupation' => $rider->guarantor_occupation,
                    ]
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch guarantor information', [
                'rider_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch guarantor information'
            ], 500);
        }
    }

    /**
     * Get Next of Kin Information
     */
    public function getNextOfKin(Request $request)
    {
        try {
            $rider = $request->user();
            
            return response()->json([
                'status' => true,
                'message' => 'Next of kin information retrieved successfully',
                'data' => [
                    'next_of_kin' => [
                        'name' => $rider->nok_name,
                        'phone' => $rider->nok_phone,
                        'relationship' => $rider->nok_relationship,
                        'address' => $rider->nok_address,
                    ]
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch next of kin information', [
                'rider_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch next of kin information'
            ], 500);
        }
    }
}