<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// =========================================================================
// AUTH ROUTES
// =========================================================================

Route::prefix('v1/auth')->group(function () {

    // =========================================================================
    // PUBLIC ROUTES (No authentication required)
    // =========================================================================

    // Email & Phone checks
    Route::post('/check-email', [AuthController::class, 'checkEmail']);
    Route::post('/check-phone', [AuthController::class, 'checkPhone']);

    // Registration OTP routes
    Route::post('/send-pre-registration-otp', [AuthController::class, 'sendPreRegistrationOTP']);
    Route::post('/resend-pre-registration-otp', [AuthController::class, 'resendPreRegistrationOTP']);
    Route::post('/verify-otp-register', [AuthController::class, 'verifyOTPAndRegister']);

    // Login
    Route::post('/login', [AuthController::class, 'login']);

    // Email verification for existing users
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);

    // PASSWORD RESET ROUTES 
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/resend-reset-code', [AuthController::class, 'resendResetCode']);

    // =========================================================================
    // AUTHENTICATED ROUTES (Requires valid token)
    // =========================================================================

    Route::middleware(['auth:sanctum'])->group(function () {

        // Session management
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAllDevices']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);

        // Two Factor Authentication Routes
        Route::prefix('/two-factor')->group(function () {
            Route::get('/settings', [AuthController::class, 'getTwoFactorSettings']);
            Route::post('/enable', [AuthController::class, 'enableTwoFactor']);
            Route::post('/disable', [AuthController::class, 'disableTwoFactor']);
            Route::post('/verify', [AuthController::class, 'verifyTwoFactor']);
            Route::post('/regenerate-codes', [AuthController::class, 'regenerateRecoveryCodes']);
        });
    });
});
