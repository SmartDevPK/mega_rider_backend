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
  AccountController,
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
| HEALTH & DEBUG ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/health', fn() => response()->json([
  'status' => 'healthy',
  'timestamp' => now()->toISOString(),
  'version' => '1.0.0',
]));

Route::get('/debug', fn() => response()->json(['ok' => true]));

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::get('/referral/{code}', [UserController::class, 'getByReferralCode'])
  ->middleware('throttle:30,1');

Route::get('/promotions/live', [PromotionController::class, 'live']);

// =========================================================================
// AUTHENTICATION ROUTES (User)
// =========================================================================
Route::prefix('auth')->group(function () {
  Route::post('/register', [AuthController::class, 'register']);
  Route::post('/login', [AuthController::class, 'login']);
  Route::post('/check-email', [AuthController::class, 'checkEmail']);
  Route::post('/check-phone', [AuthController::class, 'checkPhone']);
});

// =========================================================================
// PASSWORD RESET ROUTES (User)
// =========================================================================
Route::prefix('password')->group(function () {
  Route::post('/request-reset', [PasswordResetController::class, 'sendResetCode']);
  Route::post('/verify-code', [PasswordResetController::class, 'verifyResetCode']);
  Route::post('/reset', [PasswordResetController::class, 'resetPassword']);
});

// =========================================================================
// AUTHENTICATED USER ROUTES (Customer/General User)
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {

  // User Information
  Route::get('/user', fn(Request $request) => $request->user());

  // Auth Management
  Route::prefix('auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user-info', [AuthController::class, 'getUserInfo']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Two-Factor Authentication
    Route::post('/two-factor/settings', [AuthController::class, 'getTwoFactorSettings']);
    Route::post('/two-factor/toggle', [AuthController::class, 'toggleTwoFactor']);
  });

  // Profile Management
  Route::prefix('profile')->group(function () {
    Route::get('/', [UserController::class, 'profile']);
    Route::put('/', [UserController::class, 'update']);
    Route::delete('/', [UserController::class, 'destroy']);
  });

  // Orders
  Route::prefix('orders')->group(function () {
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/{order_id}', [OrderController::class, 'show']);
  });

  // Wallet
  Route::prefix('wallet')->group(function () {
    Route::get('/balance', [WalletController::class, 'balance']);
    Route::get('/transactions', [WalletController::class, 'transactions']);
  });

  // Account Management (Password, Deletion)
  Route::prefix('account')->group(function () {
    // Password Status
    Route::get('/password-status', [AccountController::class, 'checkPasswordStatus']);

    // Password Update (Main endpoint)
    Route::post('/update-password', [AccountController::class, 'updatePassword']);
    Route::post('/update-password-with-type', [AccountController::class, 'updatePasswordWithType']);

    // Password Update by User Type
    Route::prefix('user')->group(function () {
      Route::post('/update-password', [AccountController::class, 'updateUserPassword']);
    });

    Route::prefix('rider')->group(function () {
      Route::post('/update-password', [AccountController::class, 'updateRiderPassword']);
    });

    Route::prefix('admin')->group(function () {
      Route::post('/update-password', [AccountController::class, 'updateAdminPassword']);
    });

    // Account Deletion
    Route::post('/delete', [AccountController::class, 'deleteAccount']);
  });

  // Admin Deletion (Super Admin only)
  Route::post('/admin/delete', [AccountController::class, 'deleteAdminAccount'])
    ->middleware(['admin.access', 'super.admin']);
});

// =========================================================================
// RIDER ROUTES (All Rider-related endpoints)
// =========================================================================
Route::prefix('rider')->group(function () {

  /*
    |--------------------------------------------------------------------------
    | PUBLIC RIDER ROUTES (No Authentication Required)
    |--------------------------------------------------------------------------
    */

  // Registration & Authentication Flow
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
    |--------------------------------------------------------------------------
    | PROTECTED RIDER ROUTES (Authentication Required)
    |--------------------------------------------------------------------------
    */
  Route::middleware('auth:sanctum')->group(function () {

    // Dashboard & Analytics
    Route::post('/dashboard', [RiderController::class, 'dashboard']);
    Route::post('/activities', [RiderController::class, 'getActivities']);
    Route::get('/deliveries', [RiderController::class, 'deliveryHistory']);
    Route::get('/weekly-earnings', [RiderController::class, 'weeklyEarnings']);
    Route::post('/update-status', [RiderController::class, 'updateAvailabilityStatus']);
    Route::get('/dashboard', [RiderController::class, 'dashboard']);
    Route::get('/live-order', [RiderController::class, 'getLiveOrder']);
    Route::get('/wallet-dashboard', [RiderController::class, 'walletDashboard']);
    Route::post('/wallet-transactions', [RiderController::class, 'getWalletTransactions']);
    Route::post('/reviews', [RiderController::class, 'getReviews']);
    Route::post('/monthly-order-counts', [RiderController::class, 'getMonthlyOrderCounts']);
    Route::post('/yearly-order-analytics', [RiderController::class, 'getFullYearMonthlyOrderCounts']);
    Route::post('/weekly-order-counts', [RiderController::class, 'getWeeklyOrderCounts']);
    Route::post('/weekly-analytics', [RiderController::class, 'getWeeklyOrderAnalytics']);
    Route::get('/daily-order-counts', [RiderController::class, 'getDailyOrderCounts']);
    Route::get('/daily-order-counts/range', [RiderController::class, 'getDailyOrderCountsByDateRange']);
    Route::get('/hourly-order-distribution', [RiderController::class, 'getHourlyOrderDistribution']);
    Route::post('/metrics-dashboard', [RiderController::class, 'getMetricsDashboard']);
    Route::post('/metrics-dashboard/compare', [RiderController::class, 'getMetricsDashboardWithComparison']);
    Route::get('/banks', [RiderController::class, 'getBanks']);
    Route::get('/banks/country', [RiderController::class, 'getBanksByCountry']);
    Route::get('/banks/search', [RiderController::class, 'searchBanks']);
    Route::post('/banks/resolve-account', [RiderController::class, 'resolveBankAccount']);
    Route::post('/banks/resolve-account-retry', [RiderController::class, 'resolveBankAccountWithRetry']);
    Route::post('/banks/verify-beneficiary', [RiderController::class, 'verifyAndSaveBeneficiary']);
    Route::get('/wallet-beneficiaries', [RiderController::class, 'getWalletBeneficiaries']);
    Route::post('/wallet-beneficiaries', [RiderController::class, 'addWalletBeneficiary']);
    Route::delete('/wallet-beneficiaries/{id}', [RiderController::class, 'deleteWalletBeneficiary']);
    Route::put('/wallet-beneficiaries/{id}/default', [RiderController::class, 'setDefaultBeneficiary']);
    Route::delete('/{id}', [RiderController::class, 'deleteWalletBeneficiary']);
  });

  // Profile Management
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

  // Phone Management
  Route::prefix('phone')->group(function () {
    Route::put('/', [RiderController::class, 'updateRiderPhone']);
    Route::post('/verify', [RiderController::class, 'verifyRiderPhone']);
    Route::post('/resend-otp', [RiderController::class, 'resendPhoneOtp']);
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

  // Combined Family Information
  Route::put('/family-info', [RiderAuthController::class, 'updateGuarantorAndNextOfKin']);

  // Account Security
  Route::prefix('account')->group(function () {
    Route::post('/logout', [RiderAuthController::class, 'logout']);
    Route::post('/refresh-token', [RiderAuthController::class, 'refreshToken']);
    Route::post('/change-password', [RiderAuthController::class, 'changePassword']);
    Route::post('/verify-token', [RiderAuthController::class, 'verifyToken']);
  });
});

// =========================================================================
// ADMIN ROUTES
// =========================================================================
Route::prefix('admin')->group(function () {

  // Public Admin Routes
  Route::post('/login', [AdminAuthController::class, 'login']);

  // Protected Admin Routes
  Route::middleware('auth:sanctum')->group(function () {

    // Admin Authentication
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);

    // Dashboard & Statistics
    Route::get('/dashboard', [RiderApprovalController::class, 'dashboard']);
    Route::get('/statistics', [RiderApprovalController::class, 'statistics']);

    // Rider Management
    Route::prefix('riders')->group(function () {

      // List endpoints
      Route::get('/pending', [RiderApprovalController::class, 'pendingRiders']);
      Route::get('/approved', [RiderApprovalController::class, 'approvedRiders']);
      Route::get('/rejected', [RiderApprovalController::class, 'rejectedRiders']);

      // Export functionality
      Route::get('/export', [RiderApprovalController::class, 'exportPending']);

      // Single rider operations
      Route::get('/{id}', [RiderApprovalController::class, 'showRider']);
      Route::post('/{id}/approve', [RiderApprovalController::class, 'approve']);
      Route::post('/{id}/reject', [RiderApprovalController::class, 'reject']);
      Route::post('/{id}/process', [RiderApprovalController::class, 'processApproval']);

      // Bulk operations
      Route::post('/bulk-approve', [RiderApprovalController::class, 'bulkApprove']);

      // Delete rider
      Route::delete('/{id}', [RiderApprovalController::class, 'deleteRider']);
    });
  });
});

// =========================================================================
// FALLBACK ROUTE (Must be last)
// =========================================================================
Route::fallback(function () {
  return response()->json([
    'success' => false,
    'message' => 'Route not found',
  ], 404);
});
