<?php

namespace App\Services\Rider;

use App\Models\Rider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RiderService
{
    /**
     * Send verification OTP to rider's email and save to database
     */
    public function sendVerificationOtp(Rider $rider): bool
    {
        // Generate 8-character alphanumeric OTP
        $otp = $this->generateAlphanumericOtp(8);
        
        // Save OTP to database
        $rider->otp_code = $otp;
        $rider->otp_expires_at = now()->addMinutes(15);
        $rider->otp_verified_at = null; // Reset verification status
        $rider->save();
        
        try {
            // Send HTML email directly using blade template
            Mail::send('emails.rider-verification-otp', [
                'firstName' => $rider->first_name,
                'lastName' => $rider->last_name,
                'otp' => $otp,
                'email' => $rider->email
            ], function ($message) use ($rider) {
                $message->to($rider->email)
                        ->subject('Your Verification Code - ' . config('app.name'));
            });
            
            Log::info('OTP sent to rider', [
                'email' => $rider->email, 
                'otp' => $otp, // Remove in production
                'expires_at' => $rider->otp_expires_at
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send OTP', [
                'email' => $rider->email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Verify OTP code using database
     */
    public function verifyOtp(Rider $rider, string $otp): bool
    {
        // Check if OTP exists and not expired
        if (!$rider->otp_code || !$rider->otp_expires_at) {
            Log::warning('No OTP found for rider', ['email' => $rider->email]);
            return false;
        }
        
        // Check if OTP is expired
        if (now()->gt($rider->otp_expires_at)) {
            Log::warning('OTP expired', [
                'email' => $rider->email,
                'expired_at' => $rider->otp_expires_at
            ]);
            return false;
        }
        
        // Check if OTP is already verified
        if ($rider->otp_verified_at) {
            Log::warning('OTP already verified', ['email' => $rider->email]);
            return false;
        }
        
        // Verify OTP (case-sensitive comparison)
        if ($rider->otp_code === $otp) {
            // Mark email as verified
            $rider->email_verified_at = now();
            $rider->otp_verified_at = now();
            $rider->save();
            
            Log::info('OTP verified successfully', ['email' => $rider->email]);
            
            return true;
        }
        
        Log::warning('Invalid OTP attempt', [
            'email' => $rider->email,
            'provided_otp' => $otp
        ]);
        
        return false;
    }
    
    /**
     * Resend verification OTP
     */
    public function resendVerificationOtp(Rider $rider): bool
    {
        // Clear existing OTP data
        $rider->otp_code = null;
        $rider->otp_expires_at = null;
        $rider->otp_verified_at = null;
        $rider->save();
        
        // Send new OTP
        return $this->sendVerificationOtp($rider);
    }
    
    /**
     * Generate alphanumeric OTP (numbers + letters)
     */
    private function generateAlphanumericOtp(int $length = 8): string
    {
        // Excluding confusing characters: 0,1,O,I,o,l
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $otp = '';
        
        for ($i = 0; $i < $length; $i++) {
            $otp .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $otp;
    }
    
    /**
     * Check if OTP is expired
     */
    public function isOtpExpired(Rider $rider): bool
    {
        if (!$rider->otp_expires_at) {
            return true;
        }
        
        return now()->gt($rider->otp_expires_at);
    }
    
    /**
     * Get remaining OTP attempts (optional - you can track this separately)
     * For database approach, you might want to add an 'otp_attempts' column
     * to track failed attempts. For now, we'll just return a default.
     */
    public function getRemainingAttempts(Rider $rider): int
    {
        // If you want to track attempts, add 'otp_attempts' column to riders table
        // For now, return 5 (max attempts before needing to resend)
        return 5;
    }
    
    /**
     * Clear OTP from database
     */
    public function clearOtp(Rider $rider): void
    {
        $rider->otp_code = null;
        $rider->otp_expires_at = null;
        $rider->otp_verified_at = null;
        $rider->save();
    }
    
    /**
     * Check if rider has a valid OTP
     */
    public function hasValidOtp(Rider $rider): bool
    {
        return $rider->otp_code !== null 
            && $rider->otp_expires_at !== null 
            && now()->lte($rider->otp_expires_at)
            && $rider->otp_verified_at === null;
    }
}