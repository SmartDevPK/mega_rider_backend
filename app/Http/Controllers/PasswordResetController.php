<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Request password reset (send reset code)
     */
    public function requestReset(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $user = User::where('email', $validated['email'])->firstOrFail();
            
            $resetCode = $this->userService->sendPasswordReset($user);

            return response()->json([
                'success' => true,
                'message' => 'Password reset code has been sent to your email.',
                'data' => [
                    'email' => $user->email,
                    // Remove this in production - only for testing
                    // 'reset_code' => $resetCode
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reset code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify reset code
     */
    public function verifyCode(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'code' => 'required|string|size:6',
            ]);

            $user = User::where('email', $validated['email'])
                ->where('password_reset_code', $validated['code'])
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reset code',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Code verified successfully',
                'data' => [
                    'email' => $user->email,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Reset password with code
     */
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'code' => 'required|string|size:6',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = $this->userService->resetPassword(
                $validated['email'],
                $validated['code'],
                $validated['password']
            );

            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully. You can now login.',
                'data' => [
                    'email' => $user->email,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}