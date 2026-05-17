<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| CONTROLLERS
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\{
    AuthController,
    UserController,
    ProfileController,
    OrderController,
    PasswordResetController,
    ReviewController,
    OrderCancellationController,
    ReasonController,
    UserReportController,
    PromotionController,
    WalletController,
    ReferralController,
    DraftController,
};

use App\Http\Controllers\Rider\{
    RiderController,
    RiderAuthController,
    CheckRiderController,
    RiderVerificationController
};

use App\Http\Controllers\Admin\{
    AdminAuthController,
    RiderApprovalController,
};

/*
|--------------------------------------------------------------------------
| HEALTH & DEBUG
|--------------------------------------------------------------------------
*/
Route::get('/health', fn () => response()->json([
    'status' => 'healthy',
    'timestamp' => now()->toISOString(),
    'version' => '1.0.0',
]));

Route::get('/debug', fn () => response()->json(['ok' => true]));

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/
Route::get('/referral/{code}', [UserController::class, 'getByReferralCode'])
    ->middleware('throttle:30,1');

Route::get('/promotions/live', [PromotionController::class, 'live']);

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/check-email', [AuthController::class, 'checkEmail']);
    Route::post('/check-phone', [AuthController::class, 'checkPhone']);
});

/*
|--------------------------------------------------------------------------
| PASSWORD RESET
|--------------------------------------------------------------------------
*/
Route::prefix('password')->group(function () {
    Route::post('/request-reset', [PasswordResetController::class, 'sendResetCode']);
    Route::post('/verify-code', [PasswordResetController::class, 'verifyResetCode']);
    Route::post('/reset', [PasswordResetController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED USER ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn (Request $request) => $request->user());

    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/user-info', [AuthController::class, 'getUserInfo']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    Route::prefix('profile')->group(function () {
        Route::get('/', [UserController::class, 'profile']);
        Route::put('/', [UserController::class, 'update']);
        Route::delete('/', [UserController::class, 'destroy']);
    });

    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{order_id}', [OrderController::class, 'show']);
    });

    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [WalletController::class, 'balance']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
    });
});

/*
|--------------------------------------------------------------------------
| RIDER ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('rider')->group(function () {
    
    /*
    |==========================================================================
    | PUBLIC ENDPOINTS
    |==========================================================================
    */
    
    // Registration Flow
    Route::prefix('auth')->group(function () {
        Route::post('/register', [RiderController::class, 'register']);
        Route::post('/check-status', [RiderController::class, 'checkStatus']);
        Route::post('/set-password', [RiderController::class, 'setPassword']);
        Route::post('/login', [RiderAuthController::class, 'login']);
    });
    
    // Password Reset Flow
    Route::prefix('password')->group(function () {
        Route::post('/forgot', [RiderAuthController::class, 'forgotPassword']);
        Route::post('/reset', [RiderAuthController::class, 'resetPassword']);
        Route::post('/resend-token', [RiderAuthController::class, 'resendResetToken']);
    });
    
    // Email Verification Flow
    Route::prefix('verification')->group(function () {
        Route::post('/send', [RiderVerificationController::class, 'sendVerification']);
        Route::post('/verify', [RiderVerificationController::class, 'verifyOtp']);
        Route::post('/resend', [RiderVerificationController::class, 'resendOtp']);
    });

    /*
    |==========================================================================
    | PROTECTED ENDPOINTS (Requires Authentication)
    |==========================================================================
    */
    
    Route::middleware('auth:sanctum')->group(function () {
        
        // User Information
        Route::prefix('user')->group(function () {
            Route::get('/profile', [RiderController::class, 'profile']);
            Route::put('/profile', [RiderController::class, 'updateProfile']);
            Route::get('/me', [RiderAuthController::class, 'me']);
            Route::post('/vehicle/update', [RiderController::class, 'updateVehicleDetails']);
            Route::post('/name/update', [RiderController::class, 'updateRiderName']);
            Route::post('/profile-picture/upload', [RiderController::class, 'updateRiderProfilePicture']);
            Route::post('/driver-license/upload', [RiderController::class, 'updateDriverLicense']);
            Route::post('/utility-bill/upload', [RiderController::class, 'updateUtilityBill']);
        });
        
        // Guarantor Management
        Route::prefix('guarantor')->group(function () {
            Route::get('/', [RiderAuthController::class, 'getGuarantor']);
            Route::put('/', [RiderAuthController::class, 'updateGuarantor']);
        });
        
        // Next of Kin Management
        Route::prefix('next-of-kin')->group(function () {
            Route::get('/', [RiderAuthController::class, 'getNextOfKin']);
            Route::put('/', [RiderAuthController::class, 'updateNextOfKin']);
        });

        
        
        // Combined Operations
        Route::put('/family-info', [RiderAuthController::class, 'updateGuarantorAndNextOfKin']);
        
        // Account Security
        Route::prefix('account')->group(function () {
            Route::post('/logout', [RiderAuthController::class, 'logout']);
            Route::post('/refresh-token', [RiderAuthController::class, 'refreshToken']);
            Route::post('/change-password', [RiderAuthController::class, 'changePassword']);
            Route::post('/verify-token', [RiderAuthController::class, 'verifyToken']);
        });
        
        // Rider Dashboard
        Route::get('/dashboard', [RiderController::class, 'dashboard']);
    });
});
/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {

    // Public
    Route::post('/login', [AdminAuthController::class, 'login']);

    // Protected
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);

        Route::get('/dashboard', [RiderApprovalController::class, 'dashboard']);
        Route::get('/statistics', [RiderApprovalController::class, 'statistics']);

        Route::prefix('riders')->group(function () {

            Route::get('/pending', [RiderApprovalController::class, 'pendingRiders']);
            Route::get('/approved', [RiderApprovalController::class, 'approvedRiders']);
            Route::get('/rejected', [RiderApprovalController::class, 'rejectedRiders']);
            Route::get('/export', [RiderApprovalController::class, 'exportPending']);

            Route::get('/{id}', [RiderApprovalController::class, 'showRider']);
            Route::post('/{id}/approve', [RiderApprovalController::class, 'approve']);
            Route::post('/{id}/reject', [RiderApprovalController::class, 'reject']);
            Route::post('/{id}/process', [RiderApprovalController::class, 'processApproval']);
            Route::post('/bulk-approve', [RiderApprovalController::class, 'bulkApprove']);
            Route::delete('/{id}', [RiderApprovalController::class, 'deleteRider']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| FALLBACK ROUTE
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Route not found',
    ], 404);
});