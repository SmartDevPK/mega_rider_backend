<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PasswordResetController;

// ---------------------------
// Public routes
// ---------------------------

// Check if email exists (no authentication required)
Route::post('/check-email', [AuthController::class, 'checkEmail']);

// Public referral lookup
Route::get('/referral/{code}', [UserController::class, 'getByReferralCode']);

// Authentication & registration
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});


    // Password reset
    Route::post('/password/request-reset', [PasswordResetController::class, 'sendResetCode']);
    Route::post('/password/verify-code', [PasswordResetController::class, 'verifyResetCode']);
    Route::post('/password/reset', [PasswordResetController::class, 'resetPassword']);
});

// ---------------------------
// Protected routes (require authentication via Sanctum)
// ---------------------------
Route::middleware('auth:sanctum')->group(function () {
    // Retrieve authenticated user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // User profile management
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'profile']);
        Route::put('/profile', [UserController::class, 'update']);
        Route::delete('/profile', [UserController::class, 'destroy']);
    });

    // Additional protected routes can be added here
});

