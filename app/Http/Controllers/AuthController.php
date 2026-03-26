<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use App\Services\UserService;
use App\Services\LoginService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Hash;

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
     */
    public function checkEmail(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email'
            ]);

            $user = User::where('email', $validated['email'])->first();

            if ($user) {
                $emailVerified = $user->is_verified;

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

    /**
     * Check if phone exists in the system
     */
    public function checkPhone(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'phoneNumber' => 'required|string'
            ]);

            $user = User::where('phoneNumber', $validated['phoneNumber'])->first();

            if ($user) {
                return response()->json([
                    'success' => true,
                    'exists' => true,
                    'message' => 'Phone number is already registered.',
                    'data' => [
                        'phoneNumber' => $user->phoneNumber,
                        'name' => $user->firstname . ' ' . $user->lastname,
                    ],
                ], 200);
            }

            return response()->json([
                'success' => true,
                'exists' => false,
                'message' => 'Phone number is available.',
                'data' => [
                    'phoneNumber' => $validated['phoneNumber'],
                ],
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
                'message' => 'Failed to check phone number',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // =========================================================================
    // Registration
    // =========================================================================
    
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'firstname' => 'required|string|max:255',
                'lastname'  => 'required|string|max:255',
                'phoneNumber' => [
                    'required',
                    'string',
                    'max:20',
                    'unique:users',
                    'regex:/^(?:\+234|0)[789][01]\d{8}$/',
                ],
                'email' => 'required|email|max:255|unique:users',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'regex:/[a-z]/',
                    'regex:/[A-Z]/',
                    'regex:/[0-9]/',
                    'regex:/[@$!%*?&]/',
                ],
                'referralCode' => 'nullable|string|exists:users,referralCode',
            ]);

            // Normalize phone number to +234
            if (Str::startsWith($validated['phoneNumber'], '0')) {
                $validated['phoneNumber'] = '+234' . substr($validated['phoneNumber'], 1);
            }

            // Block non-Nigerian phone numbers
            if (!Str::startsWith($validated['phoneNumber'], '+234')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Nigerian phone numbers are allowed.'
                ], 403);
            }

            // Register user via service
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

    // =========================================================================
    // User Info
    // =========================================================================
    
    /**
     * Get authenticated user info
     */
    public function getUserInfo(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login first.',
                    'error_code' => 'UNAUTHENTICATED'
                ], 401);
            }
            
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated.',
                    'error_code' => 'ACCOUNT_DEACTIVATED'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'User info retrieved successfully',
                'data' => [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'full_name' => $user->firstname . ' ' . $user->lastname,
                    'email' => $user->email,
                    'phoneNumber' => $user->phoneNumber,
                    'referralCode' => $user->referralCode,
                    'is_verified' => $user->is_verified,
                    'is_active' => $user->is_active,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]
            ], 200);
            
        } catch (Exception $e) {
            \Log::error('Get user info error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user information',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // =========================================================================
    // Email Verification
    // =========================================================================
    
    /**
     * Verify user's email with OTP
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

    /**
     * Resend verification code
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
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if ($user && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

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
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
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
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            // Revoke current token
            $user->currentAccessToken()->delete();
            
            // Create new token
            $newToken = $user->createToken('auth_token', ['basic'])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $newToken,
                    'expires_at' => now()->addDays(7),
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