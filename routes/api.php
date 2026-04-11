<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
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
};

/*
|--------------------------------------------------------------------------
| Public / No Auth Routes
|--------------------------------------------------------------------------
*/

// 🔹 Debug
Route::get('/debug', fn() => response()->json(['ok' => true]));

//  Authentication
Route::prefix('auth')->group(function () {
    Route::post('/check-email', [AuthController::class, 'checkEmail'])->middleware('throttle:10,1');
    Route::post('/check-phone', [AuthController::class, 'checkPhone'])->middleware('throttle:10,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,10');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:10,5');
    Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,30');
    
});

//  Password Reset
Route::prefix('password')->group(function () {
    Route::post('/request-reset', [PasswordResetController::class, 'sendResetCode'])->middleware('throttle:3,60');
    Route::post('/verify-code', [PasswordResetController::class, 'verifyResetCode'])->middleware('throttle:5,30');
    Route::post('/reset', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:5,30');
});

// Referral
Route::get('/referral/{code}', [UserController::class, 'getByReferralCode'])->middleware('throttle:30,1');
Route::get('/promotions/live', [PromotionController::class, 'live']);

// 🩺 Health Check
Route::get('/health', fn() => response()->json([
    'status'    => 'healthy',
    'timestamp' => now()->toISOString(),
    'version'   => '1.0.0',
]));

// Wallet balance (requires auth but placed here for visibility)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wallet/balance', [WalletController::class, 'balance']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
});


/*
|--------------------------------------------------------------------------
| Authenticated Routes (auth:sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    // 🔹 Current authenticated user
    Route::get('/user', fn(Request $request) => $request->user());

    // Auth management
    Route::prefix('auth')->group(function () {
        Route::get('/user-info', [AuthController::class, 'getUserInfo']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    //  Profile management
    Route::prefix('profile')->group(function () {
        Route::get('/', [UserController::class, 'profile']);
        Route::put('/', [UserController::class, 'update']);
        Route::delete('/', [UserController::class, 'destroy']);
        Route::patch('/address', [ProfileController::class, 'updateAddress']);
        Route::patch('/picture', [ProfileController::class, 'updateProfilePicture']);
        Route::patch('/password', [ProfileController::class, 'updatePassword']);
        Route::patch('/2fa', [ProfileController::class, 'update2FA']);
        Route::patch('/notifications', [ProfileController::class, 'updateNotifications']);
        Route::delete('/delete', [ProfileController::class, 'deleteAccount']);
    });
    //  Orders
       // New group for customer endpoints
   Route::prefix('customer')->group(function () {
    // Order listing & details
    Route::get('/summary', [OrderController::class, 'summary']);
    Route::get('/order-types', [OrderController::class, 'getOrderTypes']);
    Route::get('/payment-breakdown', [OrderController::class, 'paymentBreakdown']);

    // Order modifications
    Route::post('/live-packages', [OrderController::class, 'livePackages']);
    Route::post('/update-order-type', [OrderController::class, 'updateOrderType']);
    Route::post('/update-instructions', [OrderController::class, 'updateInstructions']);
});

    // Orders
   Route::prefix('orders')->group(function () {
    Route::post('/', [OrderController::class, 'store'])->middleware('throttle:20,5');
    Route::get('/', [OrderController::class, 'index']);
    
    // SPECIFIC routes must come BEFORE parameterised ones
    Route::get('/activities', [OrderController::class, 'activities']);
    
    //  Parameterised route must be LAST
    Route::get('/{order_id}', [OrderController::class, 'show']);
    Route::put('/{order_id}', [OrderController::class, 'update']);
    Route::patch('/{order_id}', [OrderController::class, 'update']);
    Route::post('/cancel', [OrderCancellationController::class, 'cancel']);
});

    //  Reviews
    Route::prefix('reviews')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
    });

    //  Reasons (Report & Cancellation)
    Route::prefix('reasons')->group(function () {
        Route::get('/report', [ReasonController::class, 'reportReasons']);
        Route::get('/cancellation', [ReasonController::class, 'cancellationReasons']);
    });

    //  User Reporting
    Route::prefix('reports')->group(function () {
        Route::post('/user', [UserReportController::class, 'store']); 
    });

});
