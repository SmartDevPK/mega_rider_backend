<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\VehicleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

/*========================================================
=               Public Routes (No Auth Required)        =
========================================================*/
 // Vehicles
Route::prefix('vehicles')->middleware('throttle:60,1')->group(function () {
    Route::post('/', [VehicleController::class, 'storeVehicle'])->middleware('throttle:10,1');       // Create
    Route::get('/', [VehicleController::class, 'index'])->middleware('throttle:30,1');               // Get all
    Route::get('/{id}', [VehicleController::class, 'show'])->middleware('throttle:20,1');            // Get one
    Route::put('/{id}', [VehicleController::class, 'update'])->middleware('throttle:10,1');          // Update
    Route::delete('/{id}', [VehicleController::class, 'destroy'])->middleware('throttle:5,1');       // Delete
    Route::get('/driver/{driverId}', [VehicleController::class, 'getByDriver'])->middleware('throttle:20,1'); // Get by driver
});



// Check email & phone
Route::post('/check-email', [AuthController::class, 'checkEmail'])->middleware('throttle:10,1');
Route::post('/check-phone', [AuthController::class, 'checkPhone'])->middleware('throttle:10,1');

// Referral
Route::get('/referral/{code}', [UserController::class, 'getByReferralCode'])->middleware('throttle:30,1');

// Authentication
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,10');
Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:10,5');
Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,30');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Password reset
Route::post('/password/request-reset', [PasswordResetController::class, 'sendResetCode'])->middleware('throttle:3,60');
Route::post('/password/verify-code', [PasswordResetController::class, 'verifyResetCode'])->middleware('throttle:5,30');
Route::post('/password/reset', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:5,30');

// Ride (Public)
Route::post('/ride/calculate', [OrderController::class, 'calculateRide'])->middleware('throttle:30,1');
Route::post('/ride/nearby-riders', [OrderController::class, 'nearbyRiders'])->middleware('throttle:20,1');

/*========================================================
=           Authenticated Routes (auth:sanctum)         =
========================================================*/

Route::middleware(['auth:sanctum'])->group(function () {

    // Auth user info
    Route::get('/auth/user-info', [AuthController::class, 'getUserInfo'])->middleware('throttle:20,1');
    Route::get('/user', fn(Request $request) => $request->user())->middleware('throttle:60,1');

    // Auth actions
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:5,1');

    // User profile
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'update'])->middleware('throttle:10,5');
    Route::delete('/profile', [UserController::class, 'destroy'])->middleware('throttle:3,60');

    // Profile settings
    Route::put('/profile/update', [ProfileController::class, 'updateProfile'])->middleware('throttle:5,10');
    Route::patch('/profile/address', [ProfileController::class, 'updateAddress'])->middleware('throttle:10,5');
    Route::patch('/profile/picture', [ProfileController::class, 'updateProfilePicture'])->middleware('throttle:5,10');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:3,60');
    Route::patch('/profile/2fa', [ProfileController::class, 'update2FA'])->middleware('throttle:5,30');
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotifications'])->middleware('throttle:20,1');
    Route::delete('/profile/delete', [ProfileController::class, 'deleteAccount'])->middleware('throttle:2,1440');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->middleware('throttle:60,1');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:10,5');
    Route::post('/orders/create', [OrderController::class, 'createOrder'])->middleware('throttle:10,5');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->middleware('throttle:60,1');
    Route::put('/orders/{orderId}', [OrderController::class, 'update'])->middleware('throttle:20,5');
    Route::delete('/orders/{orderId}', [OrderController::class, 'destroy'])->middleware('throttle:5,60');
    Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancelOrder'])->middleware('throttle:5,60');
    Route::get('/orders/{orderId}/basic-details', [OrderController::class, 'getBasicDetails'])->middleware('throttle:60,1');
    Route::get('/orders/customer/stats', [OrderController::class, 'getCustomerStats'])->middleware('throttle:30,1');
    Route::get('/orders/{orderId}/driver-info', [OrderController::class, 'getDriverInfo'])->middleware('throttle:30,1');
    Route::get('/orders/{orderId}/driver-location', [OrderController::class, 'getDriverLocation'])->middleware('throttle:60,1');
    Route::post('/orders/{orderId}/payment', [OrderController::class, 'processPayment'])->middleware('throttle:5,60');
    Route::post('/orders/{orderId}/tip', [OrderController::class, 'addTip'])->middleware('throttle:5,30');
    Route::post('/orders/{orderId}/emergency', [OrderController::class, 'emergencyAlert'])->middleware('throttle:2,1440');

     // Packages
 Route::post('/packages/image', [OrderController::class, 'storeImage'])->middleware('throttle:5,60');
});

/*========================================================
=                    Health Check                        =
========================================================*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});
