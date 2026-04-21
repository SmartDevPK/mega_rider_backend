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
    ReferralController,
    DraftController,
};

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', fn() => response()->json([
    'status' => 'healthy',
    'timestamp' => now()->toISOString(),
    'version' => '1.0.0',
]));

// Debug
Route::get('/debug', fn() => response()->json(['ok' => true]));

// Referral public
Route::get('/referral/{code}', [UserController::class, 'getByReferralCode'])
    ->middleware('throttle:30,1');

// Promotions
Route::get('/promotions/live', [PromotionController::class, 'live']);

/*
|--------------------------------------------------------------------------
| AUTH (PUBLIC)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/check-email', [AuthController::class, 'checkEmail'])->middleware('throttle:10,1');
    Route::post('/check-phone', [AuthController::class, 'checkPhone'])->middleware('throttle:10,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,10');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:10,5');
    Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,30');
});

/*
|--------------------------------------------------------------------------
| PASSWORD RESET (PUBLIC)
|--------------------------------------------------------------------------
*/
Route::prefix('password')->group(function () {
    Route::post('/request-reset', [PasswordResetController::class, 'sendResetCode'])->middleware('throttle:3,60');
    Route::post('/verify-code', [PasswordResetController::class, 'verifyResetCode'])->middleware('throttle:5,30');
    Route::post('/reset', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:5,30');
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (SANCTUM)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    |-------------------------
    | USER
    |-------------------------
    */
    Route::get('/user', fn(Request $request) => $request->user());

    /*
    |-------------------------
    | AUTH
    |-------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/user-info', [AuthController::class, 'getUserInfo']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    /*
    |-------------------------
    | PROFILE
    |-------------------------
    */
    Route::prefix('profile')->group(function () {
        Route::get('/', [UserController::class, 'profile']);
        Route::put('/', [UserController::class, 'update']);
        Route::delete('/', [UserController::class, 'destroy']);

        Route::patch('/address', [ProfileController::class, 'updateAddress']);
        Route::patch('/picture', [ProfileController::class, 'updateProfilePicture']);
        Route::patch('/password', [ProfileController::class, 'updatePassword']);
        Route::patch('/2fa', [ProfileController::class, 'update2FA']);
        Route::patch('/notifications', [ProfileController::class, 'updateNotifications']);
    });

    /*
    |-------------------------
    | CUSTOMER
    |-------------------------
    */
    Route::prefix('customer')->group(function () {

        Route::get('/summary', [OrderController::class, 'summary']);
        Route::get('/order-types', [OrderController::class, 'getOrderTypes']);
        Route::get('/payment-breakdown', [OrderController::class, 'paymentBreakdown']);

        Route::post('/live-packages', [OrderController::class, 'livePackages']);
        Route::post('/update-order-type', [OrderController::class, 'updateOrderType']);
        Route::post('/update-instructions', [OrderController::class, 'updateInstructions']);

        /*
        |-------------------------
        | DRAFTS (FIXED)
        |-------------------------
        */
        Route::prefix('drafts')->group(function () {
            Route::get('/', [DraftController::class, 'index']);
            Route::post('/auto-save', [DraftController::class, 'autoSave']);
            Route::get('/{order_id}', [DraftController::class, 'show']);
            Route::post('/{order_id}/submit', [DraftController::class, 'submit']);
            Route::delete('/{order_id}', [DraftController::class, 'destroy']);
        });
    });

    /*
    |-------------------------
    | ORDERS
    |-------------------------
    */
    Route::prefix('orders')->group(function () {

        Route::post('/', [OrderController::class, 'store'])->middleware('throttle:20,5');
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/activities', [OrderController::class, 'activities']);

        Route::post('/cancel', [OrderCancellationController::class, 'cancel']);
        Route::post('/apply-promo', [OrderController::class, 'applyPromo'])->middleware('throttle:10,1');

        Route::post('/deliver', [OrderController::class, 'markAsDelivered']);
        Route::post('/streak-update', [OrderController::class, 'streakUpdate']);

        Route::get('/{order_id}', [OrderController::class, 'show']);
        Route::put('/{order_id}', [OrderController::class, 'update']);
        Route::patch('/{order_id}', [OrderController::class, 'update']);
    });

    /*
    |-------------------------
    | REFERRALS
    |-------------------------
    */
    Route::get('/referrals/leaderboard', [ReferralController::class, 'leaderboard']);

    /*
    |-------------------------
    | WALLET
    |-------------------------
    */
    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [WalletController::class, 'balance']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
    });

    /*
    |-------------------------
    | REVIEWS
    |-------------------------
    */
    Route::prefix('reviews')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
    });

    /*
    |-------------------------
    | REASONS
    |-------------------------
    */
    Route::prefix('reasons')->group(function () {
        Route::get('/report', [ReasonController::class, 'reportReasons']);
        Route::get('/cancellation', [ReasonController::class, 'cancellationReasons']);
    });

    /*
    |-------------------------
    | REPORTS
    |-------------------------
    */
    Route::prefix('reports')->group(function () {
        Route::post('/user', [UserReportController::class, 'store']);
    });
});
