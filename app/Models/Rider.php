<?php

namespace App\Models;

use App\Enums\RiderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Rider extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */
    protected $table = 'riders';

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */
    protected $fillable = [

        /*
        |--------------------------------------------------------------------------
        | Personal Information
        |--------------------------------------------------------------------------
        */
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'gender',
        'address',
        'nin',

        /*
        |--------------------------------------------------------------------------
        | Uploaded Files
        |--------------------------------------------------------------------------
        */
        'image_path',
        'proof_of_address_path',
        'driver_license_path',

        /*
        |--------------------------------------------------------------------------
        | Vehicle Information
        |--------------------------------------------------------------------------
        */
        'vehicle_type',
        'vehicle_color',
        'vehicle_plate_number',
        'vehicle_name',
        'driver_license_number',

        /*
        |--------------------------------------------------------------------------
        | Guarantor Information
        |--------------------------------------------------------------------------
        */
        'guarantor_name',
        'guarantor_phone',
        'guarantor_relationship',
        'guarantor_address',
        'guarantor_occupation',

        /*
        |--------------------------------------------------------------------------
        | Next of Kin
        |--------------------------------------------------------------------------
        */
        'nok_name',
        'nok_phone',
        'nok_relationship',
        'nok_address',

        /*
        |--------------------------------------------------------------------------
        | Work History
        |--------------------------------------------------------------------------
        */
        'previous_place_of_work',
        'years_of_work',

        /*
        |--------------------------------------------------------------------------
        | Verification
        |--------------------------------------------------------------------------
        */
        'email_verified_at',
        'otp_code',
        'otp_expires_at',
        'otp_verified_at',

        /*
        |--------------------------------------------------------------------------
        | Authentication & Security
        |--------------------------------------------------------------------------
        */
        'password',
        'password_set_at',
        'remember_token',

        /*
        |--------------------------------------------------------------------------
        | Password Reset
        |--------------------------------------------------------------------------
        */
        'password_reset_token',
        'password_reset_token_expires_at',
        'password_reset_attempts',

        /*
        |--------------------------------------------------------------------------
        | Login Tracking
        |--------------------------------------------------------------------------
        */
        'last_login_at',
        'last_login_ip',
        'fcm_token',

        /*
        |--------------------------------------------------------------------------
        | Admin & Status
        |--------------------------------------------------------------------------
        */
        'status',
        'rejection_reason',
        'approved_at',
        'approved_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | HIDDEN ATTRIBUTES
    |--------------------------------------------------------------------------
    */
    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
        'password_reset_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | ATTRIBUTE CASTS
    |--------------------------------------------------------------------------
    */
    protected $casts = [

        // Dates
        'email_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'password_set_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password_reset_token_expires_at' => 'datetime',

        // Numbers
        'years_of_work' => 'integer',
        'password_reset_attempts' => 'integer',

        // Enum
        'status' => RiderStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | APPENDED ATTRIBUTES
    |--------------------------------------------------------------------------
    */
    protected $appends = [
        'full_name',
        'image_url',
        'proof_of_address_url',
        'driver_license_url',
        'can_set_password',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopePending($query)
    {
        return $query->where('status', RiderStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', RiderStatus::APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', RiderStatus::REJECTED);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeUnverified($query)
    {
        return $query->whereNull('email_verified_at');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path
            ? Storage::url($this->image_path)
            : null;
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

    /*
    |--------------------------------------------------------------------------
    | ADMIN ACTIONS
    |--------------------------------------------------------------------------
    */
    public function approve(int $adminId): bool
    {
        $this->status = RiderStatus::APPROVED;
        $this->approved_by = $adminId;
        $this->approved_at = now();

        return $this->save();
    }

    public function reject(int $adminId, string $reason): bool
    {
        $this->status = RiderStatus::REJECTED;
        $this->approved_by = $adminId;
        $this->rejection_reason = $reason;

        return $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATION
    |--------------------------------------------------------------------------
    */
    public function setPasswordAndActivate(string $password): bool
    {
        if (
            $this->status !== RiderStatus::APPROVED ||
            !$this->hasVerifiedEmail()
        ) {
            return false;
        }

        $this->password = Hash::make($password);
        $this->password_set_at = now();

        return $this->save();
    }

    public function canLogin(): bool
    {
        return $this->status === RiderStatus::APPROVED
            && $this->hasVerifiedEmail()
            && !is_null($this->password);
    }

    public function canSetPassword(): bool
    {
        return $this->status === RiderStatus::APPROVED
            && $this->hasVerifiedEmail()
            && is_null($this->password);
    }

    public function updateLoginInfo(Request $request): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TOKEN MANAGEMENT
    |--------------------------------------------------------------------------
    */
    public function revokeAllTokens(): void
    {
        $this->tokens()->delete();
    }

    public function revokeCurrentToken(Request $request): void
    {
        $request->user()
            ->currentAccessToken()
            ->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | PASSWORD RESET
    |--------------------------------------------------------------------------
    */
    
    /**
     * Generate and store password reset token
     */
    public function generatePasswordResetToken(): string
    {
        // Generate 8-character alphanumeric token
        $token = $this->generateAlphanumericToken(8);
        
        $this->password_reset_token = $token;
        $this->password_reset_token_expires_at = now()->addMinutes(15);
        $this->password_reset_attempts = 0;
        $this->save();
        
        return $token;
    }

    /**
     * Verify password reset token
     */
    public function verifyPasswordResetToken(string $token): bool
    {
        // Check if token exists
        if (!$this->password_reset_token) {
            return false;
        }
        
        // Check if expired
        if ($this->password_reset_token_expires_at && now()->gt($this->password_reset_token_expires_at)) {
            $this->clearPasswordResetToken();
            return false;
        }
        
        // Check attempts (max 5)
        if ($this->password_reset_attempts >= 5) {
            $this->clearPasswordResetToken();
            return false;
        }
        
        // Verify token (case-sensitive)
        if ($this->password_reset_token === $token) {
            return true;
        }
        
        // Increment attempts
        $this->password_reset_attempts += 1;
        $this->save();
        
        return false;
    }

    /**
     * Clear password reset token
     */
    public function clearPasswordResetToken(): void
    {
        $this->update([
            'password_reset_token' => null,
            'password_reset_token_expires_at' => null,
            'password_reset_attempts' => 0,
        ]);
    }

    /**
     * Get remaining password reset attempts
     */
    public function getRemainingResetAttempts(): int
    {
        return max(0, 5 - ($this->password_reset_attempts ?? 0));
    }

    /**
     * Check if password reset token is valid
     */
    public function hasValidPasswordResetToken(): bool
    {
        return $this->password_reset_token !== null 
            && $this->password_reset_token_expires_at !== null
            && now()->lte($this->password_reset_token_expires_at)
            && $this->password_reset_attempts < 5;
    }

    /**
     * Generate alphanumeric token (letters and numbers)
     */
    private function generateAlphanumericToken(int $length = 8): string
    {
        // Excluding confusing characters: 0,1,O,I,o,l
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $token = '';
        
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $token;
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS HELPERS
    |--------------------------------------------------------------------------
    */
    public function isPending(): bool
    {
        return $this->status === RiderStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === RiderStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === RiderStatus::REJECTED;
    }

    /*
    |--------------------------------------------------------------------------
    | EMAIL VERIFICATION
    |--------------------------------------------------------------------------
    */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | PROFILE COMPLETION
    |--------------------------------------------------------------------------
    */
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
            'vehicle_name',

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

        return round(
            ($filledFields / count($requiredFields)) * 100
        );
    }
}