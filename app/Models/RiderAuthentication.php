<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RiderAuthentication Model
 * 
 * Manages OTP/authentication codes for riders during:
 * - Email verification
 * - Phone verification
 * - Password reset
 * - Login verification (2FA)
 * - Account recovery
 */
class RiderAuthentication extends Model
{
    use HasFactory;

    // =========================================================================
    // TABLE CONFIGURATION
    // =========================================================================

    /**
     * The table associated with the model.
     */
    protected $table = 'rider_authentications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rider_id',
        'code',
        'type',
        'attempts',
        'expires_at',
        'verified_at',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'code', // Never expose the OTP code in API responses
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Dates
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        // Integers
        'attempts' => 'integer',

        // Strings
        'type' => 'string',
        'ip_address' => 'string',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'attempts' => 0,
        'type' => 'otp',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'is_valid',
        'is_verified',
        'remaining_attempts',
        'expires_in_minutes',
    ];

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    /**
     * Authentication types.
     */
    public const TYPE_OTP = 'otp';
    public const TYPE_EMAIL_VERIFICATION = 'email_verification';
    public const TYPE_PHONE_VERIFICATION = 'phone_verification';
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_TWO_FACTOR = 'two_factor';
    public const TYPE_ACCOUNT_RECOVERY = 'account_recovery';

    /**
     * All available authentication types.
     */
    public static array $types = [
        self::TYPE_OTP,
        self::TYPE_EMAIL_VERIFICATION,
        self::TYPE_PHONE_VERIFICATION,
        self::TYPE_PASSWORD_RESET,
        self::TYPE_TWO_FACTOR,
        self::TYPE_ACCOUNT_RECOVERY,
    ];

    /**
     * Default expiry times (minutes) by type.
     */
    public static array $expiryMinutes = [
        self::TYPE_OTP => 10,
        self::TYPE_EMAIL_VERIFICATION => 60,
        self::TYPE_PHONE_VERIFICATION => 10,
        self::TYPE_PASSWORD_RESET => 15,
        self::TYPE_TWO_FACTOR => 5,
        self::TYPE_ACCOUNT_RECOVERY => 30,
    ];

    /**
     * Max attempts by type.
     */
    public static array $maxAttempts = [
        self::TYPE_OTP => 5,
        self::TYPE_EMAIL_VERIFICATION => 3,
        self::TYPE_PHONE_VERIFICATION => 5,
        self::TYPE_PASSWORD_RESET => 5,
        self::TYPE_TWO_FACTOR => 3,
        self::TYPE_ACCOUNT_RECOVERY => 3,
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the rider that owns this authentication record.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get valid (non-expired, not verified) authentication records.
     */
    public function scopeValid($query)
    {
        return $query->whereNull('verified_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired authentication records.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope to get verified authentication records.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Scope to get unverified authentication records.
     */
    public function scopeUnverified($query)
    {
        return $query->whereNull('verified_at');
    }

    /**
     * Scope to get records by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get records for a specific rider.
     */
    public function scopeForRider($query, int $riderId)
    {
        return $query->where('rider_id', $riderId);
    }

    /**
     * Scope to get recent records (last X minutes).
     */
    public function scopeRecent($query, int $minutes = 15)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Check if authentication is still valid.
     */
    public function getIsValidAttribute(): bool
    {
        return !$this->isExpired() && !$this->isVerified();
    }

    /**
     * Check if authentication is verified.
     */
    public function getIsVerifiedAttribute(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Get remaining attempts.
     */
    public function getRemainingAttemptsAttribute(): int
    {
        $maxAttempts = self::$maxAttempts[$this->type] ?? 5;

        return max(0, $maxAttempts - $this->attempts);
    }

    /**
     * Get minutes until expiration.
     */
    public function getExpiresInMinutesAttribute(): int
    {
        if (!$this->expires_at) {
            return 0;
        }

        $minutesLeft = now()->diffInMinutes($this->expires_at, false);

        return max(0, (int) $minutesLeft);
    }

    /**
     * Get formatted expiry time.
     */
    public function getFormattedExpiryAttribute(): string
    {
        if (!$this->expires_at) {
            return 'Expired';
        }

        if ($this->isExpired()) {
            return 'Expired';
        }

        $minutes = $this->expires_in_minutes;

        if ($minutes < 1) {
            return 'Expires now';
        }

        if ($minutes < 60) {
            return "Expires in {$minutes} minute" . ($minutes !== 1 ? 's' : '');
        }

        $hours = floor($minutes / 60);

        return "Expires in {$hours} hour" . ($hours !== 1 ? 's' : '');
    }

    // =========================================================================
    // BUSINESS LOGIC METHODS
    // =========================================================================

    /**
     * Check if OTP is expired.
     */
    public function isExpired(?int $minutes = null): bool
    {
        $expiryMinutes = $minutes ?? (self::$expiryMinutes[$this->type] ?? 15);

        if (!$this->created_at) {
            return true;
        }

        return $this->created_at->diffInMinutes(now()) > $expiryMinutes;
    }

    /**
     * Check if max attempts reached.
     */
    public function maxAttemptsReached(?int $maxAttempts = null): bool
    {
        $max = $maxAttempts ?? (self::$maxAttempts[$this->type] ?? 5);

        return $this->attempts >= $max;
    }

    /**
     * Check if has remaining attempts.
     */
    public function hasRemainingAttempts(): bool
    {
        return !$this->maxAttemptsReached();
    }

    /**
     * Increment attempts counter.
     */
    public function incrementAttempts(): self
    {
        $this->increment('attempts');

        return $this;
    }

    /**
     * Mark authentication as verified.
     */
    public function markAsVerified(): bool
    {
        $this->verified_at = now();

        return $this->save();
    }

    /**
     * Verify the provided code.
     */
    public function verify(string $code): bool
    {
        // Check if already verified
        if ($this->isVerified()) {
            return false;
        }

        // Check if expired
        if ($this->isExpired()) {
            return false;
        }

        // Check attempts
        if ($this->maxAttemptsReached()) {
            return false;
        }

        // Increment attempts (always increment, even on failure)
        $this->incrementAttempts();

        // Verify code (case-insensitive for alphanumeric)
        if (strtolower($this->code) === strtolower($code)) {
            $this->markAsVerified();

            return true;
        }

        return false;
    }

    /**
     * Generate a new OTP code.
     */
    public static function generateCode(int $length = 6): string
    {
        if ($length <= 6) {
            // Numeric OTP for SMS/voice
            return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
        }

        // Alphanumeric for email/reset
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $code;
    }

    /**
     * Create a new authentication record.
     */
    public static function createForRider(
        int $riderId,
        string $type = self::TYPE_OTP,
        ?string $code = null,
        ?int $expiryMinutes = null
    ): self {
        $code = $code ?? self::generateCode();
        $expiryMinutes = $expiryMinutes ?? (self::$expiryMinutes[$type] ?? 15);

        return self::create([
            'rider_id' => $riderId,
            'type' => $type,
            'code' => $code,
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expiryMinutes),
            'verified_at' => null,
        ]);
    }

    /**
     * Clean up old authentication records.
     */
    public static function cleanup(int $days = 7): int
    {
        return self::where('created_at', '<', now()->subDays($days))
            ->orWhere(function ($query) {
                $query->whereNotNull('verified_at')
                    ->where('verified_at', '<', now()->subDays(1));
            })
            ->delete();
    }

    /**
     * Resend OTP (create new record, invalidate old one).
     */
    public static function resendForRider(
        int $riderId,
        string $type = self::TYPE_OTP
    ): ?self {
        // Invalidate existing valid records
        self::forRider($riderId)
            ->ofType($type)
            ->valid()
            ->update(['expires_at' => now()]);

        // Create new record
        return self::createForRider($riderId, $type);
    }
}
