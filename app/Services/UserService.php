<?php

namespace App\Services;

use App\Models\User;
use App\Mail\VerifyEmailMail;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Str;
use Carbon\Carbon; // Add this import

class UserService
{
    private const PASSWORD_PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
    private const VERIFICATION_CODE_LENGTH = 8;
    private const RESET_CODE_LENGTH = 6;

    // ------------------------------
    // Registration
    // ------------------------------

    /**
     * Register a new user
     *
     * @throws ValidationException
     */
    public function register(array $data): User
    {
        $this->validateUniqueCredentials($data);
        $this->validatePasswordStrength($data['password']);

        $user = $this->createUser($data);
        $this->sendVerificationEmail($user);

        return $user;
    }

    /**
     * Validate unique email and phone
     *
     * @throws ValidationException
     */
    private function validateUniqueCredentials(array $data): void
    {
        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered. Please login instead.'],
            ]);
        }

        if (User::where('phoneNumber', $data['phoneNumber'])->exists()) {
            throw ValidationException::withMessages([
                'phoneNumber' => ['This phone number is already registered.'],
            ]);
        }
    }

    /**
     * Validate password strength
     *
     * @throws ValidationException
     */
    private function validatePasswordStrength(string $password): void
    {
        if (!preg_match(self::PASSWORD_PATTERN, $password)) {
            throw ValidationException::withMessages([
                'password' => [
                    'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.'
                ],
            ]);
        }
    }

    /**
     * Create new user
     */
    private function createUser(array $data): User
    {
        return User::create([
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'phoneNumber' => $data['phoneNumber'],
            'email' => $data['email'],
            'referralCode' => $data['referralCode'] ?? $this->generateReferralCode(),
            'password' => Hash::make($data['password']),
            'email_verification_code' => $this->generateVerificationCode(),
            'email_verification_sent_at' => now(),
            'is_verified' => false,
        ]);
    }

    /**
     * Generate verification code
     */
    private function generateVerificationCode(): string
    {
        return strtoupper(Str::random(self::VERIFICATION_CODE_LENGTH));
    }

    /**
     * Send verification email
     */
    private function sendVerificationEmail(User $user): void
    {
        Mail::to($user->email)->send(new VerifyEmailMail($user));
    }

    // ------------------------------
    // Email Verification
    // ------------------------------

    /**
     * Verify email using code
     *
     * @throws ValidationException
     */
    public function verifyEmail(string $email, string $code): User
    {
        $user = User::where('email', $email)
            ->where('email_verification_code', $code)
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid.'],
            ]);
        }

        $user->update([
            'is_verified' => true,
            'email_verification_code' => null,
            'email_verified_at' => now(),
        ]);

        return $user;
    }

    /**
     * Resend verification code
     */
    public function resendVerification(User $user): void
    {
        $user->update([
            'email_verification_code' => $this->generateVerificationCode(),
            'email_verification_sent_at' => now(),
        ]);

        Mail::to($user->email)->send(new VerifyEmailMail($user));
    }

    // ------------------------------
    // User Updates & Deletion
    // ------------------------------

    /**
     * Update user information
     *
     * @throws ValidationException
     */
    public function update(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $this->validatePasswordStrength($data['password']);
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $user->fresh();
    }

    /**
     * Delete user account
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }

    // ------------------------------
    // Password Reset
    // ------------------------------

    /**
     * Send password reset code
     */
    public function sendPasswordReset(User $user): string
    {
        // Generate 6-digit reset code
        $resetCode = $this->generateResetCode();
        
        // Save to database with expiry
        $user->password_reset_code = $resetCode;
        $user->password_reset_expires_at = Carbon::now()->addMinutes(30);
        $user->save();
        
        // Send notification via email
        try {
            $user->notify(new PasswordResetCode($resetCode));
            \Log::info("Password reset code sent to: {$user->email}");
        } catch (\Exception $e) {
            \Log::error("Failed to send password reset email: " . $e->getMessage());
            throw new \Exception('Unable to send reset code. Please try again later.');
        }
        
        return $resetCode;
    }

    /**
     * Generate reset code
     */
    private function generateResetCode(): string
    {
        return strtoupper(Str::random(self::RESET_CODE_LENGTH));
    }

    /**
     * Reset password using reset code
     *
     * @throws ValidationException
     */
    public function resetPassword(string $email, string $code, string $newPassword): User
    {
        // Find user with valid reset code and not expired
        $user = User::where('email', $email)
            ->where('password_reset_code', $code)
            ->where('password_reset_expires_at', '>', Carbon::now())
            ->first();
            
        if (!$user) {
            throw new \Exception('Invalid or expired reset code');
        }
        
        // Validate password strength
        $this->validatePasswordStrength($newPassword);
        
        // Update password and clear reset code
        $user->password = Hash::make($newPassword);
        $user->password_reset_code = null;
        $user->password_reset_expires_at = null;
        $user->save();
        
        \Log::info("Password reset successfully for: {$email}");
        
        return $user;
    }

    // ------------------------------
    // Utility Methods
    // ------------------------------

    /**
     * Get user's full name
     */
    public function getFullName(User $user): string
    {
        return trim("{$user->firstname} {$user->lastname}");
    }

    /**
     * Generate unique referral code
     */
    public function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (User::where('referralCode', $code)->exists());

        return $code;
    }

    /**
     * Find user by referral code
     */
    public function findByReferralCode(string $code): ?User
    {
        return User::where('referralCode', $code)->first();
    }
}