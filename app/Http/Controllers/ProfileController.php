<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class ProfileController extends Controller
{
    /**
     * Update user's basic profile information
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'firstname'   => 'sometimes|string|max:255',
            'lastname'    => 'sometimes|string|max:255',
            'phoneNumber' => 'sometimes|string|max:20|unique:users,phoneNumber,' . $user->id,
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data'    => $user,
        ]);
    }

    /**
     * Update user's address and location
     */
    public function updateAddress(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'address'   => 'required|string|max:500',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully',
            'data'    => $user,
        ]);
    }

    /**
     * Update user's profile picture
     */
    public function updateProfilePicture(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'profile_picture' => 'required|image|max:2048', // max 2MB
        ]);

        $path = $request->file('profile_picture')->store('profile_pictures', 'public');

        $user->update(['profile_picture' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully',
            'data'    => $user,
        ]);
    }

    /**
     * Update user's password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed', // expects new_password_confirmation
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->update(['password' => bcrypt($request->new_password)]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Enable or disable two-factor authentication (2FA)
     */
    public function update2FA(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'two_factor_enabled' => 'required|boolean',
        ]);

        $user->update(['two_factor_enabled' => $request->two_factor_enabled]);

        return response()->json([
            'success' => true,
            'message' => '2FA status updated successfully',
            'data'    => $user,
        ]);
    }

    /**
     * Update user notification preferences
     */
    public function updateNotifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'notifications' => 'required|array', // e.g. ['app_update' => true, 'promo' => false]
        ]);

        $user->update(['notifications' => json_encode($validated['notifications'])]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications updated successfully',
            'data'    => $user,
        ]);
    }

    /**
     * Delete user account (soft delete)
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password is incorrect',
            ], 422);
        }

        // Soft delete: mark user as inactive
        $user->update(['is_active' => false]);

        // Revoke all API tokens (if using Sanctum)
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);
    }
}
