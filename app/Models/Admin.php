<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // =========================================================================
    // TABLE CONFIGURATION
    // =========================================================================

    protected $table = 'admins';

    protected $fillable = [
        // Authentication
        'name',
        'email',
        'password',
        'remember_token',

        // Profile
        'profile_picture',
        'phone_number',

        // Role & Permissions
        'role',
        'is_super_admin',
        'permissions',

        // Dashboard Preferences
        'dashboard_preferences',
        'language',
        'timezone',

        // Account Status
        'is_active',
        'is_deleted',
        'deleted_at',
        'deletion_reason',
        'deleted_by',

        // Security
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'password_updated_at',

        // Tracking
        'last_login_at',
        'last_login_ip',
        'login_count',
        'last_action_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        // Dates
        'password_updated_at' => 'datetime',
        'last_login_at' => 'datetime',
        'last_action_at' => 'datetime',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        // Booleans
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'is_super_admin' => 'boolean',
        'two_factor_enabled' => 'boolean',

        // Integers
        'login_count' => 'integer',
        'deleted_by' => 'integer',

        // Arrays/JSON
        'permissions' => 'array',
        'dashboard_preferences' => 'array',
        'two_factor_recovery_codes' => 'array',
    ];

    protected $attributes = [
        'role' => 'admin',
        'language' => 'en',
        'timezone' => 'UTC',
        'is_active' => true,
        'is_deleted' => false,
        'two_factor_enabled' => false,
        'is_super_admin' => false,
        'login_count' => 0,
    ];

    protected $appends = [
        'is_password_expired',
        'password_expiry_days',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the admin who deleted this account
     */
    public function deletedBy()
    {
        return $this->belongsTo(Admin::class, 'deleted_by');
    }

    /**
     * Get admins deleted by this admin
     */
    public function deletedAdmins()
    {
        return $this->hasMany(Admin::class, 'deleted_by');
    }

    /**
     * Get login attempts for this admin
     */
    public function loginAttempts()
    {
        return $this->hasMany(LoginAttempt::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get only active (non-deleted) admins
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false)->where('is_active', true);
    }

    /**
     * Scope to get only deleted admins
     */
    public function scopeDeleted($query)
    {
        return $query->where('is_deleted', true);
    }

    /**
     * Scope to get only super admins
     */
    public function scopeSuperAdmins($query)
    {
        return $query->where('is_super_admin', true);
    }

    /**
     * Scope to filter by role
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to get only admins with specific permission
     */
    public function scopeWithPermission($query, string $permission)
    {
        return $query->whereJsonContains('permissions', $permission);
    }

    /**
     * Scope to get recently active admins
     */
    public function scopeRecentlyActive($query, int $days = 7)
    {
        return $query->where('last_login_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get inactive admins
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get the password for authentication
     */
    public function getAuthPassword()
    {
        return $this->password;
    }

    /**
     * Check if password is expired
     */
    public function getIsPasswordExpiredAttribute(): bool
    {
        return $this->isPasswordExpired();
    }

    /**
     * Get days until password expires
     */
    public function getPasswordExpiryDaysAttribute(): int
    {
        return $this->getPasswordExpiryDays();
    }

    /**
     * Get formatted role name
     */
    public function getFormattedRoleAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->role));
    }

    /**
     * Get admin's full display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    // =========================================================================
    // MUTATORS
    // =========================================================================

    /**
     * Set the admin's email (lowercase)
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    /**
     * Set the admin's name (proper case)
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = ucwords(trim($value));
    }

    /**
     * Set the admin's phone number (format)
     */
    public function setPhoneNumberAttribute($value): void
    {
        $this->attributes['phone_number'] = $value ? preg_replace('/[^0-9+]/', '', $value) : null;
    }

    // =========================================================================
    // PASSWORD METHODS
    // =========================================================================

    /**
     * Check if password needs to be updated (older than 3 months)
     */
    public function isPasswordExpired(): bool
    {
        if (!$this->password_updated_at) {
            return true;
        }

        return $this->password_updated_at->diffInMonths(now()) >= 3;
    }

    /**
     * Get days until password expires
     */
    public function getPasswordExpiryDays(): int
    {
        if (!$this->password_updated_at) {
            return 0;
        }

        $expiryDate = $this->password_updated_at->addMonths(3);
        $daysLeft = now()->diffInDays($expiryDate, false);

        return max(0, $daysLeft);
    }

    /**
     * Update password and reset tracking
     */
    public function updatePassword(string $password): bool
    {
        $this->password = bcrypt($password);
        $this->password_updated_at = now();

        return $this->save();
    }

    /**
     * Check if password needs to be changed on next login
     */
    public function requiresPasswordChange(): bool
    {
        return $this->isPasswordExpired();
    }

    // =========================================================================
    // ACCOUNT STATUS METHODS
    // =========================================================================

    /**
     * Check if admin account is deleted
     */
    public function isDeleted(): bool
    {
        return (bool) $this->is_deleted;
    }

    /**
     * Check if admin is super admin
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin || $this->role === 'super_admin';
    }

    /**
     * Check if admin account is active
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active && !$this->is_deleted;
    }

    /**
     * Check if two-factor authentication is enabled
     */
    public function isTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled;
    }

    /**
     * Activate admin account
     */
    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    /**
     * Deactivate admin account
     */
    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    /**
     * Soft delete the admin account
     */
    public function softDelete(?string $reason = null, ?int $deletedByAdminId = null): bool
    {
        $this->is_deleted = true;
        $this->deleted_at = now();

        if ($reason) {
            $this->deletion_reason = $reason;
        }

        if ($deletedByAdminId) {
            $this->deleted_by = $deletedByAdminId;
        }

        return $this->save();
    }

    /**
     * Restore a soft-deleted admin account
     */
    public function restoreAccount(): bool
    {
        $this->is_deleted = false;
        $this->deleted_at = null;
        $this->deleted_by = null;
        $this->deletion_reason = null;

        return $this->save();
    }

    // =========================================================================
    // TWO-FACTOR AUTHENTICATION METHODS
    // =========================================================================

    /**
     * Enable two-factor authentication
     */
    public function enableTwoFactorAuth(string $secret, string $recoveryCodes): void
    {
        $this->two_factor_enabled = true;
        $this->two_factor_secret = $secret;
        $this->two_factor_recovery_codes = $recoveryCodes;
        $this->save();
    }

    /**
     * Disable two-factor authentication
     */
    public function disableTwoFactorAuth(): void
    {
        $this->two_factor_enabled = false;
        $this->two_factor_secret = null;
        $this->two_factor_recovery_codes = null;
        $this->save();
    }

    // =========================================================================
    // LOGIN & TRACKING METHODS
    // =========================================================================

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(?string $ip = null): void
    {
        $this->last_login_at = now();

        if ($ip) {
            $this->last_login_ip = $ip;
        }

        $this->login_count = $this->login_count + 1;
        $this->save();
    }

    /**
     * Update last action timestamp
     */
    public function updateLastAction(): void
    {
        $this->last_action_at = now();
        $this->save();
    }

    /**
     * Log a login attempt
     */
    public function logLoginAttempt(bool $success, ?string $ip = null): void
    {
        $this->loginAttempts()->create([
            'success' => $success,
            'attempted_at' => now(),
            'ip_address' => $ip,
        ]);
    }

    /**
     * Get recent failed login attempts
     */
    public function getRecentFailedAttempts(int $hours = 24)
    {
        return $this->loginAttempts()
            ->where('success', false)
            ->where('attempted_at', '>=', now()->subHours($hours))
            ->count();
    }

    /**
     * Check if account should be locked due to too many failures
     */
    public function isLocked(int $maxAttempts = 5, int $hours = 24): bool
    {
        return $this->getRecentFailedAttempts($hours) >= $maxAttempts;
    }

    // =========================================================================
    // PERMISSION METHODS
    // =========================================================================

    /**
     * Check if admin has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        // Super admins have all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = $this->permissions ?? [];

        return in_array($permission, $permissions);
    }

    /**
     * Check if admin has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if admin has all of the given permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assign permissions to admin
     */
    public function assignPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
        $this->save();
    }

    /**
     * Add a single permission
     */
    public function addPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];

        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    /**
     * Remove a single permission
     */
    public function removePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];

        $this->permissions = array_values(array_filter($permissions, function ($p) use ($permission) {
            return $p !== $permission;
        }));

        $this->save();
    }

    // =========================================================================
    // ROLE METHODS
    // =========================================================================

    /**
     * Check if admin has a specific role
     */
    public function hasRole(string $role): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->role === $role;
    }

    /**
     * Check if admin has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update admin role
     */
    public function updateRole(string $role): void
    {
        $this->role = $role;

        // If role is super_admin, ensure is_super_admin flag is set
        if ($role === 'super_admin') {
            $this->is_super_admin = true;
        }

        $this->save();
    }

    // =========================================================================
    // DASHBOARD PREFERENCES
    // =========================================================================

    /**
     * Get dashboard preference value
     */
    public function getDashboardPreference(string $key, $default = null)
    {
        $preferences = $this->dashboard_preferences ?? [];

        return $preferences[$key] ?? $default;
    }

    /**
     * Set dashboard preference
     */
    public function setDashboardPreference(string $key, $value): void
    {
        $preferences = $this->dashboard_preferences ?? [];
        $preferences[$key] = $value;
        $this->dashboard_preferences = $preferences;
        $this->save();
    }

    /**
     * Update multiple dashboard preferences
     */
    public function updateDashboardPreferences(array $preferences): void
    {
        $current = $this->dashboard_preferences ?? [];
        $this->dashboard_preferences = array_merge($current, $preferences);
        $this->save();
    }

    // =========================================================================
    // BOOT METHOD
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($admin) {
            // Ensure super_admin flag matches role
            if ($admin->role === 'super_admin') {
                $admin->is_super_admin = true;
            }
        });

        static::updating(function ($admin) {
            // Ensure super_admin flag matches role
            if ($admin->role === 'super_admin') {
                $admin->is_super_admin = true;
            } elseif ($admin->role !== 'super_admin' && !$admin->isDirty('is_super_admin')) {
                // Don't auto-remove super_admin flag if it was explicitly set
                // But if role changes from super_admin, we might want to reconsider
            }
        });
    }
}
