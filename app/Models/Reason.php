<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reason Model
 * 
 * Manages predefined reasons for:
 * - Cancellations
 * - Reports
 * - Returns
 * - Complaints
 * - Refunds
 * - Disputes
 */
class Reason extends Model
{
    use HasFactory, SoftDeletes;

    // =========================================================================
    // TABLE CONFIGURATION
    // =========================================================================

    /**
     * The table associated with the model.
     */
    protected $table = 'reasons';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'reason',
        'description',
        'sort_order',
        'is_active',
        'requires_comment',
        'requires_evidence',
        'auto_action',
        'auto_action_days',
        'icon',
        'color',
        'parent_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Booleans
        'is_active' => 'boolean',
        'requires_comment' => 'boolean',
        'requires_evidence' => 'boolean',

        // Integers
        'sort_order' => 'integer',
        'auto_action_days' => 'integer',

        // Arrays
        'metadata' => 'array',

        // Dates
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'is_active' => true,
        'requires_comment' => false,
        'requires_evidence' => false,
        'sort_order' => 0,
        'auto_action' => 'none',
        'auto_action_days' => 0,
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'formatted_reason',
        'auto_action_label',
    ];

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    /**
     * Reason types.
     */
    public const TYPE_CANCELLATION = 'cancellation';
    public const TYPE_REPORT = 'report';
    public const TYPE_RETURN = 'return';
    public const TYPE_COMPLAINT = 'complaint';
    public const TYPE_REFUND = 'refund';
    public const TYPE_DISPUTE = 'dispute';

    /**
     * Auto action types.
     */
    public const ACTION_NONE = 'none';
    public const ACTION_WARNING = 'warning';
    public const ACTION_SUSPEND = 'suspend';
    public const ACTION_BAN = 'ban';
    public const ACTION_REFUND = 'refund';

    /**
     * All available reason types.
     */
    public static array $types = [
        self::TYPE_CANCELLATION,
        self::TYPE_REPORT,
        self::TYPE_RETURN,
        self::TYPE_COMPLAINT,
        self::TYPE_REFUND,
        self::TYPE_DISPUTE,
    ];

    /**
     * All available auto actions.
     */
    public static array $autoActions = [
        self::ACTION_NONE,
        self::ACTION_WARNING,
        self::ACTION_SUSPEND,
        self::ACTION_BAN,
        self::ACTION_REFUND,
    ];

    /**
     * Auto action labels for display.
     */
    public static array $autoActionLabels = [
        self::ACTION_NONE => 'No Action',
        self::ACTION_WARNING => 'Send Warning',
        self::ACTION_SUSPEND => 'Suspend Account',
        self::ACTION_BAN => 'Ban Account',
        self::ACTION_REFUND => 'Auto Refund',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the parent reason.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Reason::class, 'parent_id');
    }

    /**
     * Get the child reasons.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Reason::class, 'parent_id');
    }

    /**
     * Get all descendants (recursive).
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get reports using this reason.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(UserReport::class, 'reason_id');
    }

    /**
     * Get orders cancelled with this reason.
     */
    public function cancelledOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'cancellation_reason_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get only active reasons.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get inactive reasons.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to get reasons by type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get reasons by multiple types.
     */
    public function scopeOfTypes(Builder $query, array $types): Builder
    {
        return $query->whereIn('type', $types);
    }

    /**
     * Scope for ordered reasons.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('reason');
    }

    /**
     * Scope to get root-level reasons.
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get reasons requiring comment.
     */
    public function scopeRequiresComment(Builder $query): Builder
    {
        return $query->where('requires_comment', true);
    }

    /**
     * Scope to get reasons requiring evidence.
     */
    public function scopeRequiresEvidence(Builder $query): Builder
    {
        return $query->where('requires_evidence', true);
    }

    /**
     * Scope to get reasons with auto action.
     */
    public function scopeWithAutoAction(Builder $query): Builder
    {
        return $query->where('auto_action', '!=', self::ACTION_NONE);
    }

    /**
     * Scope to get cancellation reasons.
     */
    public function scopeCancellation(Builder $query): Builder
    {
        return $query->ofType(self::TYPE_CANCELLATION);
    }

    /**
     * Scope to get report reasons.
     */
    public function scopeReport(Builder $query): Builder
    {
        return $query->ofType(self::TYPE_REPORT);
    }

    /**
     * Scope to get return reasons.
     */
    public function scopeReturn(Builder $query): Builder
    {
        return $query->ofType(self::TYPE_RETURN);
    }

    /**
     * Scope to get complaint reasons.
     */
    public function scopeComplaint(Builder $query): Builder
    {
        return $query->ofType(self::TYPE_COMPLAINT);
    }

    // =========================================================================
    // ACCESSORS & MUTATORS
    // =========================================================================

    /**
     * Get formatted reason with type prefix.
     */
    public function getFormattedReasonAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->type)) . ': ' . $this->reason;
    }

    /**
     * Get auto action label for display.
     */
    public function getAutoActionLabelAttribute(): string
    {
        return self::$autoActionLabels[$this->auto_action] ?? ucfirst($this->auto_action);
    }

    /**
     * Get display name with icon.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->icon) {
            return $this->icon . ' ' . $this->reason;
        }

        return $this->reason;
    }

    /**
     * Check if this is a system reason (cannot be deleted).
     */
    public function getIsSystemAttribute(): bool
    {
        return in_array($this->type, [self::TYPE_CANCELLATION, self::TYPE_REPORT]);
    }

    /**
     * Get badge color for UI.
     */
    public function getBadgeColorAttribute(): string
    {
        if ($this->color) {
            return $this->color;
        }

        return match ($this->type) {
            self::TYPE_CANCELLATION => 'warning',
            self::TYPE_REPORT => 'danger',
            self::TYPE_RETURN => 'info',
            self::TYPE_COMPLAINT => 'dark',
            self::TYPE_REFUND => 'success',
            self::TYPE_DISPUTE => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Mutator for reason text.
     */
    public function setReasonAttribute(string $value): void
    {
        $this->attributes['reason'] = ucfirst(trim($value));
    }

    /**
     * Mutator for type (ensure lowercase).
     */
    public function setTypeAttribute(string $value): void
    {
        $this->attributes['type'] = strtolower(trim($value));
    }

    // =========================================================================
    // BUSINESS LOGIC METHODS
    // =========================================================================

    /**
     * Check if this reason requires additional comment.
     */
    public function requiresComment(): bool
    {
        return $this->requires_comment;
    }

    /**
     * Check if this reason requires evidence upload.
     */
    public function requiresEvidence(): bool
    {
        return $this->requires_evidence;
    }

    /**
     * Get auto action to take.
     */
    public function getAutoAction(): string
    {
        return $this->auto_action ?? self::ACTION_NONE;
    }

    /**
     * Get auto action days.
     */
    public function getAutoActionDays(): int
    {
        return $this->auto_action_days ?? 0;
    }

    /**
     * Check if reason has auto action.
     */
    public function hasAutoAction(): bool
    {
        return $this->getAutoAction() !== self::ACTION_NONE;
    }

    /**
     * Check if this reason has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->count() > 0;
    }

    // =========================================================================
    // CACHED QUERIES (Optimized for production)
    // =========================================================================

    /**
     * Get all active reasons for a specific type (cached).
     */
    public static function getActiveForType(string $type): \Illuminate\Support\Collection
    {
        return cache()->remember("reasons:type:{$type}", 3600, function () use ($type) {
            return self::active()
                ->ofType($type)
                ->ordered()
                ->get(['id', 'reason', 'description', 'requires_comment', 'requires_evidence', 'icon', 'color']);
        });
    }

    /**
     * Get all active reasons grouped by type (cached).
     */
    public static function getAllActiveGrouped(): \Illuminate\Support\Collection
    {
        return cache()->remember('reasons:all_grouped', 3600, function () {
            return self::active()
                ->ordered()
                ->get()
                ->groupBy('type');
        });
    }

    /**
     * Get reasons as array for select dropdowns.
     */
    public static function getSelectArray(string $type): array
    {
        return self::getActiveForType($type)
            ->pluck('reason', 'id')
            ->toArray();
    }

    /**
     * Get reasons with auto actions (for moderation).
     */
    public static function getWithAutoActions(): \Illuminate\Support\Collection
    {
        return cache()->remember('reasons:with_auto_actions', 3600, function () {
            return self::active()
                ->withAutoAction()
                ->ordered()
                ->get(['id', 'type', 'reason', 'auto_action', 'auto_action_days']);
        });
    }

    /**
     * Clear all reason caches.
     */
    public static function clearCache(): void
    {
        cache()->forget('reasons:all_grouped');
        cache()->forget('reasons:with_auto_actions');

        foreach (self::$types as $type) {
            cache()->forget("reasons:type:{$type}");
        }
    }

    // =========================================================================
    // VALIDATION METHODS
    // =========================================================================

    /**
     * Check if reason ID is valid for given type.
     */
    public static function isValidForType(int $reasonId, string $type): bool
    {
        return self::where('id', $reasonId)
            ->where('type', $type)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Validate reason with comment requirement.
     */
    public static function validateWithComment(int $reasonId, ?string $comment): bool
    {
        $reason = self::find($reasonId);

        if (!$reason || !$reason->is_active) {
            return false;
        }

        if ($reason->requires_comment && empty($comment)) {
            return false;
        }

        return true;
    }

    /**
     * Get the auto action for a reason.
     */
    public static function getAutoActionForReason(int $reasonId): ?array
    {
        $reason = self::find($reasonId);

        if (!$reason || !$reason->hasAutoAction()) {
            return null;
        }

        return [
            'action' => $reason->auto_action,
            'days' => $reason->auto_action_days,
            'reason' => $reason->reason,
        ];
    }

    // =========================================================================
    // BOOT METHOD
    // =========================================================================

    protected static function boot(): void
    {
        parent::boot();

        // Clear cache on save/delete
        static::saved(function (): void {
            self::clearCache();
        });

        static::deleted(function (): void {
            self::clearCache();
        });

        // Prevent deletion of system reasons
        static::deleting(function (Reason $reason): bool {
            if ($reason->is_system) {
                return false;
            }

            // Update children to remove parent
            $reason->children()->update(['parent_id' => null]);

            return true;
        });
    }
}
