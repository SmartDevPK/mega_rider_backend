<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Personal Information
        'firstname',
        'lastname',
        'phoneNumber',
        'email',
        'referralCode',
        'address',
        'latitude',
        'longitude',
        'profile_picture',
        'notifications',
        
        // Driver Specific Fields
        'is_available',
        'rating',
        'total_trips',
        'profile_image',
        
        // Authentication
        'password',
        
        // Password Reset
        'password_reset_code',
        'password_reset_expires_at',
        
        // Email Verification
        'email_verification_code',
        'email_verification_sent_at',
        'email_verified_at',
        'is_verified',
        
        // Security Features
        'two_factor_enabled',
        'two_factor_secret',
        
        // Account Status
        'is_active',
        
        // Login Tracking
        'last_login_at',
        'last_login_ip',
        'login_count',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'email_verification_code',
        'password_reset_code', // Add this to hide reset code from responses
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Datetime casts
        'email_verified_at' => 'datetime',
        'email_verification_sent_at' => 'datetime',
        'password_reset_expires_at' => 'datetime', // Add this cast
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        
        // Boolean casts
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'is_available' => 'boolean', // Add this for driver availability
        
        // Other casts
        'password' => 'hashed',
        'login_count' => 'integer',
        'total_trips' => 'integer',
        'rating' => 'decimal:1', // Cast rating as decimal with 1 decimal place
        'notifications' => 'array', // Cast JSON to array
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'is_verified' => false,
        'is_active' => true,
        'two_factor_enabled' => false,
        'login_count' => 0,
        'is_available' => true,
        'total_trips' => 0,
        'rating' => null,
    ];

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }

    /**
     * Get the user's initials.
     *
     * @return string
     */
    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->firstname, 0, 1) . substr($this->lastname, 0, 1));
    }

    /**
     * Check if user has verified their email.
     *
     * @return bool
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at) && $this->is_verified;
    }

    /**
     * Mark email as verified.
     *
     * @return bool
     */
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'is_verified' => true,
            'email_verification_code' => null,
        ])->save();
    }

    /**
     * Check if password reset code is valid.
     *
     * @param string $code
     * @return bool
     */
    public function hasValidResetCode(string $code): bool
    {
        return $this->password_reset_code === $code 
            && $this->password_reset_expires_at 
            && $this->password_reset_expires_at->isFuture();
    }

    /**
     * Clear password reset code.
     *
     * @return bool
     */
    public function clearResetCode(): bool
    {
        return $this->forceFill([
            'password_reset_code' => null,
            'password_reset_expires_at' => null,
        ])->save();
    }

    /**
     * Check if user is a driver (has driver-specific fields).
     *
     * @return bool
     */
    public function isDriver(): bool
    {
        return !is_null($this->profile_image) || $this->total_trips > 0;
    }

    /**
     * Update driver rating based on new rating.
     *
     * @param float $newRating
     * @return void
     */
    public function updateRating(float $newRating): void
    {
        // Simple average calculation
        // You might want to implement a more sophisticated algorithm
        $totalRatings = $this->total_trips;
        $currentTotal = $this->rating * $totalRatings;
        $newTotal = $currentTotal + $newRating;
        $this->rating = $newTotal / ($totalRatings + 1);
        $this->save();
    }

    /**
     * Increment total trips count.
     *
     * @return void
     */
    public function incrementTrips(): void
    {
        $this->increment('total_trips');
    }

    /**
     * Get the login attempts for the user.
     */
    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }

    /**
     * Get recent failed login attempts.
     */
    public function recentFailedAttempts(): HasMany
    {
        return $this->loginAttempts()
            ->where('success', false)
            ->where('attempted_at', '>=', now()->subHours(24))
            ->latest('attempted_at');
    }

    /**
     * Check if user has too many failed login attempts.
     *
     * @param int $maxAttempts
     * @return bool
     */
    public function hasTooManyFailedAttempts(int $maxAttempts = 5): bool
    {
        return $this->recentFailedAttempts()->count() >= $maxAttempts;
    }

    /**
     * Log a login attempt.
     *
     * @param bool $success
     * @param string|null $ip
     * @return void
     */
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

    /**
     * Scope a query to only include verified users.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true)
                     ->whereNotNull('email_verified_at');
    }

    /**
     * Scope a query to only include unverified users.
     */
    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false)
                     ->orWhereNull('email_verified_at');
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive users.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to only include users with 2FA enabled.
     */
    public function scopeWithTwoFactor($query)
    {
        return $query->where('two_factor_enabled', true);
    }

    /**
     * Scope a query to only include available drivers.
     */
    public function scopeAvailableDrivers($query)
    {
        return $query->where('is_available', true)
                     ->whereNotNull('profile_image')
                     ->where('is_active', true);
    }

    /**
     * Scope a query to only include drivers with rating above threshold.
     */
    public function scopeWithMinRating($query, float $minRating = 4.0)
    {
        return $query->where('rating', '>=', $minRating);
    }

    /**
     * Get the orders for the user (if they are a rider).
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    /**
     * Get the trips for the user (if they are a driver).
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Order::class, 'driver_id');
    }

    /**
     * Get active orders for the user.
     */
    public function activeOrders()
    {
        return $this->orders()->whereIn('status', ['pending', 'assigned', 'in_progress']);
    }

    /**
     * Get completed trips for the driver.
     */
    public function completedTrips()
    {
        return $this->trips()->where('status', 'completed');
    }

    /**
     * Check if user has an active order.
     */
    public function hasActiveOrder(): bool
    {
        return $this->activeOrders()->exists();
    }

    /**
     * Check if user is currently on a trip as driver.
     */
    public function isOnTrip(): bool
    {
        return $this->trips()->whereIn('status', ['assigned', 'in_progress'])->exists();
    }
}