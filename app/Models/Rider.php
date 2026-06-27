<?php

namespace App\Models;

use App\Enums\RiderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use App\Models\Zone;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Rider extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    // =========================================================================
    // TABLE CONFIGURATION
    // =========================================================================

    protected $table = 'riders';

    protected $fillable = [
        // Authentication
        'phone_number',
        'email',
        'password',
        'remember_token',

        // Personal Information
        'first_name',
        'last_name',
        'gender',
        'address',
        'nin',

        // Profile
        'profile_picture',
        'image_path',

        // Performance Metrics
        'rating',
        'total_trips',
        'total_deliveries',
        'completed_trips',
        'cancelled_trips',
        'acceptance_rate',

        // Availability & Location
        'is_online',
        'is_available',
        'is_busy',
        'zone_id',
        'current_latitude',
        'current_longitude',
        'location_updated_at',
        'last_status_update',

        // Vehicle Information
        'vehicle_type',
        'vehicle_color',
        'vehicle_plate_number',
        'vehicle_model',
        'seating_capacity',
        'driver_license_number',

        // Documents
        'driver_license_path',
        'proof_of_address_path',
        'license_verified_at',
        'background_check_passed',
        'phone_verified',

        // Financial
        'total_earned',
        'current_balance',
        'pending_payout',
        'total_withdrawn',

        // Guarantor Information
        'guarantor_name',
        'guarantor_phone',
        'guarantor_relationship',
        'guarantor_address',
        'guarantor_occupation',

        // Next of Kin
        'nok_name',
        'nok_phone',
        'nok_relationship',
        'nok_address',

        // Work History
        'previous_place_of_work',
        'years_of_work',

        // Approval System
        'verification_status',
        'rejection_reason',
        'approved_at',
        'approved_by',

        // Security & 2FA
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_auth',

        // Password Management
        'password_updated_at',
        'password_set_at',
        'password_reset_token',
        'password_reset_token_expires_at',
        'password_reset_attempts',

        // OTP & Email Verification
        'otp_code',
        'otp_expires_at',
        'otp_verified_at',
        'otp_attempts',
        'otp_last_attempt_at',
        'email_verification_code',
        'email_verification_sent_at',
        'email_verified',

        // Account Status
        'is_active',
        'is_deleted',
        'email_verified_at',

        // Device & Tracking
        'fcm_token',
        'device_id',
        'last_login_at',
        'last_login_ip',
        'login_count',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'otp_code',
        'password_reset_token',
        'email_verification_code',
    ];

    protected $casts = [
        // Dates
        'email_verified_at' => 'datetime',
        'password_updated_at' => 'datetime',
        'password_set_at' => 'datetime',
        'approved_at' => 'datetime',
        'last_login_at' => 'datetime',
        'location_updated_at' => 'datetime',
        'last_status_update' => 'datetime',
        'otp_expires_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'otp_last_attempt_at' => 'datetime',
        'email_verification_sent_at' => 'datetime',
        'password_reset_token_expires_at' => 'datetime',
        'license_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',

        // Decimals
        'rating' => 'decimal:2',
        'acceptance_rate' => 'decimal:2',
        'current_latitude' => 'decimal:7',
        'current_longitude' => 'decimal:7',
        'total_earned' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'pending_payout' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',

        // Integers
        'years_of_work' => 'integer',
        'total_trips' => 'integer',
        'total_deliveries' => 'integer',
        'completed_trips' => 'integer',
        'cancelled_trips' => 'integer',
        'seating_capacity' => 'integer',
        'password_reset_attempts' => 'integer',
        'otp_attempts' => 'integer',
        'login_count' => 'integer',

        // Booleans
        'is_online' => 'boolean',
        'is_available' => 'boolean',
        'is_busy' => 'boolean',
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'two_factor_auth' => 'boolean',
        'background_check_passed' => 'boolean',
        'phone_verified' => 'boolean',
        'email_verified' => 'boolean',

        // Enum
        'verification_status' => RiderStatus::class,
        'gender' => 'string',
        'vehicle_type' => 'string',
    ];

    protected $appends = [
        'full_name',
        'image_url',
        'proof_of_address_url',
        'driver_license_url',
        'can_set_password',
        'formatted_rating',
        'profile_completion_percentage',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'rider_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function loginAttempts()
    {
        return $this->hasMany(LoginAttempt::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopePending(Builder $query): Builder
    {
        return $query->where('verification_status', RiderStatus::PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('verification_status', RiderStatus::APPROVED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('verification_status', RiderStatus::REJECTED);
    }

    public function scopeSuspended(Builder $query): Builder
    {
        $status = defined(RiderStatus::class . '::SUSPENDED')
            ? constant(RiderStatus::class . '::SUSPENDED')
            : 'suspended';

        return $query->where('verification_status', $status);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeUnverified(Builder $query): Builder
    {
        return $query->whereNull('email_verified_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('is_online', true)->where('is_available', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true)
            ->where('is_online', true)
            ->where('is_busy', false)
            ->where('verification_status', RiderStatus::APPROVED);
    }

    public function scopeNearby(Builder $query, float $lat, float $lng, int $radiusKm = 5)
    {
        // Haversine formula for radius search
        return $query->whereRaw(
            "(6371 * acos(cos(radians(?)) * cos(radians(current_latitude)) * cos(radians(current_longitude) - radians(?)) + sin(radians(?)) * sin(radians(current_latitude)))) < ?",
            [$lat, $lng, $lat, $radiusKm]
        );
    }

    public function scopeWithMinRating(Builder $query, float $minRating = 4.0)
    {
        return $query->where('rating', '>=', $minRating);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path
            ? Storage::url($this->image_path)
            : ($this->profile_picture ? Storage::url($this->profile_picture) : null);
    }

    public function getProofOfAddressUrlAttribute(): ?string
    {
        return $this->proof_of_address_path
            ? Storage::url($this->proof_of_address_path)
            : null;
    }

    public function getDriverLicenseUrlAttribute(): ?string
    {
        return $this->driver_license_path
            ? Storage::url($this->driver_license_path)
            : null;
    }

    public function getCanSetPasswordAttribute(): bool
    {
        return $this->canSetPassword();
    }

    public function getFormattedRatingAttribute(): string
    {
        return number_format($this->rating, 1);
    }

    public function getProfileCompletionPercentageAttribute(): int
    {
        return $this->getProfileCompletionPercentage();
    }

    public function getRemainingResetAttemptsAttribute(): int
    {
        return $this->getRemainingResetAttempts();
    }

    public function getPasswordExpiryDaysAttribute(): int
    {
        return $this->getPasswordExpiryDays();
    }

    // =========================================================================
    // MUTATORS
    // =========================================================================

    public function setFirstNameAttribute($value): void
    {
        $this->attributes['first_name'] = ucfirst(strtolower($value));
    }

    public function setLastNameAttribute($value): void
    {
        $this->attributes['last_name'] = ucfirst(strtolower($value));
    }

    public function setPhoneNumberAttribute($value): void
    {
        $this->attributes['phone_number'] = preg_replace('/[^0-9]/', '', $value);
    }

    public function setNinAttribute($value): void
    {
        $this->attributes['nin'] = preg_replace('/[^0-9]/', '', $value);
    }

    // =========================================================================
    // ADMIN ACTIONS
    // =========================================================================

    public function approve(int $adminId): bool
    {
        $this->verification_status = RiderStatus::APPROVED;
        $this->approved_by = $adminId;
        $this->approved_at = now();

        return $this->save();
    }

    public function reject(int $adminId, string $reason): bool
    {
        $this->verification_status = RiderStatus::REJECTED;
        $this->approved_by = $adminId;
        $this->rejection_reason = $reason;

        return $this->save();
    }

    public function suspend($reason = null): bool
    {
        $status = defined(RiderStatus::class . '::SUSPENDED')
            ? constant(RiderStatus::class . '::SUSPENDED')
            : 'suspended';

        $this->verification_status = $status;
        $this->rejection_reason = $reason;
        $this->is_online = false;
        $this->is_available = false;

        return $this->save();
    }

    public function activate(): bool
    {
        $this->verification_status = RiderStatus::APPROVED;
        $this->rejection_reason = null;
        $this->is_active = true;

        return $this->save();
    }

    // =========================================================================
    // AUTHENTICATION METHODS
    // =========================================================================

    public function setPasswordAndActivate(string $password): bool
    {
        if ($this->verification_status !== RiderStatus::APPROVED || !$this->hasVerifiedEmail()) {
            return false;
        }

        $this->password = Hash::make($password);
        $this->password_set_at = now();
        $this->password_updated_at = now();

        return $this->save();
    }

    public function updatePassword(string $password): bool
    {
        $this->password = Hash::make($password);
        $this->password_updated_at = now();
        $this->password_reset_token = null;
        $this->password_reset_token_expires_at = null;

        return $this->save();
    }

    public function canLogin(): bool
    {
        return $this->verification_status === RiderStatus::APPROVED
            && $this->hasVerifiedEmail()
            && !is_null($this->password)
            && $this->is_active;
    }

    public function canSetPassword(): bool
    {
        return $this->verification_status === RiderStatus::APPROVED
            && $this->hasVerifiedEmail()
            && is_null($this->password);
    }

    public function updateLoginInfo(Request $request): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'login_count' => $this->login_count + 1,
        ]);
    }

    public function logLoginAttempt(bool $success, ?string $ip = null): void
    {
        $this->loginAttempts()->create([
            'success' => $success,
            'attempted_at' => now(),
            'ip_address' => $ip,
        ]);
    }

    // =========================================================================
    // TOKEN MANAGEMENT
    // =========================================================================

    public function revokeAllTokens(): void
    {
        $this->tokens()->delete();
    }

    public function revokeCurrentToken(Request $request): void
    {
        $request->user()->currentAccessToken()->delete();
    }

    // =========================================================================
    // PASSWORD RESET METHODS
    // =========================================================================

    public function generatePasswordResetToken(): string
    {
        $token = $this->generateAlphanumericToken(8);

        $this->password_reset_token = $token;
        $this->password_reset_token_expires_at = now()->addMinutes(15);
        $this->password_reset_attempts = 0;
        $this->save();

        return $token;
    }

    public function verifyPasswordResetToken(string $token): bool
    {
        if (!$this->password_reset_token) {
            return false;
        }

        if ($this->password_reset_token_expires_at && now()->gt($this->password_reset_token_expires_at)) {
            $this->clearPasswordResetToken();
            return false;
        }

        if ($this->password_reset_attempts >= 5) {
            $this->clearPasswordResetToken();
            return false;
        }

        if ($this->password_reset_token === $token) {
            return true;
        }

        $this->password_reset_attempts += 1;
        $this->save();

        return false;
    }

    public function clearPasswordResetToken(): void
    {
        $this->update([
            'password_reset_token' => null,
            'password_reset_token_expires_at' => null,
            'password_reset_attempts' => 0,
        ]);
    }

    public function getRemainingResetAttempts(): int
    {
        return max(0, 5 - ($this->password_reset_attempts ?? 0));
    }

    public function hasValidPasswordResetToken(): bool
    {
        return $this->password_reset_token !== null
            && $this->password_reset_token_expires_at !== null
            && now()->lte($this->password_reset_token_expires_at)
            && $this->password_reset_attempts < 5;
    }

    // =========================================================================
    // OTP & EMAIL VERIFICATION
    // =========================================================================

    public function generateOtp(): string
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->otp_code = Hash::make($otp);
        $this->otp_expires_at = now()->addMinutes(10);
        $this->otp_attempts = 0;
        $this->otp_last_attempt_at = null;
        $this->save();

        return $otp;
    }

    public function verifyOtp(string $otp): bool
    {
        if (!$this->otp_code || now()->gt($this->otp_expires_at)) {
            return false;
        }

        if ($this->otp_attempts >= 5) {
            return false;
        }

        $this->otp_attempts += 1;
        $this->otp_last_attempt_at = now();
        $this->save();

        if (Hash::check($otp, $this->otp_code)) {
            $this->otp_verified_at = now();
            $this->otp_code = null;
            $this->save();
            return true;
        }

        return false;
    }

    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'email_verified' => true,
        ])->save();
    }

    // =========================================================================
    // STATUS HELPERS
    // =========================================================================

    public function isPending(): bool
    {
        return $this->verification_status === RiderStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->verification_status === RiderStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->verification_status === RiderStatus::REJECTED;
    }

    public function isSuspended(): bool
    {
        return $this->verification_status === self::getSuspendedStatusValue();
    }

    public function isOnline(): bool
    {
        return $this->is_online && $this->is_available && !$this->is_busy;
    }

    public function goOnline(): void
    {
        $this->update([
            'is_online' => true,
            'is_available' => true,
            'last_status_update' => now(),
        ]);
    }

    public function goOffline(): void
    {
        $this->update([
            'is_online' => false,
            'is_available' => false,
            'last_status_update' => now(),
        ]);
    }

    public function setBusy(bool $busy = true): void
    {
        $this->update([
            'is_busy' => $busy,
            'last_status_update' => now(),
        ]);
    }

    // =========================================================================
    // LOCATION METHODS
    // =========================================================================

    public function updateLocation(float $latitude, float $longitude): void
    {
        $this->update([
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
            'location_updated_at' => now(),
        ]);
    }

    public function hasStaleLocation(int $maxMinutes = 5): bool
    {
        return !$this->location_updated_at ||
            $this->location_updated_at->diffInMinutes(now()) > $maxMinutes;
    }

    // =========================================================================
    // PERFORMANCE METHODS
    // =========================================================================

    public function updateRating(float $newRating): void
    {
        $totalRatings = $this->total_trips;
        $currentTotal = $this->rating * $totalRatings;
        $newTotal = $currentTotal + $newRating;

        $this->rating = $newTotal / ($totalRatings + 1);
        $this->save();
    }

    public function incrementTrips(): void
    {
        $this->increment('total_trips');
        $this->increment('completed_trips');
    }

    public function incrementCancelledTrips(): void
    {
        $this->increment('cancelled_trips');
        $this->updateAcceptanceRate();
    }

    public function updateAcceptanceRate(): void
    {
        $total = $this->completed_trips + $this->cancelled_trips;
        if ($total > 0) {
            $this->acceptance_rate = ($this->completed_trips / $total) * 100;
            $this->save();
        }
    }

    // =========================================================================
    // FINANCIAL METHODS
    // =========================================================================

    public function addEarnings(float $amount): void
    {
        $this->increment('total_earned', $amount);
        $this->increment('current_balance', $amount);
    }

    public function addToBalance(float $amount): void
    {
        $this->increment('current_balance', $amount);
    }

    public function deductFromBalance(float $amount): bool
    {
        if ($this->current_balance < $amount) {
            return false;
        }

        $this->decrement('current_balance', $amount);
        return true;
    }

    public function addPendingPayout(float $amount): void
    {
        $this->increment('pending_payout', $amount);
        $this->decrement('current_balance', $amount);
    }

    public function completePayout(float $amount): void
    {
        $this->decrement('pending_payout', $amount);
        $this->increment('total_withdrawn', $amount);
    }

    public function getFormattedCurrentBalance(): string
    {
        return '₦' . number_format($this->current_balance, 2);
    }

    public function getFormattedTotalEarned(): string
    {
        return '₦' . number_format($this->total_earned, 2);
    }

    // =========================================================================
    // PROFILE COMPLETION
    // =========================================================================

    public function hasCompletedProfile(): bool
    {
        return !empty($this->vehicle_type)
            && !empty($this->vehicle_plate_number)
            && !empty($this->address)
            && !empty($this->driver_license_number);
    }

    public function getProfileCompletionPercentage(): int
    {
        $requiredFields = [
            'first_name',
            'last_name',
            'phone_number',
            'email',
            'gender',
            'address',
            'nin',
            'vehicle_type',
            'vehicle_color',
            'vehicle_plate_number',
            'driver_license_number',
            'vehicle_model',
            'guarantor_name',
            'guarantor_phone',
            'guarantor_relationship',
            'nok_name',
            'nok_phone',
            'nok_relationship',
        ];

        $filledFields = 0;
        foreach ($requiredFields as $field) {
            if (!empty($this->$field)) {
                $filledFields++;
            }
        }

        return round(($filledFields / count($requiredFields)) * 100);
    }

    // =========================================================================
    // PASSWORD EXPIRY
    // =========================================================================

    public function isPasswordExpired(): bool
    {
        if (!$this->password_updated_at) {
            return true;
        }

        return $this->password_updated_at->diffInMonths(now()) >= 3;
    }

    public function getPasswordExpiryDays(): int
    {
        if (!$this->password_updated_at) {
            return 0;
        }

        $expiryDate = $this->password_updated_at->addMonths(3);
        $daysLeft = now()->diffInDays($expiryDate, false);

        return max(0, $daysLeft);
    }

    // =========================================================================
    // BOOT & HELPER METHODS
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($rider) {
            $rider->verification_status = $rider->verification_status ?? RiderStatus::PENDING;
        });
    }

    private function generateAlphanumericToken(int $length = 8): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $token = '';

        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $token;
    }
}
