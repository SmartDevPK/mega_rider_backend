<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // Add this import
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Get authenticated user profile
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => array_merge(
                        $user->only([
                            'id', 
                            'firstname', 
                            'lastname', 
                            'email', 
                            'phoneNumber',
                            'referralCode',
                            'is_verified',
                            'is_active',
                            'created_at'
                        ]),
                        ['full_name' => $this->userService->getFullName($user)]
                    ),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $validated = $request->validate([
                'firstname' => 'sometimes|string|max:255',
                'lastname' => 'sometimes|string|max:255',
                'phoneNumber' => 'sometimes|string|max:20|unique:users,phoneNumber,' . $user->id,
                'current_password' => 'required_with:password|string',
                'password' => 'sometimes|string|min:8|confirmed',
            ]);

            // Verify current password if changing password
            if (isset($validated['password'])) {
                if (!Hash::check($validated['current_password'], $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is incorrect',
                        'errors' => ['current_password' => ['The provided password does not match our records.']]
                    ], 422);
                }
            }

            $updatedUser = $this->userService->update($user, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => $updatedUser->only([
                        'id',
                        'firstname', 
                        'lastname', 
                        'email', 
                        'phoneNumber',
                        'address'
                    ]),
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
                'message' => 'Update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete user account
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $validated = $request->validate([
                'password' => 'required|string',
            ]);

            if (!Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect',
                ], 422);
            }

            $this->userService->delete($user);
            
            // Revoke tokens
            $user->tokens()->delete();
            
            // Logout user
            Auth::guard('web')->logout();
            
            // Invalidate session if using sessions
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully',
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
                'message' => 'Account deletion failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user by referral code
     */
    public function getByReferralCode(string $code): JsonResponse
    {
        try {
            $user = $this->userService->findByReferralCode($code);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid referral code',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user->only(['id', 'firstname', 'lastname', 'referralCode']),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to find user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}