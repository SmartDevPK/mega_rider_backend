<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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
        
        // Authentication
        'password',
        
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
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        
        // Boolean casts
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
        
        // Other casts
        'password' => 'hashed',
        'login_count' => 'integer',
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
    ];

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->firstname} {$this->lastname}";
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
     * Get the login attempts for the user.
     */
    public function loginAttempts()
    {
        return $this->hasMany(LoginAttempt::class);
    }

    /**
     * Get recent failed login attempts.
     */
    public function recentFailedAttempts()
    {
        return $this->loginAttempts()
            ->where('success', false)
            ->where('attempted_at', '>=', now()->subHours(24))
            ->latest('attempted_at');
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
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include users with 2FA enabled.
     */
    public function scopeWithTwoFactor($query)
    {
        return $query->where('two_factor_enabled', true);
    }
}