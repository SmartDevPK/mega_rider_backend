<?php

namespace App\Services;

use App\Models\User;
use App\Models\LoginAttempt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class LoginService
{
    protected $maxAttempts = 4;
    protected $lockoutTime = 15; // minutes
    protected $decayMinutes = 15; // minutes for attempt counter decay

    public function login(array $data)
    {
        // Check if account is locked
        if ($this->isAccountLocked($data['email'])) {
            $lockedUntil = Cache::get($this->getLockKey($data['email']));
            $minutesLeft = ceil(Carbon::parse($lockedUntil)->diffInMinutes(now()));
            
            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Your account is locked for {$minutesLeft} minutes."],
            ]);
        }

        // Check for too many attempts
        if ($this->hasTooManyLoginAttempts($data['email'])) {
            $this->lockAccount($data['email']);
            
            throw ValidationException::withMessages([
                'email' => ["Too many failed attempts. Your account is locked for {$this->lockoutTime} minutes."],
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        // Validate credentials
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->incrementLoginAttempts($data['email']);
            
            // Log failed attempt
            $this->logFailedAttempt($data['email'], request()->ip());
            
            $attemptsLeft = $this->maxAttempts - $this->getLoginAttempts($data['email']);
            
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials. ' . max(0, $attemptsLeft) . ' attempts remaining.'],
            ]);
        }

        // Check if email is verified
        if (!$user->is_verified) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email address before logging in.'],
            ]);
        }

        // Check if account is active
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }

        // Check if email verification is expired (optional)
        if ($this->isEmailVerificationExpired($user)) {
            $this->resendVerificationCode($user);
            throw ValidationException::withMessages([
                'email' => ['Your email verification code has expired. A new code has been sent.'],
            ]);
        }

        // Login successful - clear attempts
        $this->clearLoginAttempts($data['email']);
        
        // Log successful login
        $this->logSuccessfulLogin($user, request()->ip());

        // Update last login info
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
            'login_count' => $user->login_count + 1,
        ]);

        // Create token with abilities/scopes
        $token = $user->createToken('auth_token', ['basic'])->plainTextToken;

        // Check if device is trusted (optional)
        $isTrustedDevice = $this->isTrustedDevice($user, request()->userAgent());

        return [
            'user' => $user,
            'token' => $token,
            'requires_2fa' => $user->two_factor_enabled,
            'is_trusted_device' => $isTrustedDevice,
            'login_history' => $this->getRecentLoginHistory($user),
        ];
    }

    /**
     * Check if account is locked
     */
    protected function isAccountLocked($email)
    {
        return Cache::has($this->getLockKey($email));
    }

    /**
     * Lock the account
     */
    protected function lockAccount($email)
    {
        Cache::put(
            $this->getLockKey($email),
            now()->addMinutes($this->lockoutTime),
            now()->addMinutes($this->lockoutTime)
        );
        
        // Log the lockout
        $this->logAccountLock($email, request()->ip());
        
        // Clear attempts after locking
        $this->clearLoginAttempts($email);
    }

    /**
     * Check if too many login attempts
     */
    protected function hasTooManyLoginAttempts($email)
    {
        return $this->getLoginAttempts($email) >= $this->maxAttempts;
    }

    /**
     * Get login attempts count
     */
    protected function getLoginAttempts($email)
    {
        return Cache::get($this->getAttemptsKey($email), 0);
    }

    /**
     * Increment login attempts
     */
    protected function incrementLoginAttempts($email)
    {
        Cache::put(
            $this->getAttemptsKey($email),
            $this->getLoginAttempts($email) + 1,
            now()->addMinutes($this->decayMinutes)
        );
    }

    /**
     * Clear login attempts
     */
    protected function clearLoginAttempts($email)
    {
        Cache::forget($this->getAttemptsKey($email));
    }

    /**
     * Get lock cache key
     */
    protected function getLockKey($email)
    {
        return 'login_lock_' . md5($email);
    }

    /**
     * Get attempts cache key
     */
    protected function getAttemptsKey($email)
    {
        return 'login_attempts_' . md5($email);
    }

    /**
     * Log failed login attempt to database
     */
    protected function logFailedAttempt($email, $ip)
    {
        // Create a login_attempts table first, then implement this
        LoginAttempt::create([
            'email' => $email,
            'ip_address' => $ip,
            'user_agent' => request()->userAgent(),
            'success' => false,
            'attempted_at' => now(),
        ]);
    }

    /**
     * Log successful login
     */
    protected function logSuccessfulLogin($user, $ip)
    {
        LoginAttempt::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $ip,
            'user_agent' => request()->userAgent(),
            'success' => true,
            'attempted_at' => now(),
        ]);
    }

    /**
     * Log account lock
     */
    protected function logAccountLock($email, $ip)
    {
        LoginAttempt::create([
            'email' => $email,
            'ip_address' => $ip,
            'user_agent' => request()->userAgent(),
            'success' => false,
            'is_lockout' => true,
            'attempted_at' => now(),
        ]);
    }

    /**
     * Check if email verification is expired
     */
    protected function isEmailVerificationExpired($user)
    {
        if ($user->email_verified_at) {
            return false;
        }

        // Expire after 24 hours
        $expirationTime = 24 * 60; // minutes
        return $user->email_verification_sent_at 
            && now()->diffInMinutes($user->email_verification_sent_at) > $expirationTime;
    }

    /**
     * Resend verification code
     */
    protected function resendVerificationCode($user)
    {
        // Implement your verification code resend logic
    }

    /**
     * Check if device is trusted
     */
    protected function isTrustedDevice($user, $userAgent)
    {
        // Check if this device has been used before successfully
        return LoginAttempt::where('user_id', $user->id)
            ->where('user_agent', $userAgent)
            ->where('success', true)
            ->exists();
    }

    /**
     * Get recent login history
     */
    protected function getRecentLoginHistory($user)
    {
        return LoginAttempt::where('user_id', $user->id)
            ->where('success', true)
            ->latest('attempted_at')
            ->limit(5)
            ->get(['ip_address', 'user_agent', 'attempted_at']);
    }
}