<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Customer extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    // =========================================================================
    // TABLE CONFIGURATION
    // =========================================================================

    protected $table = 'customers';

    protected $fillable = [
        // Authentication
        'phone_number',
        'email',
        'password',

        // Personal information
        'first_name',
        'last_name',
        'profile_picture',
        'address',
        'latitude',
        'longitude',

        // Referral system
        'referral_code',
        'referred_by',
        'referral_rewarded',

        // Financial
        'wallet_balance',
        'points_balance',

        // Registration tracking
        'registration_ip',

        // Ride statistics
        'total_rides',
        'total_spent',

        // Preferences
        'notification_preferences',
        'timezone',
        'locale',
        'zone_id',

        // Account status
        'is_active',
        'is_verified',
        'email_verified_at',
        'phone_verified_at',

        // Security
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',

        // Tracking
        'last_login_at',
        'last_login_ip',
        'login_count',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        // Datetime casts
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',

        // Decimal casts
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'wallet_balance' => 'decimal:2',
        'total_spent' => 'decimal:2',

        // Integer casts
        'points_balance' => 'integer',
        'total_rides' => 'integer',
        'login_count' => 'integer',

        // Boolean casts
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'referral_rewarded' => 'boolean',

        // Other casts
        'password' => 'hashed',
        'notification_preferences' => 'array',
        'two_factor_recovery_codes' => 'array',
    ];

    protected $attributes = [
        'is_verified' => false,
        'is_active' => true,
        'two_factor_enabled' => false,
        'login_count' => 0,
        'wallet_balance' => 0,
        'points_balance' => 0,
        'total_rides' => 0,
        'total_spent' => 0,
        'referral_rewarded' => false,
        'timezone' => 'UTC',
        'locale' => 'en',
    ];

    // =========================================================================
    // JWT AUTHENTICATION
    // =========================================================================

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    // =========================================================================
    // ACCESSORS & MUTATORS
    // =========================================================================

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }

    public function getFormattedWalletBalanceAttribute(): string
    {
        return '₦' . number_format((float) $this->wallet_balance, 2);
    }

    public function setFirstNameAttribute($value): void
    {
        $this->attributes['first_name'] = $value !== null ? ucfirst(strtolower($value)) : null;
    }

    public function setLastNameAttribute($value): void
    {
        $this->attributes['last_name'] = $value !== null ? ucfirst(strtolower($value)) : null;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function activeOrders()
    {
        return $this->orders()
            ->whereIn('status', ['pending', 'assigned', 'picked_up'])
            ->where('is_draft', false);
    }

    public function completedOrders()
    {
        return $this->orders()
            ->where('status', 'delivered');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }

    public function recentFailedAttempts(): HasMany
    {
        return $this->loginAttempts()
            ->where('success', false)
            ->where('attempted_at', '>=', now()->subHours(24))
            ->latest('attempted_at');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    // =========================================================================
    // ROLE CHECKING
    // =========================================================================

    public function isCustomer(): bool
    {
        return true;
    }

    public function isAdmin(): bool
    {
        return $this->role ?? false;
    }

    // =========================================================================
    // EMAIL VERIFICATION METHODS
    // =========================================================================

    /**
     * Check if email is verified
     */
    public function isEmailVerified(): bool
    {
        return !is_null($this->email_verified_at) && $this->is_verified === true;
    }

    /**
     * Check if email is verified (alias for consistency)
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->isEmailVerified();
    }

    /**
     * Mark email as verified
     */
    public function markEmailAsVerified(): bool
    {
        $this->email_verified_at = now();
        $this->is_verified = true;
        return $this->save();
    }

    /**
     * Check if phone is verified
     */
    public function hasVerifiedPhone(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    /**
     * Mark phone as verified
     */
    public function markPhoneAsVerified(): bool
    {
        $this->phone_verified_at = now();
        return $this->save();
    }

    /**
     * Get verification status
     */
    public function getVerificationStatus(): array
    {
        return [
            'is_verified' => $this->isEmailVerified(),
            'verified_at' => $this->email_verified_at?->toIso8601String(),
            'requires_verification' => !$this->isEmailVerified(),
            'phone_verified' => $this->hasVerifiedPhone(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
        ];
    }

    // =========================================================================
    // LOGIN ATTEMPT METHODS
    // =========================================================================

    public function hasTooManyFailedAttempts(int $maxAttempts = 5): bool
    {
        return $this->recentFailedAttempts()->count() >= $maxAttempts;
    }

    public function logLoginAttempt(bool $success, ?string $ip = null): void
    {
        $this->loginAttempts()->create([
            'success' => $success,
            'attempted_at' => now(),
            'ip_address' => $ip,
        ]);

        if ($success) {
            $this->update([
                'last_login_at' => now(),
                'last_login_ip' => $ip,
                'login_count' => $this->login_count + 1,
            ]);
        }
    }

    // =========================================================================
    // WALLET METHODS
    // =========================================================================

    public function addToWallet(float $amount): bool
    {
        $this->wallet_balance += $amount;
        return $this->save();
    }

    public function deductFromWallet(float $amount): bool
    {
        if ((float) $this->wallet_balance < $amount) {
            return false;
        }

        $this->wallet_balance -= $amount;
        return $this->save();
    }

    // =========================================================================
    // STATISTICS METHODS
    // =========================================================================

    public function updateTotalSpent(float $amount): void
    {
        $this->increment('total_spent', $amount);
        $this->increment('total_rides');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeVerified(Builder $query)
    {
        return $query->where('is_verified', true)
            ->whereNotNull('email_verified_at');
    }

    public function scopeUnverified(Builder $query)
    {
        return $query->where('is_verified', false)
            ->orWhereNull('email_verified_at');
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopePhoneVerified(Builder $query)
    {
        return $query->whereNotNull('phone_verified_at');
    }

    public function scopeInZone(Builder $query, int $zoneId)
    {
        return $query->where('zone_id', $zoneId);
    }

    // =========================================================================
    // BOOT METHODS
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->referral_code)) {
                $user->referral_code = self::generateReferralCode();
            }
        });
    }

    private static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }
}
