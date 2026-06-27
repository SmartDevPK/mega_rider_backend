<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Rider;
use App\Models\Admin;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    /**
     * Base validation rules for password update
     */
    protected function getPasswordValidationRules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|min:8|same:new_password'
        ];
    }

    /**
     * Update Password - Single endpoint for User model (RECOMMENDED)
     */
    public function updatePassword(Request $request)
    {
        try {
            $validated = $request->validate($this->getPasswordValidationRules());

            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Verify current password
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 401);
            }

            // Check if new password is same as old password
            if (Hash::check($validated['new_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password must be different from current password'
                ], 422);
            }

            // Update password
            $user->password = Hash::make($validated['new_password']);

            // Update password timestamp
            if (property_exists($user, 'password_updated_at')) {
                $user->password_updated_at = now();
            }

            // Update timestamps
            if (property_exists($user, 'date_modified')) {
                $user->date_modified = now();
            }

            if (property_exists($user, 'updated_at')) {
                $user->updated_at = now();
            }

            $user->save();

            // Optional: Revoke all tokens for security
            // $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully',
                'data' => [
                    'password_expires_at' => now()->addMonths(3)->toDateTimeString()
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Update Password Error', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }

    /**
     * Update Password with user_type parameter (Alternative approach for separate tables)
     */
    public function updatePasswordWithType(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_type' => 'required|string|in:user,rider,admin',
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8',
                'password_confirmation' => 'required|string|min:8|same:new_password'
            ]);

            $userId = auth()->id();

            // Get user model instance based on type
            $user = $this->getUserModel($validated['user_type'], $userId);

            // Check if account exists
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account does not exist'
                ], 404);
            }

            // Get password field name
            $passwordField = $this->getPasswordField($validated['user_type']);

            // Verify current password
            if (!Hash::check($validated['current_password'], $user->$passwordField)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 401);
            }

            // Check if new password is same as old password
            if (Hash::check($validated['new_password'], $user->$passwordField)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password must be different from current password'
                ], 422);
            }

            // Update password
            $user->$passwordField = Hash::make($validated['new_password']);

            // Update password timestamp if the model has this field
            if (property_exists($user, 'password_updated_at')) {
                $user->password_updated_at = now();
            }

            // Update timestamps
            if (property_exists($user, 'date_modified')) {
                $user->date_modified = now();
            }

            if (property_exists($user, 'updated_at')) {
                $user->updated_at = now();
            }

            $user->save();

            // Revoke all tokens for security
            $this->revokeUserTokens($user);

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully',
                'data' => [
                    'password_expires_at' => now()->addMonths(3)->toDateTimeString()
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Update Password Error', [
                'user_id' => auth()->id(),
                'user_type' => $request->user_type ?? 'unknown',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }

    /**
     * Update User Password (using User model)
     */
    public function updateUserPassword(Request $request)
    {
        return $this->updatePasswordForUserType($request, 'user', User::class, 'password');
    }

    /**
     * Update Rider Password (using Rider model)
     */
    public function updateRiderPassword(Request $request)
    {
        return $this->updatePasswordForUserType($request, 'rider', Rider::class, 'password');
    }

    /**
     * Update Admin Password (using Admin model)
     */
    public function updateAdminPassword(Request $request)
    {
        return $this->updatePasswordForUserType($request, 'admin', Admin::class, 'password_hash');
    }

    /**
     * Check if user's password is expired
     */
    public function checkPasswordStatus(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Check if user model has the password expiry methods
            if (!method_exists($user, 'isPasswordExpired')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password expiry not implemented for this user type'
                ], 500);
            }

            $isExpired = $user->isPasswordExpired();
            $daysLeft = $user->getPasswordExpiryDays();

            return response()->json([
                'success' => true,
                'data' => [
                    'is_expired' => $isExpired,
                    'days_until_expiry' => $daysLeft,
                    'password_updated_at' => $user->password_updated_at?->toDateTimeString(),
                    'password_expires_at' => $user->password_updated_at?->addMonths(3)->toDateTimeString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Check Password Status Error', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Generic method to update password for any user type
     */
    private function updatePasswordForUserType(Request $request, string $userType, string $modelClass, string $passwordField)
    {
        try {
            $validated = $request->validate($this->getPasswordValidationRules());

            $user = $modelClass::find(auth()->id());

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => ucfirst($userType) . ' account not found'
                ], 404);
            }

            // Verify current password
            if (!Hash::check($validated['current_password'], $user->$passwordField)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 401);
            }

            // Check if new password is same as old password
            if (Hash::check($validated['new_password'], $user->$passwordField)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password must be different from current password'
                ], 422);
            }

            // Update password
            $user->$passwordField = Hash::make($validated['new_password']);

            // Update password timestamp if field exists
            if (property_exists($user, 'password_updated_at')) {
                $user->password_updated_at = now();
            }

            // Update timestamps
            if (property_exists($user, 'date_modified')) {
                $user->date_modified = now();
            }

            if (property_exists($user, 'updated_at')) {
                $user->updated_at = now();
            }

            $user->save();

            // Revoke all tokens for security
            $this->revokeUserTokens($user);

            return response()->json([
                'success' => true,
                'message' => ucfirst($userType) . ' password updated successfully',
                'data' => [
                    'password_expires_at' => now()->addMonths(3)->toDateTimeString()
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error("Update {$userType} Password Error", [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Get user model instance based on type
     */
    private function getUserModel(string $userType, int $userId)
    {
        $models = [
            'user' => User::class,
            'rider' => Rider::class,
            'admin' => Admin::class,
        ];

        if (!isset($models[$userType])) {
            return null;
        }

        return $models[$userType]::find($userId);
    }

    /**
     * Get password field name for different user types
     */
    private function getPasswordField(string $userType): string
    {
        return $userType === 'admin' ? 'password_hash' : 'password';
    }

    /**
     * Revoke all user tokens for security (Sanctum)
     */
    private function revokeUserTokens($user): void
    {
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
    }
    /**
     * Delete Account - Soft delete customer or rider account
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAccount(Request $request)
    {
        try {
            // 1. Validate request
            $validated = $request->validate([
                'user_type' => 'required|string|in:customer,rider',
                'email' => 'required|email',
                'password' => 'required|string'
            ]);

            // 2. Get authenticated user
            $authenticatedUser = auth()->user();

            if (!$authenticatedUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // 3. Determine account type and get model
            $userType = strtolower($validated['user_type']);
            $email = $validated['email'];
            $password = $validated['password'];
            $userId = $authenticatedUser->id;

            // 4. Fetch account from database
            $user = $this->getAccountForDeletion($userType, $userId, $email);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account does not exist'
                ], 404);
            }

            // 5. Verify password
            $passwordField = $this->getPasswordFieldForDeletion($userType);

            if (!Hash::check($password, $user->$passwordField)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorised'
                ], 401);
            }

            // 6. Soft delete account
            $this->softDeleteAccount($user, $userType);

            // 7. Revoke all Sanctum tokens
            $this->revokeUserTokens($user);

            // 8. Return success response
            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Delete Account Error', [
                'user_id' => auth()->id(),
                'user_type' => $request->user_type ?? 'unknown',
                'email' => $request->email ?? 'unknown',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get account for deletion based on user type
     */
    private function getAccountForDeletion(string $userType, int $userId, string $email)
    {
        $models = [
            'customer' => User::class,
            'rider' => Rider::class,
        ];

        if (!isset($models[$userType])) {
            return null;
        }

        $modelClass = $models[$userType];

        // Check if account is already deleted
        $user = $modelClass::where('id', $userId)
            ->where('email', $email)
            ->first();

        if ($user && $this->isAccountAlreadyDeleted($user, $userType)) {
            return null;
        }

        return $user;
    }

    /**
     * Check if account is already deleted
     */
    private function isAccountAlreadyDeleted($user, string $userType): bool
    {
        // For customers (using User model)
        if ($userType === 'customer') {
            return isset($user->is_deleted) && $user->is_deleted == true;
        }

        // For riders
        if ($userType === 'rider') {
            return isset($user->is_deleted) && $user->is_deleted == true;
        }

        return false;
    }

    /**
     * Get password field name for deletion
     */
    private function getPasswordFieldForDeletion(string $userType): string
    {
        // Customers and riders use 'password'
        return 'password';
    }

    /**
     * Soft delete account
     */
    private function softDeleteAccount($user, string $userType): void
    {
        // Set deleted flag
        $user->is_deleted = true;

        // Set email verification to false
        if ($userType === 'customer') {
            $user->is_email_verified = false;
        } elseif ($userType === 'rider') {
            $user->email_verified = false;
        }

        // Update timestamp
        if (property_exists($user, 'date_modified')) {
            $user->date_modified = now();
        }

        if (property_exists($user, 'updated_at')) {
            $user->updated_at = now();
        }

        // Optional: Clear sensitive data
        // $user->email = $user->email . '_deleted_' . time();
        // $user->phone = null;
        // $user->device_token = null;

        $user->save();

        Log::info('Account soft deleted', [
            'user_id' => $user->id,
            'user_type' => $userType,
            'email' => $user->email,
            'deleted_at' => now()
        ]);
    }
    /**
     * Delete Admin Account - Separate endpoint with enhanced security
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAdminAccount(Request $request)
    {
        try {
            // 1. Validate request with extra security
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'super_admin_token' => 'required|string', // Extra security token
                'deletion_reason' => 'nullable|string|max:500'
            ]);

            // 2. Get authenticated admin
            $authenticatedUser = auth()->user();

            if (!$authenticatedUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // 3. Verify user is admin
            if (!($authenticatedUser instanceof Admin) && $authenticatedUser->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin accounts can be deleted through this endpoint'
                ], 403);
            }

            // 4. Prevent deletion of the last admin (optional but recommended)
            if ($this->isLastAdmin($authenticatedUser->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the last admin account'
                ], 403);
            }

            // 5. Verify super admin token (optional but recommended)
            if (!$this->verifySuperAdminToken($validated['super_admin_token'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid super admin token'
                ], 401);
            }

            // 6. Fetch admin account
            $admin = Admin::where('id', $authenticatedUser->id)
                ->where('email', $validated['email'])
                ->first();

            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin account does not exist'
                ], 404);
            }

            // 7. Verify password
            if (!Hash::check($validated['password'], $admin->password_hash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorised'
                ], 401);
            }

            // 8. Soft delete or hard delete admin
            $this->deleteAdminAccountPermanently($admin, $validated['deletion_reason'] ?? null);

            // 9. Revoke all tokens
            $this->revokeUserTokens($admin);

            return response()->json([
                'success' => true,
                'message' => 'Admin account deleted successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Delete Admin Account Error', [
                'admin_id' => auth()->id(),
                'email' => $request->email ?? 'unknown',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }

    /**
     * Check if this is the last admin account
     */
    private function isLastAdmin(int $adminId): bool
    {
        return Admin::where('id', '!=', $adminId)
            ->where(function ($query) {
                $query->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->count() === 0;
    }

    /**
     * Verify super admin token (store in .env for security)
     */
    private function verifySuperAdminToken(string $token): bool
    {
        $expectedToken = config('app.super_admin_token', env('SUPER_ADMIN_TOKEN'));
        return hash_equals($expectedToken, $token);
    }

    /**
     * Permanently delete admin account (or soft delete)
     */
    private function deleteAdminAccountPermanently($admin, ?string $reason = null): void
    {
        // Option A: Hard delete (permanent)
        $admin->forceDelete();

        // Option B: Soft delete with audit
        // $admin->is_deleted = true;
        // $admin->deleted_at = now();
        // $admin->deletion_reason = $reason;
        // $admin->deleted_by = auth()->id();
        // $admin->save();

        Log::warning('Admin account deleted', [
            'admin_id' => $admin->id,
            'email' => $admin->email,
            'reason' => $reason,
            'deleted_by' => auth()->id(),
            'deleted_at' => now()
        ]);
    }
}
