<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailCheckController;

use Illuminate\Support\Facades\Route;

/**
 * Auth Routes - Mega Dispatch Production Standard
 * 
 * Security Features:
 * - Rate limiting at route level
 * - Security headers middleware
 * - Throttle by IP and user
 * - Idempotency key support
 */

// =========================================================================
// V1 AUTH ROUTES
// =========================================================================

Route::prefix('v1/auth')
    ->group(function () {

        // =====================================================================
        // PUBLIC ROUTES - No authentication required
        // =====================================================================

        Route::prefix('public')->group(function () {

            // Email Validation
            Route::post('/check-email', [EmailCheckController::class, 'checkEmail'])
                ->name('auth.check-email')
                ->middleware('throttle:30,1');

            // Registration Flow
            Route::post('/send-otp', [AuthController::class, 'sendOTP'])
                ->name('auth.send-otp')
                ->middleware('throttle:5,1');

            Route::post('/register', [AuthController::class, 'register'])
                ->name('auth.register')
                ->middleware('throttle:10,1');

            // Authentication
            Route::post('/login', [AuthController::class, 'login'])
                ->name('auth.login')
                ->middleware('throttle:10,1');

            Route::post('/verify-2fa', [AuthController::class, 'verifyTwoFactor'])
                ->name('auth.verify-2fa')
                ->middleware('throttle:5,1');

            // Password Reset Flow
            Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
                ->name('auth.forgot-password')
                ->middleware('throttle:3,60');

            Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode'])
                ->name('auth.verify-reset-code')
                ->middleware('throttle:5,1');

            Route::post('/reset-password', [AuthController::class, 'resetPassword'])
                ->name('auth.reset-password')
                ->middleware('throttle:3,1');
        });

        // =====================================================================
        // AUTHENTICATED ROUTES - Require valid token
        // =====================================================================

        Route::middleware(['auth:sanctum', 'throttle:100,1'])
            ->group(function () {

                // Session Management
                Route::post('/logout', [AuthController::class, 'logout'])
                    ->name('auth.logout');

                Route::post('/logout-all-devices', [AuthController::class, 'logoutAllDevices'])
                    ->name('auth.logout-all');

                Route::post('/refresh', [AuthController::class, 'refresh'])
                    ->name('auth.refresh');

                Route::get('/me', [AuthController::class, 'me'])
                    ->name('auth.me');

                // Two-Factor Authentication (2FA)
                Route::prefix('2fa')
                    ->name('auth.2fa.')
                    ->group(function () {

                        Route::get('/settings', [AuthController::class, 'getTwoFactorSettings'])
                            ->name('settings');

                        Route::post('/enable', [AuthController::class, 'enableTwoFactor'])
                            ->name('enable');

                        Route::post('/disable', [AuthController::class, 'disableTwoFactor'])
                            ->name('disable');

                        Route::post('/verify', [AuthController::class, 'verifyTwoFactor'])
                            ->name('verify');

                        Route::post('/recovery-codes', [AuthController::class, 'regenerateRecoveryCodes'])
                            ->name('recovery-codes');
                    });
            });
    });

// =========================================================================
// FALLBACK ROUTE (Optional - for debugging)
// =========================================================================

Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Route not found.',
        'code' => 'ROUTE_NOT_FOUND'
    ], 404);
});
