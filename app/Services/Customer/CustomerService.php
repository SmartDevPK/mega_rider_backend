<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Models\Customer;
use App\Mail\VerifyEmailMail;
use App\Mail\PreRegistrationOTPMail;
use App\Repositories\User\CustomerRepository;
use App\Mail\WelcomeEmail;
use App\Services\NotificationService;
use App\Services\AuditService;
use App\Mail\Customer\PasswordResetConfirmationMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use RuntimeException;

class CustomerService
{
    // =========================================================================
    // CONSTANTS
    // =========================================================================

    private const PASSWORD_PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
    private const VERIFICATION_CODE_LENGTH = 8;
    private const RESET_CODE_LENGTH = 6;
    private const RESET_CODE_EXPIRY_MINUTES = 30;
    private const VERIFICATION_CODE_EXPIRY_MINUTES = 10;
    private const OTP_EXPIRY_MINUTES = 3;
    private const MAX_OTP_ATTEMPTS = 10;
    private const MAX_RESEND_ATTEMPTS = 10;

    // =========================================================================
    // PROPERTIES
    // =========================================================================

    protected CustomerRepository $customerRepository;
    protected NotificationService $notificationService;
    protected AuditService $auditService;

    // =========================================================================
    // CONSTRUCTOR
    // =========================================================================

    public function __construct(
        CustomerRepository $customerRepository,
        NotificationService $notificationService,
        AuditService $auditService
    ) {
        $this->customerRepository = $customerRepository;
        $this->notificationService = $notificationService;
        $this->auditService = $auditService;
    }

    // =========================================================================
    // REGISTRATION & PRE-REGISTRATION OTP METHODS
    // =========================================================================

    /**
     * Step 1: Send OTP for email verification before registration
     */
    public function sendPreRegistrationOTP(string $email): void
    {
        $email = strtolower(trim($email));

        if ($this->customerRepository->emailExists($email)) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered. Please login instead.'],
            ]);
        }

        $this->checkOTPRateLimit($email);
        $otp = $this->generateAlphanumericOTP();

        Cache::put("pre_registration_otp:{$email}", [
            'otp' => $otp,
            'locked_email' => $email,
            'created_at' => now(),
            'attempts' => 0,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
        ], now()->addMinutes(self::OTP_EXPIRY_MINUTES));

        try {
            Mail::to($email)->send(new PreRegistrationOTPMail($otp));
            Log::info("Pre-registration OTP sent", ['email' => $email]);
        } catch (\Exception $e) {
            Log::error("Failed to send pre-registration OTP", ['email' => $email, 'error' => $e->getMessage()]);
            throw new RuntimeException('Unable to send OTP. Please try again.');
        }
    }

    /**
     * Step 2: Verify OTP and complete registration
     */
    public function verifyOTPAndRegister(array $data): Customer
    {
        $registrationEmail = strtolower(trim($data['email']));
        $otp = $data['otp'];

        $cachedData = Cache::get("pre_registration_otp:{$registrationEmail}");

        if (!$cachedData) {
            throw ValidationException::withMessages([
                'email' => ['No OTP request found for this email. Please request OTP first.'],
            ]);
        }

        if ($cachedData['locked_email'] !== $registrationEmail) {
            Cache::forget("pre_registration_otp:{$registrationEmail}");
            throw ValidationException::withMessages([
                'email' => ['Invalid verification. This OTP belongs to a different email address.']
            ]);
        }

        if (strtoupper($cachedData['otp']) !== strtoupper($otp)) {
            $attempts = $cachedData['attempts'] + 1;
            $remainingAttempts = self::MAX_OTP_ATTEMPTS - $attempts;

            if ($attempts >= self::MAX_OTP_ATTEMPTS) {
                Cache::forget("pre_registration_otp:{$registrationEmail}");
                throw ValidationException::withMessages([
                    'otp' => ['Too many failed attempts. Please request a new OTP.'],
                ]);
            }

            Cache::put("pre_registration_otp:{$registrationEmail}", array_merge($cachedData, ['attempts' => $attempts]), now()->addMinutes(self::OTP_EXPIRY_MINUTES));

            throw ValidationException::withMessages([
                'otp' => ["Invalid OTP. {$remainingAttempts} attempts remaining."],
            ]);
        }

        $otpCreatedAt = Carbon::parse($cachedData['created_at']);
        if ($otpCreatedAt->diffInMinutes(now()) > self::OTP_EXPIRY_MINUTES) {
            Cache::forget("pre_registration_otp:{$registrationEmail}");
            throw ValidationException::withMessages([
                'otp' => ['OTP has expired. Please request a new one.'],
            ]);
        }

        if ($this->customerRepository->emailExists($registrationEmail)) {
            Cache::forget("pre_registration_otp:{$registrationEmail}");
            throw ValidationException::withMessages([
                'email' => ['This email is already registered. Please login instead.'],
            ]);
        }

        if ($this->customerRepository->phoneExists($data['phone_number'])) {
            throw ValidationException::withMessages([
                'phone_number' => ['This phone number is already registered.'],
            ]);
        }

        $this->validatePasswordStrength($data['password']);

        return $this->createUserWithPreVerification($data);
    }

    /**
     * Resend pre-registration OTP
     */
    public function resendPreRegistrationOTP(string $email): void
    {
        $email = strtolower(trim($email));

        if ($this->customerRepository->emailExists($email)) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered. Cannot resend OTP.'],
            ]);
        }

        Cache::forget("pre_registration_otp:{$email}");

        $resendKey = "pre_registration_resend:{$email}";
        $resendCount = Cache::get($resendKey, 0);

        if ($resendCount >= self::MAX_RESEND_ATTEMPTS) {
            throw new RuntimeException("Too many resend attempts. Please try again later.");
        }

        $this->sendPreRegistrationOTP($email);
        Cache::put($resendKey, $resendCount + 1, now()->addHour());
    }

    /**
     * Create user with pre-verification
     */
    private function createUserWithPreVerification(array $data): Customer
    {
        $email = strtolower(trim($data['email']));

        if ($this->customerRepository->emailExists($email)) {
            throw new RuntimeException('Email was registered during verification. Please login.');
        }

        DB::beginTransaction();

        try {
            $referralCode = $data['referral_code'] ?? $this->generateReferralCode();

            $userData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone_number' => $data['phone_number'],
                'email' => $email,
                'referral_code' => $referralCode,
                'password' => Hash::make($data['password']),
                'registration_ip' => request()->ip(),
                'email_verified_at' => now(),
                'is_verified' => true,
                'is_active' => true,
            ];

            Log::info('Creating user with data:', array_keys($userData));

            $user = $this->customerRepository->create($userData);

            if (!empty($data['referral_code'])) {
                $this->processReferral($user, $data['referral_code']);
            }

            $this->notificationService->sendWelcomeEmail($user);

            DB::commit();
            Cache::forget("pre_registration_otp:{$email}");

            return $user;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create user: ' . $e->getMessage(), [
                'email' => $data['email'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Failed to create user account: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // EMAIL VERIFICATION METHODS (FOR EXISTING USERS)
    // =========================================================================

    /**
     * Verify email for existing user
     */
    public function verifyEmail(string $email, string $code): Customer
    {
        $email = strtolower(trim($email));
        $cacheKey = "email_verification:{$email}";
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData) {
            throw ValidationException::withMessages([
                'code' => ['Verification code not found or expired. Please request a new one.']
            ]);
        }

        if ($cachedData['code'] !== $code) {
            $attempts = ($cachedData['attempts'] ?? 0) + 1;
            $remainingAttempts = self::MAX_OTP_ATTEMPTS - $attempts;

            if ($attempts >= self::MAX_OTP_ATTEMPTS) {
                Cache::forget($cacheKey);
                throw ValidationException::withMessages([
                    'code' => ['Too many failed attempts. Please request a new verification code.']
                ]);
            }

            Cache::put($cacheKey, array_merge($cachedData, ['attempts' => $attempts]), now()->addMinutes(self::VERIFICATION_CODE_EXPIRY_MINUTES));

            throw ValidationException::withMessages([
                'code' => ["Invalid verification code. {$remainingAttempts} attempts remaining."]
            ]);
        }

        $user = $this->customerRepository->findByEmail($email);

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.']
            ]);
        }

        if ($user->is_verified) {
            Cache::forget($cacheKey);
            return $user;
        }

        DB::beginTransaction();

        try {
            $this->customerRepository->update($user, [
                'email_verified_at' => now(),
                'is_verified' => true
            ]);

            Cache::forget($cacheKey);

            $this->notificationService->sendEmailVerifiedConfirmation($user);

            DB::commit();

            Log::info('Email verified successfully', ['email' => $email, 'user_id' => $user->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Email verification failed', ['email' => $email, 'error' => $e->getMessage()]);
            throw new RuntimeException('Failed to verify email. Please try again.');
        }

        return $user->fresh();
    }

    /**
     * Resend verification code for existing users
     * 
     * @param Customer $user The customer user object
     * @throws ValidationException|RuntimeException
     */
    /**
     * Resend verification code for existing users
     */
    public function resendVerification(Customer $user): void
    {
        // Check if user is already verified
        if ($user->is_verified) {
            throw ValidationException::withMessages([
                'email' => ['Email already verified. No need to resend verification code.']
            ]);
        }

        // Rate limiting
        $rateLimitKey = "resend_verification_rate_limit:{$user->email}";
        $attempts = Cache::get($rateLimitKey, 0);

        if ($attempts >= self::MAX_RESEND_ATTEMPTS) {
            $waitTime = 60;
            throw new RuntimeException("Too many resend attempts. Please try again in {$waitTime} minutes.");
        }

        // Generate new verification code
        $verificationCode = $this->generateAlphanumericOTP();

        // Store in cache
        $cacheKey = "email_verification:{$user->email}";
        Cache::put($cacheKey, [
            'code' => $verificationCode,
            'email' => $user->email,
            'created_at' => now(),
            'attempts' => 0,
        ], now()->addMinutes(self::VERIFICATION_CODE_EXPIRY_MINUTES));

        // Update rate limit
        Cache::put($rateLimitKey, $attempts + 1, now()->addHour());

        // Send email - FIXED: Pass both user and code
        try {
            Mail::to($user->email)->send(new \App\Mail\VerifyEmailMail($user, $verificationCode));
            Log::info('Verification code resent', [
                'email' => $user->email,
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to resend verification code', [
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Unable to send verification code. Please try again.');
        }
    }
    

    // =========================================================================
    // PASSWORD RESET METHODS
    // =========================================================================

    /**
     * Send password reset code
     */
    /**
     * Send password reset code
     */
    public function sendPasswordResetCode(string $email): void
    {
        $email = strtolower(trim($email));

        $user = $this->customerRepository->findByEmail($email);

        if (!$user) {
            Log::info('Password reset requested for non-existent email', ['email' => $email]);
            return;
        }

        $rateLimitKey = "password_reset_rate_limit:{$email}";
        $attempts = Cache::get($rateLimitKey, 0);

        if ($attempts >= 5) {
            $waitTime = 60;
            throw new RuntimeException("Too many reset requests. Please try again in {$waitTime} minutes.");
        }

        // Generate reset code
        $resetCode = $this->generateNumericResetCode();

        // Store the code
        $cacheKey = 'password_reset_' . $email;
        Cache::put($cacheKey, $resetCode, now()->addMinutes(self::RESET_CODE_EXPIRY_MINUTES));

        // Update rate limit
        Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes(60));

        // Send email directly with the code
        try {
            Mail::to($email)->send(new \App\Mail\Customer\PasswordResetMail($user, (string)$resetCode));
            Log::info('Password reset code sent', ['email' => $email]);
        } catch (\Exception $e) {
            Log::error('Failed to send password reset code', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Unable to send reset code. Please try again.');
        }
    }

    /**
     * Reset password using code
     */
    public function resetPasswordWithCode(string $email, string $code, string $newPassword): Customer
    {
        $email = strtolower(trim($email));

        $cacheKey = 'password_reset_' . $email;
        $storedCode = Cache::get($cacheKey);

        if (!$storedCode) {
            throw ValidationException::withMessages([
                'code' => ['Reset code has expired. Please request a new one.']
            ]);
        }

        if ((string)$storedCode !== (string)$code) {
            $attemptsKey = "password_reset_attempts:{$email}";
            $failedAttempts = Cache::get($attemptsKey, 0) + 1;

            if ($failedAttempts >= 3) {
                Cache::forget($cacheKey);
                Cache::forget($attemptsKey);
                throw ValidationException::withMessages([
                    'code' => ['Too many failed attempts. Please request a new reset code.']
                ]);
            }

            Cache::put($attemptsKey, $failedAttempts, now()->addMinutes(30));

            throw ValidationException::withMessages([
                'code' => ['Invalid reset code. ' . (3 - $failedAttempts) . ' attempts remaining.']
            ]);
        }

        $user = $this->customerRepository->findByEmail($email);

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.']
            ]);
        }

        $this->validatePasswordStrength($newPassword);

        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['New password cannot be the same as the old password.']
            ]);
        }

        DB::beginTransaction();

        try {
            $this->customerRepository->update($user, [
                'password' => Hash::make($newPassword)
            ]);

            Cache::forget($cacheKey);
            Cache::forget("password_reset_attempts:{$email}");
            Cache::forget("password_reset_rate_limit:{$email}");

            $this->notificationService->sendPasswordResetConfirmation($user);

            DB::commit();

            Log::info('Password reset successful', ['email' => $email, 'user_id' => $user->id]);

            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Password reset failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Failed to reset password. Please try again.');
        }

        return $user->fresh();
    }

    /**
     * Verify reset code without changing password
     */
    public function verifyResetCode(string $email, string $code): bool
    {
        $email = strtolower(trim($email));
        $cacheKey = 'password_reset_' . $email;
        $storedCode = Cache::get($cacheKey);

        if (!$storedCode) {
            return false;
        }

        return (string)$storedCode === (string)$code;
    }

    // =========================================================================
    // REFERRAL METHODS
    // =========================================================================

    /**
     * Process referral
     */
    private function processReferral(Customer $user, string $referralCode): void
    {
        try {
            $referrer = Customer::where('referral_code', $referralCode)->first();
            if ($referrer) {
                $referrer->increment('points_balance', 50);
                Log::info('Referral processed', ['new_user_id' => $user->id, 'referrer_id' => $referrer->id]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to process referral', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate referral code
     */
    public function generateReferralCode(int $length = 8): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while ($this->customerRepository->referralCodeExists($code));
        return $code;
    }

    // =========================================================================
    // HELPER & UTILITY METHODS
    // =========================================================================

    /**
     * Check OTP rate limit
     */
    private function checkOTPRateLimit(string $email): void
    {
        $requestKey = "pre_registration_request:{$email}";
        $requestCount = Cache::get($requestKey, 0);

        if ($requestCount >= self::MAX_RESEND_ATTEMPTS) {
            throw new RuntimeException("Too many OTP requests. Please try again later.");
        }

        Cache::put($requestKey, $requestCount + 1, now()->addHour());
    }

    /**
     * Generate alphanumeric OTP
     */
    private function generateAlphanumericOTP(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $otp = '';
        for ($i = 0; $i < self::VERIFICATION_CODE_LENGTH; $i++) {
            $otp .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $otp;
    }

    /**
     * Generate numeric reset code
     */
    private function generateNumericResetCode(): string
    {
        return sprintf("%06d", random_int(0, 999999));
    }

    /**
     * Generate alphanumeric reset code
     */
    private function generateAlphanumericResetCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < self::RESET_CODE_LENGTH; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }

    /**
     * Validate password strength
     */
    private function validatePasswordStrength(string $password): void
    {
        if (!preg_match(self::PASSWORD_PATTERN, $password)) {
            throw ValidationException::withMessages([
                'password' => ['Password must be at least 8 characters and include uppercase, lowercase, number, and special character.']
            ]);
        }
    }
}
