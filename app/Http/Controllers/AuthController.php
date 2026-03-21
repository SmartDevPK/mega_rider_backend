<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use App\Services\LoginService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Exception;
use GeoIP;

class AuthController extends Controller
{
    private UserService $userService;
    private LoginService $loginService;

    public function __construct(UserService $userService, LoginService $loginService)
    {
        $this->userService = $userService;
        $this->loginService = $loginService;
    }

    // =========================================================================
    // Email Check
    // =========================================================================
    
    /**
     * Check if email exists in the system
     * 
     * @param Request $request
     * @return JsonResponse
     */
 public function checkEmail(Request $request): JsonResponse
{
    try {
        // Validate request
        $validated = $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            // User exists, check if email is verified
            $emailVerified = $user->is_verified; // assumes you have 'is_verified' field

            return response()->json([
                'success' => true,
                'status' => 'login',
                'message' => $emailVerified 
                    ? 'Email exists and is verified. Proceed to login.'
                    : 'Email exists but is not verified. Please verify first.',
                'data' => [
                    'email' => $user->email,
                    'name' => $user->firstname . ' ' . $user->lastname,
                    'is_verified' => $emailVerified,
                ],
            ], 200);
        }

        // Email not found, prompt for registration
        return response()->json([
            'success' => true,
            'status' => 'register',
            'message' => 'Email not found. Proceed to register.',
            'data' => [
                'email' => $validated['email'],
            ],
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'status' => 'error',
            'message' => 'Failed to check email',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}

    // =========================================================================
    // Registration
    // =========================================================================
    
    /**
     * Register a new user
     * 
     * @param Request $request
     * @return JsonResponse
     */

public function register(Request $request): JsonResponse
{
    // -------------------------------
    // 🌍 GeoIP: Restrict to Nigeria
    // -------------------------------
    $ip = $request->ip();
    $location = geoip($ip); // requires torann/geoip package

    if ($location->iso_code !== 'NG') {
        return response()->json([
            'success' => false,
            'message' => 'This app is only available in Nigeria.'
        ], 403);
    }

    try {
        // -------------------------------
        //  Validate request input
        // -------------------------------
        $validated = $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname'  => 'required|string|max:255',
            'phoneNumber' => [
                'required',
                'string',
                'max:20',
                'unique:users',
                'regex:/^(?:\+234|0)[789][01]\d{8}$/', // Nigerian numbers
            ],
            'email' => 'required|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',      // lowercase
                'regex:/[A-Z]/',      // uppercase
                'regex:/[0-9]/',      // number
                'regex:/[@$!%*?&]/',  // special character
            ],
            'referralCode' => 'nullable|string|exists:users,referralCode',
        ]);

        // -------------------------------
        // ☎ Normalize phone number to +234
        // -------------------------------
        if (Str::startsWith($validated['phoneNumber'], '0')) {
            $validated['phoneNumber'] = '+234' . substr($validated['phoneNumber'], 1);
        }

        // -------------------------------
        //  Block non-Nigerian phone numbers
        // -------------------------------
        if (!Str::startsWith($validated['phoneNumber'], '+234')) {
            return response()->json([
                'success' => false,
                'message' => 'Only Nigerian phone numbers are allowed.'
            ], 403);
        }

        // -------------------------------
        //  Register user via service
        // -------------------------------
        $user = $this->userService->register($validated);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please check your email for verification code.',
            'data' => [
                'user' => $user->only([
                    'id',
                    'firstname',
                    'lastname',
                    'email',
                    'phoneNumber',
                    'is_verified'
                ]),
            ],
        ], 201);

    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Registration failed',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}

//--------------------------------------
//GETUSERINFO
//--------------------------------------

public function getUserInfo(Request $request): JsonResponse
{
    $emailOrPhone = $request->input('identifier'); // email or phone number

    $user = \App\Models\User::where('email', $emailOrPhone)
                ->orWhere('phoneNumber', $emailOrPhone)
                ->first();

    if ($user) {
        return response()->json([
            'success' => true,
            'data' => [
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'phoneNumber' => $user->phoneNumber
            ]
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'No existing user found'
    ]);
}


    // =========================================================================
    // Email Verification
    // =========================================================================
    
    /**
     * Verify user's email with OTP
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'otp' => 'required|string|size:8',
            ]);

            $user = $this->userService->verifyEmail(
                $validated['email'], 
                $validated['otp']
            );

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully.',
                'data' => [
                    'user' => $user->only([
                        'id',
                        'firstname',
                        'lastname',
                        'email',
                        'is_verified',
                        'email_verified_at'
                    ])
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during verification',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // =========================================================================
    // Resend Verification
    // =========================================================================
    
    /**
     * Resend verification code to user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function resendVerification(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $user = User::where('email', $validated['email'])->firstOrFail();

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified',
                ], 400);
            }

            $this->userService->resendVerification($user);

            return response()->json([
                'success' => true,
                'message' => 'Verification code resent successfully.',
                'data' => [
                    'email' => $user->email,
                    'resend_at' => now(),
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not resend verification code',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // =========================================================================
    // Login
    // =========================================================================
    
    /**
     * Authenticate user and generate token
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:8',
            ]);

            $result = $this->loginService->login($validated);

            return response()->json([
                'success' => true,
                'message' => 'Logged in successfully',
                'data' => [
                    'user' => $result['user']->only([
                        'id',
                        'firstname',
                        'lastname',
                        'email',
                        'phoneNumber',
                        'is_verified',
                        'is_active',
                        'two_factor_enabled',
                        'last_login_at',
                    ]),
                    'token' => $result['token'],
                    'requires_2fa' => $result['requires_2fa'] ?? false,
                    'is_trusted_device' => $result['is_trusted_device'] ?? false,
                    'login_history' => $result['login_history'] ?? [],
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // =========================================================================
    // Logout
    // =========================================================================
    
    /**
     * Logout user and revoke token
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // =========================================================================
    // Get Authenticated User
    // =========================================================================
    
    /**
     * Get authenticated user details
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user->only([
                        'id',
                        'firstname',
                        'lastname',
                        'email',
                        'phoneNumber',
                        'referralCode',
                        'is_verified',
                        'is_active',
                        'two_factor_enabled',
                        'last_login_at',
                        'last_login_ip',
                        'login_count',
                        'created_at',
                    ]),
                    'login_history' => $user->loginAttempts()
                        ->latest('attempted_at')
                        ->limit(5)
                        ->get([
                            'ip_address', 
                            'user_agent', 
                            'attempted_at', 
                            'success',
                            'is_lockout'
                        ])
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user details',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // =========================================================================
    // Refresh Token
    // =========================================================================
    
    /**
     * Refresh authentication token
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke current token
            $user->currentAccessToken()->delete();
            
            // Create new token
            $newToken = $user->createToken('auth_token', ['basic'])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $newToken,
                    'expires_at' => now()->addDays(7), // Configure as needed
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function updateProfile(Request $request): JsonResponse
{
    $user = $request->user();

    $validated = $request->validate([
        'first_name' => 'sometimes|string|max:255',
        'last_name' => 'sometimes|string|max:255',
        'phone_number' => 'sometimes|string|max:20|unique:users,phone_number,' . $user->id,
    ]);

    $user->update($validated);

    return response()->json([
        'success' => true,
        'message' => 'Profile updated successfully',
        'data' => $user,
    ]);
}

public function updateAddress(Request $request): JsonResponse
{
    $user = $request->user();

    $validated = $request->validate([
        'address' => 'required|string|max:500',
        'latitude' => 'required|numeric',
        'longitude' => 'required|numeric',
    ]);

    $user->update($validated);

    return response()->json([
        'success' => true,
        'message' => 'Address updated successfully',
        'data' => $user,
    ]);
}

public function updateProfilePicture(Request $request): JsonResponse
{
    $user = $request->user();

    $request->validate([
        'profile_picture' => 'required|image|max:2048',
    ]);

    $path = $request->file('profile_picture')->store('profile_pictures', 'public');
    $user->update(['profile_picture' => $path]);

    return response()->json([
        'success' => true,
        'message' => 'Profile picture updated',
        'data' => $user,
    ]);
}

public function updatePassword(Request $request): JsonResponse
{
    $user = $request->user();

    $request->validate([
        'current_password' => 'required|string',
        'new_password' => 'required|string|min:8|confirmed',
    ]);

    if (!\Hash::check($request->current_password, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Current password is incorrect'
        ], 422);
    }

    $user->update(['password' => bcrypt($request->new_password)]);

    return response()->json([
        'success' => true,
        'message' => 'Password updated successfully'
    ]);
}

public function update2FA(Request $request): JsonResponse
{
    $user = $request->user();

    $request->validate([
        'two_factor_enabled' => 'required|boolean',
    ]);

    $user->update(['two_factor_enabled' => $request->two_factor_enabled]);

    return response()->json([
        'success' => true,
        'message' => '2FA updated',
        'data' => $user,
    ]);
}
public function updateNotifications(Request $request): JsonResponse
{
    $user = $request->user();

    $validated = $request->validate([
        'notifications' => 'required|array',
    ]);

    $user->update(['notifications' => json_encode($validated['notifications'])]);

    return response()->json([
        'success' => true,
        'message' => 'Notifications updated',
        'data' => $user,
    ]);
}

}