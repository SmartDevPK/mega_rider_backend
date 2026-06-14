<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOTPRequest;
use App\Http\Requests\Auth\VerifyOTPAndRegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Customer\CustomerService;
use App\Services\LoginService;
use App\Services\TwoFactorService;
use App\Services\Customer\WelcomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Customer;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
  private CustomerService $customerService;
  private LoginService $loginService;
  private WelcomeService $welcomeService;
  private TwoFactorService $twoFactorService;

  public function __construct(
    CustomerService $customerService,
    LoginService $loginService,
    WelcomeService $welcomeService,
    TwoFactorService $twoFactorService
  ) {
    $this->customerService = $customerService;
    $this->loginService = $loginService;
    $this->welcomeService = $welcomeService;
    $this->twoFactorService = $twoFactorService;
  }

    // =========================================================================
    // AUTHENTICATION METHODS
    // =========================================================================

  /**
   * Login user
   */
  public function login(LoginRequest $request): JsonResponse
  {
    try {
      $result = $this->loginService->login(
        $request->getCredentials(),
        $request->ip(),
        $request->userAgent()
      );

      $user = $result['user'];

      return response()->json([
        'success' => true,
        'message' => 'Logged in successfully',
        'data' => [
          'user' => [
            'id' => $user->id,
            'firstname' => $user->first_name,
            'lastname' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phoneNumber' => $user->phone_number,
            'is_verified' => $user->is_verified,
            'is_active' => $user->is_active,
            'wallet_balance' => $user->wallet_balance,
            'points_balance' => $user->points_balance,
          ],
          'token' => $result['token'],
        ]
      ]);
    } catch (ValidationException $e) {
      return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
    } catch (Exception $e) {
      Log::error('Login error: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'Login failed: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Logout from current device
   */
  public function logout(Request $request): JsonResponse
  {
    try {
      $user = $request->user();
      if ($user && $user->currentAccessToken()) {
        $user->currentAccessToken()->delete();
      }
      return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    } catch (Exception $e) {
      Log::error('Logout error: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'Failed to logout'], 500);
    }
  }

  /**
   * Logout from all devices
   */
  public function logoutAllDevices(Request $request): JsonResponse
  {
    try {
      $user = $request->user();

      if ($user) {
        $user->tokens()->delete();
        Cache::forget("user_session:{$user->id}");
      }

      return response()->json([
        'success' => true,
        'message' => 'Logged out from all devices successfully'
      ]);
    } catch (Exception $e) {
      Log::error('Logout all devices error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to logout from all devices'
      ], 500);
    }
  }

  /**
   * Refresh authentication token
   */
  public function refresh(Request $request): JsonResponse
  {
    try {
      $user = $request->user();

      if (!$user) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthenticated'
        ], 401);
      }

      if ($user->currentAccessToken()) {
        $user->currentAccessToken()->delete();
      }

      $token = $user->createToken('auth_token', ['basic'])->plainTextToken;

      return response()->json([
        'success' => true,
        'message' => 'Token refreshed successfully',
        'data' => ['token' => $token]
      ]);
    } catch (Exception $e) {
      Log::error('Token refresh error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to refresh token'
      ], 500);
    }
  }

  /**
   * Get authenticated user (cached version for high traffic)
   */
  public function me(Request $request): JsonResponse
  {
    try {
      $userId = $request->user()?->id;

      if (!$userId) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthenticated'
        ], 401);
      }

      $userData = Cache::remember("user:{$userId}", 300, function () use ($request) {
        $user = $request->user();

        return [
          'id' => $user->id,
          'firstname' => $user->first_name,
          'lastname' => $user->last_name,
          'full_name' => $user->full_name,
          'email' => $user->email,
          'phoneNumber' => $user->phone_number,
          'referralCode' => $user->referral_code,
          'is_verified' => $user->is_verified,
          'is_active' => $user->is_active,
          'wallet_balance' => (float) $user->wallet_balance,
          'points_balance' => (int) $user->points_balance,
        ];
      });

      return response()->json([
        'success' => true,
        'data' => ['user' => $userData]
      ]);
    } catch (Exception $e) {
      Log::error('Get user error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to fetch user details'
      ], 500);
    }
  }

    // =========================================================================
    // EMAIL & PHONE CHECK METHODS
    // =========================================================================

  /**
   * Check if email exists in system
   */
  public function checkEmail(Request $request): JsonResponse
  {
    try {
      $validated = $request->validate(['email' => 'required|email']);
      $email = strtolower(trim($validated['email']));
      $user = Customer::where('email', $email)->first();

      if ($user) {
        return response()->json([
          'success' => true,
          'status' => 'login',
          'message' => $user->is_verified
            ? 'Email exists and is verified. Proceed to login.'
            : 'Email exists but is not verified. Please verify first.',
          'data' => [
            'email' => $user->email,
            'name' => $user->full_name,
            'is_verified' => $user->is_verified,
          ],
        ]);
      }

      return response()->json([
        'success' => true,
        'status' => 'register',
        'message' => 'Email not found. Proceed to register.',
        'data' => [
          'email' => $email,
          'otp_pending' => Cache::has("pre_registration_otp:{$email}"),
        ],
      ]);
    } catch (ValidationException $e) {
      return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
    } catch (Exception $e) {
      Log::error('Check email error: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'Failed to check email'], 500);
    }
  }

  /**
   * Check if phone number exists in system
   */
  public function checkPhone(Request $request): JsonResponse
  {
    try {
      $validated = $request->validate(['phoneNumber' => 'required|string']);
      $user = Customer::where('phone_number', $validated['phoneNumber'])->first();

      return response()->json([
        'success' => true,
        'exists' => !is_null($user),
        'message' => $user ? 'Phone number is already registered.' : 'Phone number is available.',
        'data' => ['phoneNumber' => $validated['phoneNumber']],
      ]);
    } catch (ValidationException $e) {
      return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
    } catch (Exception $e) {
      Log::error('Check phone error: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => 'Failed to check phone number'], 500);
    }
  }

    // =========================================================================
    // REGISTRATION & PRE-REGISTRATION OTP METHODS
    // =========================================================================

  /**
   * Send OTP for pre-registration
   */
  public function sendPreRegistrationOTP(SendOTPRequest $request): JsonResponse
  {
    try {
      $this->customerService->sendPreRegistrationOTP($request->getEmail());

      return response()->json([
        'success' => true,
        'message' => 'OTP sent successfully. Please check your email.',
        'data' => [
          'email' => $request->getEmail(),
          'expires_in_minutes' => 10,
          'otp_length' => 8,
          'otp_type' => 'alphanumeric'
        ]
      ]);
    } catch (Exception $e) {
      Log::error('Send OTP error: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }

  /**
   * Resend OTP for pre-registration
   */
  public function resendPreRegistrationOTP(SendOTPRequest $request): JsonResponse
  {
    try {
      $this->customerService->resendPreRegistrationOTP($request->getEmail());

      return response()->json([
        'success' => true,
        'message' => 'OTP resent successfully. Please check your email.',
        'data' => ['email' => $request->getEmail(), 'expires_in_minutes' => 10]
      ]);
    } catch (Exception $e) {
      Log::error('Resend OTP error: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }

  /**
   * Verify OTP and complete registration
   */
  public function verifyOTPAndRegister(VerifyOTPAndRegisterRequest $request): JsonResponse
  {
    try {
      $request->validatePhoneNumber();
      $user = $this->customerService->verifyOTPAndRegister($request->getServiceData());
      $token = $user->createToken('auth_token', ['basic'])->plainTextToken;
      $welcomeMessage = $this->welcomeService->generateWelcomeMessage($user);

      return response()->json([
        'success' => true,
        'message' => 'Registration successful! ' . $welcomeMessage['message'],
        'data' => [
          'user' => [
            'id' => $user->id,
            'firstname' => $user->first_name,
            'lastname' => $user->last_name,
            'email' => $user->email,
            'phoneNumber' => $user->phone_number,
            'is_verified' => $user->is_verified,
            'wallet_balance' => $user->wallet_balance,
            'points_balance' => $user->points_balance,
            'referral_code' => $user->referral_code,
          ],
          'token' => $token,
          'welcome' => $welcomeMessage,
        ]
      ], 201);
    } catch (ValidationException $e) {
      return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
    } catch (Exception $e) {
      Log::error('Verify OTP and register error: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }

    // =========================================================================
    // EMAIL VERIFICATION METHODS (For existing unverified users only)
    // =========================================================================

  /**
   * Verify email for existing users
   */
  /**
   * Verify email for existing users
   */
  public function verifyEmail(Request $request): JsonResponse
  {
    try {
      $validated = $request->validate([
        'email' => 'required|email',
        'code' => 'required|string|size:8',
      ]);

      $email = strtolower(trim($validated['email']));
      $user = Customer::where('email', $email)->firstOrFail();

      if ($user->email_verified_at || $user->is_verified) {
        return response()->json(['success' => false, 'message' => 'Email already verified'], 400);
      }

      // FIXED: Use the same cache key as in resendVerification
      $cacheKey = "email_verification:{$email}";
      $cachedData = Cache::get($cacheKey);

      if (!$cachedData || $cachedData['code'] !== strtoupper(trim($validated['code']))) {
        return response()->json(['success' => false, 'message' => 'Invalid or expired verification code'], 422);
      }

      $user->email_verified_at = now();
      $user->is_verified = true;
      $user->save();

      Cache::forget($cacheKey);

      return response()->json([
        'success' => true,
        'message' => 'Email verified successfully.',
        'data' => ['user' => ['email' => $user->email, 'is_verified' => true]]
      ]);
    } catch (ValidationException $e) {
      return response()->json(['success' => false, 'message' => 'Verification failed', 'errors' => $e->errors()], 422);
    } catch (Exception $e) {
      Log::error('Email verification error: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }

  /**
   * Resend verification code for existing users
   */
  public function resendVerification(Request $request): JsonResponse
  {
    try {
      $validated = $request->validate(['email' => 'required|email|exists:customers,email']);
      $email = strtolower(trim($validated['email']));
      $user = Customer::where('email', $email)->firstOrFail();

      if ($user->email_verified_at || $user->is_verified) {
        return response()->json(['success' => false, 'message' => 'Email already verified'], 400);
      }

      $verificationCode = $this->generateEmailVerificationCode();
      $cacheKey = "email_verification_code:{$email}";
      Cache::put($cacheKey, strtoupper($verificationCode), now()->addMinutes(10));

      \Illuminate\Support\Facades\Mail::raw(
        "Your email verification code is: {$verificationCode}",
        function ($message) use ($user) {
          $message->to($user->email)
            ->subject('Email Verification Code');
        }
      );

      return response()->json(['success' => true, 'message' => 'Verification code resent successfully.']);
    } catch (ValidationException $e) {
      return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
    } catch (Exception $e) {
      Log::error('Resend verification error: ' . $e->getMessage());
      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }

  /**
   * Generate an email verification code
   */
  private function generateEmailVerificationCode(): string
  {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';

    for ($i = 0; $i < 8; $i++) {
      $code .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $code;
  }

    // =========================================================================
    // TWO-FACTOR AUTHENTICATION METHODS
    // =========================================================================

  /**
   * Get Two Factor Authentication settings
   */
  public function getTwoFactorSettings(Request $request): JsonResponse
  {
    try {
      $user = $request->user();

      return response()->json([
        'success' => true,
        'data' => [
          'is_enabled' => (bool) $user->two_factor_enabled,
          'is_verified' => !is_null($user->two_factor_secret),
        ]
      ]);
    } catch (Exception $e) {
      Log::error('Get 2FA settings error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to fetch 2FA settings'
      ], 500);
    }
  }

  /**
   * Enable Two Factor Authentication
   */
  public function enableTwoFactor(Request $request): JsonResponse
  {
    try {
      $user = $request->user();
      $twoFactorService = app(TwoFactorService::class);
      $result = $twoFactorService->enable($user);

      return response()->json([
        'success' => true,
        'message' => '2FA enabled successfully',
        'data' => $result
      ]);
    } catch (Exception $e) {
      Log::error('Enable 2FA error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to enable 2FA'
      ], 500);
    }
  }

  /**
   * Disable Two Factor Authentication
   */
  public function disableTwoFactor(Request $request): JsonResponse
  {
    try {
      $validated = $request->validate([
        'password' => 'required|string',
      ]);

      $user = $request->user();

      if (!Hash::check($validated['password'], $user->password)) {
        return response()->json([
          'success' => false,
          'message' => 'Invalid password'
        ], 422);
      }

      $user->update([
        'two_factor_enabled' => false,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
      ]);

      return response()->json([
        'success' => true,
        'message' => '2FA disabled successfully'
      ]);
    } catch (ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (Exception $e) {
      Log::error('Disable 2FA error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to disable 2FA'
      ], 500);
    }
  }

  /**
   * Verify Two Factor Authentication code
   */
  public function verifyTwoFactor(Request $request): JsonResponse
  {
    try {
      $validated = $request->validate([
        'code' => 'required|string|size:6',
      ]);

      $user = $request->user();
      $twoFactorService = app(TwoFactorService::class);

      if (!$twoFactorService->verify($user, $validated['code'])) {
        return response()->json([
          'success' => false,
          'message' => 'Invalid 2FA code'
        ], 422);
      }

      return response()->json([
        'success' => true,
        'message' => '2FA verified successfully'
      ]);
    } catch (ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (Exception $e) {
      Log::error('Verify 2FA error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to verify 2FA'
      ], 500);
    }
  }

  /**
   * Regenerate Two Factor Recovery Codes
   */
  public function regenerateRecoveryCodes(Request $request): JsonResponse
  {
    try {
      $user = $request->user();
      $twoFactorService = app(TwoFactorService::class);
      $codes = $twoFactorService->regenerateRecoveryCodes($user);

      return response()->json([
        'success' => true,
        'message' => 'Recovery codes regenerated successfully',
        'data' => ['recovery_codes' => $codes]
      ]);
    } catch (Exception $e) {
      Log::error('Regenerate recovery codes error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to regenerate recovery codes'
      ], 500);
    }
  }
    // =========================================================================
    // PASSWORD RESET METHODS (ADD THESE)
    // =========================================================================

  /**
   * Step 1: Request password reset code
   */
  public function forgotPassword(Request $request): JsonResponse
  {
    try {
      $validated = $request->validate([
        'email' => 'required|email'
      ]);

      $this->customerService->sendPasswordResetCode($validated['email']);

      return response()->json([
        'success' => true,
        'message' => 'If your email is registered, you will receive a password reset code.',
        'data' => [
          'email' => $validated['email'],
          'expires_in_minutes' => 30,
          'code_length' => 6
        ]
      ]);
    } catch (ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (Exception $e) {
      Log::error('Forgot password error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Step 2: Verify reset code
   */
  public function verifyResetCode(Request $request): JsonResponse
  {
    try {
      $validated = $request->validate([
        'email' => 'required|email',
        'code' => 'required|string|size:6'
      ]);

      $isValid = $this->customerService->verifyResetCode(
        $validated['email'],
        $validated['code']
      );

      if (!$isValid) {
        return response()->json([
          'success' => false,
          'message' => 'Invalid or expired reset code.'
        ], 422);
      }

      // Store verification in cache
      $verificationKey = "password_reset_verified:{$validated['email']}";
      Cache::put($verificationKey, true, now()->addMinutes(10));

      return response()->json([
        'success' => true,
        'message' => 'Code verified successfully. You can now reset your password.',
        'data' => [
          'email' => $validated['email'],
          'verified' => true,
          'expires_in_minutes' => 10
        ]
      ]);
    } catch (ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (Exception $e) {
      Log::error('Verify reset code error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Step 3: Reset password with verified code
   */
  public function resetPassword(Request $request): JsonResponse
  {
    try {
      $validated = $request->validate([
        'email' => 'required|email',
        'code' => 'required|string|size:6',
        'password' => [
          'required',
          'string',
          'min:8',
          'confirmed',
          'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'
        ],
        'password_confirmation' => 'required|string|min:8'
      ]);

      // Check if code is verified
      $verificationKey = "password_reset_verified:{$validated['email']}";
      if (!Cache::get($verificationKey)) {
        return response()->json([
          'success' => false,
          'message' => 'Please verify your reset code first.'
        ], 422);
      }

      $user = $this->customerService->resetPasswordWithCode(
        $validated['email'],
        $validated['code'],
        $validated['password']
      );

      // Clear verification
      Cache::forget($verificationKey);

      return response()->json([
        'success' => true,
        'message' => 'Password reset successful. Please login with your new password.',
        'data' => [
          'email' => $user->email,
          'password_reset' => true
        ]
      ]);
    } catch (ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (Exception $e) {
      Log::error('Reset password error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Resend password reset code
   */
  public function resendResetCode(Request $request): JsonResponse
  {
    try {
      $validated = $request->validate([
        'email' => 'required|email'
      ]);

      // Check rate limiting
      $resendKey = "password_reset_resend:{$validated['email']}";
      $resendCount = Cache::get($resendKey, 0);

      if ($resendCount >= 3) {
        return response()->json([
          'success' => false,
          'message' => 'Too many resend attempts. Please try again in 1 hour.'
        ], 429);
      }

      $this->customerService->sendPasswordResetCode($validated['email']);
      Cache::put($resendKey, $resendCount + 1, now()->addHour());

      return response()->json([
        'success' => true,
        'message' => 'Reset code resent successfully.',
        'data' => [
          'email' => $validated['email'],
          'expires_in_minutes' => 30
        ]
      ]);
    } catch (ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (Exception $e) {
      Log::error('Resend reset code error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }

  private function getRemainingAttempts(string $email): int
  {
    $attemptsKey = "password_reset_attempts:{$email}";
    $attempts = Cache::get($attemptsKey, 0);
    return max(0, 3 - $attempts);
  }
}
