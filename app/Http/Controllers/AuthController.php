<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use App\Services\LoginService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Exception;

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
            $validated = $request->validate([
                'email' => 'required|email'
            ]);

            $user = User::where('email', $validated['email'])->first();

            if ($user) {
                return response()->json([
                    'success' => true,
                    'status' => 'login',
                    'message' => 'Email exists. Proceed to login.',
                    'data' => [
                        'email' => $user->email,
                        'name' => $user->firstname . ' ' . $user->lastname,
                    ],
                ], 200);
            }

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
        try {
            $validated = $request->validate([
                'firstname' => 'required|string|max:255',
                'lastname' => 'required|string|max:255',
                'phoneNumber' => 'required|string|max:20|unique:users',
                'email' => 'required|email|max:255|unique:users',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/[a-z]/',
                    'regex:/[A-Z]/',
                    'regex:/[0-9]/',
                    'regex:/[@$!%*?&]/',
                ],
                'referralCode' => 'nullable|string|exists:users,referralCode',
            ]);

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
                    ])
                ]
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
}