<?php

namespace App\Services\Notification\Email;

use App\Models\Rider;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
  /**
   * Verify rider's email address using verification ID.
   *
   * @param int $id The rider's ID
   * @return array Verification result
   * @throws ValidationException
   */
  public function verifyRiderEmail(int $id): array
  {
    // Find the rider
    $rider = $this->findRiderById($id);

    if (!$rider) {
      throw ValidationException::withMessages([
        'verification' => ['Invalid verification link.']
      ]);
    }

    // Check if already verified
    if ($this->isEmailAlreadyVerified($rider)) {
      return [
        'message' => 'Email already verified.',
        'verified' => true,
        'rider_id' => $rider->id,
        'email' => $rider->email,
      ];
    }

    // Mark email as verified
    $this->markEmailAsVerified($rider);

    // Return success response
    return [
      'message' => 'Email verified successfully.',
      'verified' => true,
      'rider_id' => $rider->id,
      'email' => $rider->email,
      'verified_at' => $rider->fresh()->email_verified_at,
    ];
  }

  /**
   * Find rider by ID.
   */
  protected function findRiderById(int $id): ?Rider
  {
    return Rider::find($id);
  }

  /**
   * Check if rider's email is already verified.
   */
  protected function isEmailAlreadyVerified(Rider $rider): bool
  {
    return !is_null($rider->email_verified_at);
  }

  /**
   * Mark rider's email as verified.
   */
  protected function markEmailAsVerified(Rider $rider): Rider
  {
    $rider->update([
      'email_verified_at' => Carbon::now(),
      'email_verification_code' => null, // Clear any verification code if exists
    ]);

    return $rider->fresh();
  }

  /**
   * Resend verification email to rider.
   *
   * @param Rider $rider
   * @return bool
   */
  public function resendVerificationEmail(Rider $rider): bool
  {
    // Check if email is already verified
    if ($this->isEmailAlreadyVerified($rider)) {
      throw ValidationException::withMessages([
        'email' => ['Email is already verified.']
      ]);
    }

    // Generate new verification code
    $verificationCode = $this->generateVerificationCode();

    // Update rider with new verification code
    $rider->update([
      'email_verification_code' => $verificationCode,
      'email_verification_sent_at' => Carbon::now(),
    ]);

    // Send verification email (implement this based on your mail system)
    // Mail::to($rider->email)->send(new VerifyRiderEmailMail($rider, $verificationCode));

    return true;
  }

  /**
   * Generate a unique verification code.
   */
  protected function generateVerificationCode(): string
  {
    return strtoupper(substr(md5(uniqid() . random_int(100000, 999999)), 0, 8));
  }

  /**
   * Verify rider email using code instead of ID.
   *
   * @param string $email
   * @param string $code
   * @return array
   * @throws ValidationException
   */
  public function verifyRiderEmailByCode(string $email, string $code): array
  {
    // Find rider by email and verification code
    $rider = Rider::where('email', $email)
      ->where('email_verification_code', $code)
      ->first();

    if (!$rider) {
      throw ValidationException::withMessages([
        'code' => ['Invalid verification code.']
      ]);
    }

    // Check if already verified
    if ($this->isEmailAlreadyVerified($rider)) {
      return [
        'message' => 'Email already verified.',
        'verified' => true,
      ];
    }

    // Mark as verified
    $this->markEmailAsVerified($rider);

    return [
      'message' => 'Email verified successfully.',
      'verified' => true,
    ];
  }

  /**
   * Check if a rider's email is verified.
   */
  public function isVerified(Rider $rider): bool
  {
    return $this->isEmailAlreadyVerified($rider);
  }

  /**
   * Get verification status with details.
   */
  public function getVerificationStatus(Rider $rider): array
  {
    return [
      'is_verified' => $this->isEmailAlreadyVerified($rider),
      'verified_at' => $rider->email_verified_at,
      'email' => $rider->email,
      'verification_sent_at' => $rider->email_verification_sent_at,
    ];
  }

  /**
   * Delete unverified rider account (cleanup old unverified accounts).
   */
  public function deleteUnverifiedRider(Rider $rider): bool
  {
    // Only allow deletion if email is not verified
    if ($this->isEmailAlreadyVerified($rider)) {
      throw ValidationException::withMessages([
        'rider' => ['Cannot delete a verified rider account.']
      ]);
    }

    return $rider->delete();
  }

  /**
   * Clean up unverified riders older than specified days.
   */
  public function cleanupUnverifiedRiders(int $days = 7): int
  {
    $cutoffDate = Carbon::now()->subDays($days);

    return Rider::whereNull('email_verified_at')
      ->where('created_at', '<', $cutoffDate)
      ->delete();
  }
}
