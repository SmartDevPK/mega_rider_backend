<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class AuthController extends Controller
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    // ------------------------------
    // Check Email
    // ------------------------------

    /**
     * Check if an email exists in the database.
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = $request->email;
        $user = User::where('email', $email)->first();

        if ($user) {
            return response()->json([
                'status' => 'login',
                'message' => 'Email exists. Proceed to login.',
                'data' => [
                    'email' => $user->email,
                    'name' => $user->firstname . ' ' . $user->lastname,
                ],
            ], 200);
        }

        return response()->json([
            'status' => 'register',
            'message' => 'Email not found. Proceed to register.',
            'data' => [
                'email' => $email,
            ],
        ], 200);
    }

    // ------------------------------
    // Registration
    // ------------------------------

    /**
     * Register a new user.
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
                    'regex:/[a-z]/',      // lowercase
                    'regex:/[A-Z]/',      // uppercase
                    'regex:/[0-9]/',      // number
                    'regex:/[@$!%*?&]/',  // special character
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
                        'phoneNumber'
                    ]),
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ------------------------------
    // Email Verification
    // ------------------------------

    /**
     * Verify email with code (OTP).
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
        ]);

        try {
            $user = $this->userService->verifyEmail($request->email, $request->otp);

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully.',
                'data' => [
                    'user' => $user->only([
                        'id',
                        'firstname',
                        'lastname',
                        'email',
                        'is_verified'
                    ]),
                ],
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during verification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resend email verification code.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $user = User::where('email', $request->email)->firstOrFail();

            $this->userService->resendVerification($user);

            return response()->json([
                'success' => true,
                'message' => 'Verification code resent successfully.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not resend verification code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
