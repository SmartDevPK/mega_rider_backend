<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrderController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Check if email exists
Route::post('/check-email', [AuthController::class, 'checkEmail']);

// Referral lookup
Route::get('/referral/{code}', [UserController::class, 'getByReferralCode']);

// Authentication & registration
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
    Route::post('/login', [AuthController::class, 'login']);

    // Password reset
    Route::post('/password/request-reset', [PasswordResetController::class, 'sendResetCode']);
    Route::post('/password/verify-code', [PasswordResetController::class, 'verifyResetCode']);
    Route::post('/password/reset', [PasswordResetController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Auth Actions
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Basic user info
    Route::get('/user', fn(Request $request) => $request->user());

    /*
    |--------------------------------------------------------------------------
    | User Profile Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'profile']);
        Route::put('/profile', [UserController::class, 'update']);
        Route::delete('/profile', [UserController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Profile Settings
    |--------------------------------------------------------------------------
    */


// Profile management routes (requires auth:sanctum)
Route::middleware('auth:sanctum')->prefix('profile')->group(function () {

    // Update basic profile info (firstname, lastname, phoneNumber)
    Route::put('/update', [ProfileController::class, 'updateProfile']);

    // Update address and location
    Route::patch('/address', [ProfileController::class, 'updateAddress']);

    // Update profile picture
    Route::patch('/picture', [ProfileController::class, 'updateProfilePicture']);

    // Update password
    Route::patch('/password', [ProfileController::class, 'updatePassword']);

    // Enable/disable 2FA
    Route::patch('/2fa', [ProfileController::class, 'update2FA']);

    // Update notifications
    Route::patch('/notifications', [ProfileController::class, 'updateNotifications']);

    // Delete account (soft delete)
    Route::delete('/delete', [ProfileController::class, 'deleteAccount']);
});



    /*
    |--------------------------------------------------------------------------
    | Orders (Strictly Protected)
    |--------------------------------------------------------------------------
    */
 Route::middleware('auth:sanctum')->group(function () {
    // Order CRUD
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::put('/orders/{orderId}', [OrderController::class, 'update']);
    
    // Order operations
    Route::patch('/orders/{orderId}/type', [OrderController::class, 'updateOrderType']);
    Route::post('/orders/{orderId}/vehicle', [OrderController::class, 'selectVehicle']);
    Route::get('/orders/{orderId}/details', [OrderController::class, 'getBasicDetails']);
    
    // Payment and tips
    Route::post('/orders/{orderId}/payment', [OrderController::class, 'processPayment']);
    Route::post('/orders/{orderId}/tip', [OrderController::class, 'addTip']);
    
    // Order cancellation
    Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancelOrder']);
    
    // Customer statistics
    Route::get('/orders/stats/my', [OrderController::class, 'getCustomerStats']);
});
});
