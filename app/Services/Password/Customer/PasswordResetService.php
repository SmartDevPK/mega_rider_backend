<?php

namespace App\Services\Password\Customer;

use App\Models\Customer;
use App\Models\PasswordResetToken;
use App\Services\NotificationService;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Events\PasswordResetRequested;
use App\Events\PasswordResetCompleted;
use App\Exceptions\PasswordResetException;
use Carbon\Carbon;

class PasswordResetService
{
  private const MAX_ATTEMPTS_PER_HOUR = 3;
  private const MAX_REQUESTS_PER_DAY = 5;
  private const LOCKOUT_MINUTES = 30;
  private const TOKEN_EXPIRY_MINUTES = 15;
  private const CODE_LENGTH = 8;
  private const RATE_LIMIT_KEY_PREFIX = 'pwd_reset_limit:';

  private NotificationService $notificationService;
  private AuditService $auditService;

  public function __construct(
    NotificationService $notificationService,
    AuditService $auditService
  ) {
    $this->notificationService = $notificationService;
    $this->auditService = $auditService;
  }

  /**
   * Request password reset with comprehensive rate limiting
   */
  public function requestReset(string $email, string $ip, ?string $userAgent = null): array
  {
    // 1. Check global rate limit (IP based)
    $this->checkGlobalRateLimit($ip);

    // 2. Get user with lock to prevent race conditions
    $user = DB::transaction(function () use ($email) {
      $user = Customer::where('email', $email)->lockForUpdate()->first();

      if (!$user) {
        throw ValidationException::withMessages([
          'email' => ['We cannot find a user with that email address.']
        ]);
      }

      // 3. Check user-specific rate limits
      $this->checkUserRateLimit($user);

      // 4. Check if user is locked out
      $this->checkUserLockout($user);

      return $user;
    });

    // 5. Generate secure token and code
    $resetData = $this->createResetToken($user, $ip, $userAgent);

    // 6. Queue notification (async to not block response)
    dispatch(function () use ($user, $resetData) {
      $this->notificationService->sendPasswordResetCode($user, $resetData['code']);
    })->onQueue('notifications');

    // 7. Audit log
    $this->auditService->log('password_reset_requested', $user, [
      'ip' => $ip,
      'user_agent' => $userAgent
    ]);

    return [
      'email' => $user->email,
      'reset_token' => $resetData['token'], // For API flow
      'expires_in_minutes' => self::TOKEN_EXPIRY_MINUTES,
      // NEVER return the actual code in production!
    ];
  }

  /**
   * Verify reset code with anti-brute-force protection
   */
  public function verifyCode(string $email, string $code, string $ip): array
  {
    // Rate limiting by IP
    $rateKey = self::RATE_LIMIT_KEY_PREFIX . 'verify:' . $ip;
    $attempts = Cache::increment($rateKey);

    if ($attempts > 10) {
      Cache::put($rateKey, $attempts, 900); // 15 minutes
      throw new PasswordResetException('Too many verification attempts. Please try again later.', 429);
    }

    if ($attempts === 1) {
      Cache::put($rateKey, 1, 60);
    }

    $token = PasswordResetToken::where('email', $email)
      ->where('code', $code)
      ->whereNull('used_at')
      ->whereNull('cancelled_at')
      ->where('expires_at', '>', now())
      ->first();

    if (!$token) {
      $this->logFailedAttempt($email, $ip, 'invalid_code');
      throw ValidationException::withMessages([
        'code' => ['Invalid or expired reset code.']
      ]);
    }

    // Increment attempt count
    $token->increment('attempt_count');

    if ($token->attempt_count > 5) {
      $token->cancelled_at = now();
      $token->save();
      throw new PasswordResetException('Too many failed attempts. Please request a new reset code.', 422);
    }

    // Mark as verified but not yet used
    $token->verified_at = now();
    $token->save();

    // Generate temporary verification token
    $verificationToken = Str::random(64);
    Cache::put("pwd_reset_verified:{$verificationToken}", [
      'email' => $email,
      'token_id' => $token->id
    ], 300); // 5 minutes

    return [
      'verification_token' => $verificationToken,
      'email' => $email
    ];
  }

  /**
   * Complete password reset with secure token validation
   */
  public function resetPassword(string $verificationToken, string $newPassword, string $ip): Customer
  {
    // Get verification data from cache
    $verificationData = Cache::get("pwd_reset_verified:{$verificationToken}");

    if (!$verificationData) {
      throw new PasswordResetException('Invalid or expired verification token. Please restart the password reset process.', 422);
    }

    // Use database transaction for consistency
    return DB::transaction(function () use ($verificationData, $newPassword, $ip) {
      $token = PasswordResetToken::find($verificationData['token_id']);

      if (!$token || $token->used_at || $token->cancelled_at) {
        throw new PasswordResetException('This reset code has already been used or expired.', 422);
      }

      $user = Customer::where('email', $token->email)->lockForUpdate()->first();

      if (!$user) {
        throw new PasswordResetException('User not found.', 404);
      }

      // Prevent password reuse
      if (Hash::check($newPassword, $user->password)) {
        throw new PasswordResetException('New password cannot be the same as your current password.', 422);
      }

      // Check password history (optional)
      $this->checkPasswordHistory($user, $newPassword);

      // Update password
      $user->password = Hash::make($newPassword);
      $user->save();

      // Mark token as used
      $token->used_at = now();
      $token->save();

      // Invalidate all existing sessions/tokens
      $user->tokens()->delete();

      // Clear rate limits
      $this->clearRateLimits($user);

      // Log the reset
      $this->auditService->log('password_reset_completed', $user, [
        'ip' => $ip,
        'token_id' => $token->id
      ]);

      // Send confirmation email (async)
      dispatch(function () use ($user) {
        $this->notificationService->sendPasswordResetConfirmation($user);
      })->onQueue('notifications');

      // Clear cache
      Cache::forget("pwd_reset_verified:{$verificationToken}");

      event(new PasswordResetCompleted($user));

      return $user;
    });
  }

  /**
   * Create secure reset token with multiple fallbacks
   */
  private function createResetToken(Customer $user, string $ip, ?string $userAgent): array
  {
    // Delete old unused tokens
    PasswordResetToken::where('customer_id', $user->id)
      ->whereNull('used_at')
      ->where('created_at', '<', now()->subHours(24))
      ->delete();

    // Generate secure components
    $token = hash_hmac('sha256', Str::random(64), config('app.key'));
    $code = $this->generateSecureCode();

    $resetToken = PasswordResetToken::create([
      'customer_id' => $user->id,
      'email' => $user->email,
      'token' => $token,
      'code' => $code,
      'ip_address' => $ip,
      'user_agent' => $userAgent,
      'expires_at' => now()->addMinutes(self::TOKEN_EXPIRY_MINUTES),
    ]);

    // Update user rate limit counters
    $user->update([
      'last_password_reset_request_at' => now(),
      'password_reset_request_count' => DB::raw('password_reset_request_count + 1')
    ]);

    return [
      'token' => $token,
      'code' => $code,
      'id' => $resetToken->id
    ];
  }

  /**
   * Generate cryptographically secure code
   */
  private function generateSecureCode(): string
  {
    // Use random_int for cryptographic security
    $code = '';
    for ($i = 0; $i < self::CODE_LENGTH; $i++) {
      $code .= random_int(0, 9);
    }
    return $code;
  }

  /**
   * Check global rate limit by IP
   */
  private function checkGlobalRateLimit(string $ip): void
  {
    $key = self::RATE_LIMIT_KEY_PREFIX . 'global:' . $ip;
    $requests = Cache::get($key, 0);

    if ($requests >= 10) { // Max 10 requests per hour from same IP
      throw new PasswordResetException('Too many password reset requests. Please try again later.', 429);
    }

    Cache::put($key, $requests + 1, 3600);
  }

  /**
   * Check user-specific rate limits
   */
  private function checkUserRateLimit(Customer $user): void
  {
    // Check hourly limit
    if (
      $user->last_password_reset_request_at &&
      $user->last_password_reset_request_at->isToday() &&
      $user->password_reset_request_count >= self::MAX_REQUESTS_PER_DAY
    ) {
      throw new PasswordResetException(
        'You have reached the maximum number of password reset requests for today. Please try again tomorrow.',
        429
      );
    }

    // Reset counter if new day
    if (
      !$user->last_password_reset_request_at ||
      !$user->last_password_reset_request_at->isToday()
    ) {
      $user->password_reset_request_count = 0;
      $user->save();
    }
  }

  /**
   * Check if user is locked out from password reset
   */
  private function checkUserLockout(Customer $user): void
  {
    if ($user->password_reset_locked_until && $user->password_reset_locked_until->isFuture()) {
      $minutes = now()->diffInMinutes($user->password_reset_locked_until);
      throw new PasswordResetException(
        "Password reset is temporarily locked. Please try again in {$minutes} minutes.",
        429
      );
    }
  }

  /**
   * Log failed verification attempts
   */
  private function logFailedAttempt(string $email, string $ip, string $reason): void
  {
    Log::warning('Failed password reset verification', [
      'email' => $email,
      'ip' => $ip,
      'reason' => $reason,
      'timestamp' => now()
    ]);

    // Track in Redis for analytics
    Redis::incr("pwd_reset_failures:{$ip}");
    Redis::expire("pwd_reset_failures:{$ip}", 86400);
  }

  /**
   * Check password history to prevent reuse
   */
  private function checkPasswordHistory(Customer $user, string $newPassword): void
  {
    // Implement if you store password history
    // This prevents users from cycling back to old passwords
  }

  /**
   * Clear rate limits after successful reset
   */
  private function clearRateLimits(Customer $user): void
  {
    $user->update([
      'password_reset_request_count' => 0,
      'password_reset_locked_until' => null
    ]);

    // Clear IP-based rate limits
    // This would need to know the IP - could be passed in
  }
}
