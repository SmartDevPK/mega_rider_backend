<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\OrderType;
use App\Models\Rider;
use App\Models\RiderReview;
use App\Models\Customer;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Order Model
 * 
 * Manages all delivery orders in the system.
 * Optimized for high-volume order processing.
 */
class Order extends Model
{
    use HasFactory;

    // =========================================================================
    // TABLE CONFIGURATION
    // =========================================================================

    /**
     * The table associated with the model.
     */
    protected $table = 'orders';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Order Identification
        'order_id',
        'step',
        'meta',

        // Relationships
        'customer_id',
        'rider_id',
        'zone_id',
        'order_type_id',

        // Pickup Information
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'pickup_city',

        // Dropoff Information
        'dropoff_address',
        'dropoff_latitude',
        'dropoff_longitude',
        'dropoff_city',

        // Sender Information
        'sender_name',
        'sender_phone',
        'sender_email',

        // Receiver Information
        'receiver_name',
        'receiver_phone',
        'receiver_email',

        // Package Information
        'package_name',
        'package_worth',
        'package_image',
        'item_name',

        // Pricing
        'price',
        'delivery_fee',
        'insurance_fee',
        'insurance_flag',
        'discount_amount',
        'surge_multiplier',
        'surge_fee',
        'total_amount',
        'rider_earnings',
        'platform_fee',

        // Order Details
        'distance',
        'estimated_travel_time',

        // Status & Tracking
        'status',
        'is_draft',
        'special_instructions',

        // Timestamps
        'date_accepted',
        'date_delivered',
        'date_modified',

        // Cancellation
        'cancelled_at',
        'cancellation_reason',

        // Ratings
        'rider_rating',
        'customer_rating',
        'rider_feedback',
        'customer_feedback',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // Add sensitive fields if needed
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Location coordinates
        'pickup_latitude' => 'decimal:7',
        'pickup_longitude' => 'decimal:7',
        'dropoff_latitude' => 'decimal:7',
        'dropoff_longitude' => 'decimal:7',

        // Monetary values
        'package_worth' => 'decimal:2',
        'price' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'insurance_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'surge_multiplier' => 'decimal:2',
        'surge_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'rider_earnings' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'distance' => 'decimal:2',

        // Boolean flags
        'insurance_flag' => 'boolean',
        'is_draft' => 'boolean',

        // Integers
        'estimated_travel_time' => 'integer',

        // Dates
        'cancelled_at' => 'datetime',
        'date_accepted' => 'datetime',
        'date_delivered' => 'datetime',
        'date_modified' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        // Ratings
        'rider_rating' => 'decimal:1',
        'customer_rating' => 'decimal:1',

        // Other
        'status' => 'string',
        'step' => 'string',
        'meta' => 'array',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'pending',
        'is_draft' => false,
        'insurance_flag' => false,
        'insurance_fee' => 0,
        'discount_amount' => 0,
        'surge_multiplier' => 1.00,
        'surge_fee' => 0,
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'formatted_price',
        'formatted_total_amount',
        'formatted_rider_earnings',
        'status_badge',
        'is_cancellable',
        'delivery_duration_minutes',
    ];

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    /**
     * Order status constants.
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_PICKED_UP = 'picked_up';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Order step constants (for multi-step orders).
     */
    public const STEP_PICKUP = 'pickup';
    public const STEP_DROPOFF = 'dropoff';
    public const STEP_ITEM = 'item';
    public const STEP_REVIEW = 'review';

    /**
     * All available statuses.
     */
    public static array $statuses = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_ASSIGNED,
        self::STATUS_ACCEPTED,
        self::STATUS_PICKED_UP,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Active statuses (not terminal).
     */
    public static array $activeStatuses = [
        self::STATUS_PENDING,
        self::STATUS_ASSIGNED,
        self::STATUS_ACCEPTED,
        self::STATUS_PICKED_UP,
    ];

    /**
     * Terminal statuses (cannot be changed).
     */
    public static array $terminalStatuses = [
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    // =========================================================================
    // BOOT METHOD
    // =========================================================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Order $order): void {
            if (empty($order->order_id)) {
                $order->order_id = (string) Str::uuid();
            }
        });

        static::updating(function (Order $order): void {
            $order->date_modified = now();
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the customer who placed the order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the rider assigned to this order.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }

    /**
     * Get the zone for this order.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    /**
     * Get the order type for this order.
     */
    public function orderType(): BelongsTo
    {
        return $this->belongsTo(OrderType::class, 'order_type_id');
    }

    /**
     * Get the review for this order.
     */
    public function review(): HasOne
    {
        return $this->hasOne(RiderReview::class, 'order_id', 'order_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get only cancellable orders.
     */
    public function scopeCancellable(Builder $query): Builder
    {
        return $query->whereIn('status', self::$activeStatuses);
    }

    /**
     * Scope to get only pending orders.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get only assigned orders.
     */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ASSIGNED);
    }

    /**
     * Scope to get only accepted orders.
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * Scope to get only picked up orders.
     */
    public function scopePickedUp(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PICKED_UP);
    }

    /**
     * Scope to get only delivered orders.
     */
    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Scope to get only cancelled orders.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope to get active orders (not terminal).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::$activeStatuses);
    }

    /**
     * Scope to get non-draft orders.
     */
    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_draft', false);
    }

    /**
     * Scope to get orders by rider.
     */
    public function scopeForRider(Builder $query, int $riderId): Builder
    {
        return $query->where('rider_id', $riderId);
    }

    /**
     * Scope to get orders by customer.
     */
    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to get orders by status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get orders by multiple statuses.
     */
    public function scopeWithStatuses(Builder $query, array $statuses): Builder
    {
        return $query->whereIn('status', $statuses);
    }

    /**
     * Scope to get orders for today.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope to get orders for this week.
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /**
     * Scope to get orders for this month.
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    /**
     * Scope to get orders by date range.
     */
    public function scopeDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get orders by zone.
     */
    public function scopeInZone(Builder $query, int $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    /**
     * Scope to get orders with rating.
     */
    public function scopeRated(Builder $query): Builder
    {
        return $query->whereNotNull('rider_rating');
    }

    /**
     * Scope to get orders without rating.
     */
    public function scopeUnrated(Builder $query): Builder
    {
        return $query->whereNull('rider_rating');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return '₦' . number_format($this->price ?? 0, 2);
    }

    /**
     * Get formatted total amount.
     */
    public function getFormattedTotalAmountAttribute(): string
    {
        return '₦' . number_format($this->total_amount ?? 0, 2);
    }

    /**
     * Get formatted rider earnings.
     */
    public function getFormattedRiderEarningsAttribute(): string
    {
        return '₦' . number_format($this->rider_earnings ?? 0, 2);
    }

    /**
     * Get status badge information.
     */
    public function getStatusBadgeAttribute(): array
    {
        $badges = [
            self::STATUS_DRAFT => ['class' => 'secondary', 'text' => 'Draft', 'icon' => '📝'],
            self::STATUS_PENDING => ['class' => 'warning', 'text' => 'Pending', 'icon' => '⏳'],
            self::STATUS_ASSIGNED => ['class' => 'info', 'text' => 'Assigned', 'icon' => '👤'],
            self::STATUS_ACCEPTED => ['class' => 'primary', 'text' => 'Accepted', 'icon' => '✅'],
            self::STATUS_PICKED_UP => ['class' => 'info', 'text' => 'Picked Up', 'icon' => '📦'],
            self::STATUS_DELIVERED => ['class' => 'success', 'text' => 'Delivered', 'icon' => '🎉'],
            self::STATUS_CANCELLED => ['class' => 'danger', 'text' => 'Cancelled', 'icon' => '❌'],
        ];

        return $badges[$this->status] ?? ['class' => 'secondary', 'text' => ucfirst($this->status), 'icon' => '❓'];
    }

    /**
     * Check if order is cancellable.
     */
    public function getIsCancellableAttribute(): bool
    {
        return $this->isCancellable();
    }

    /**
     * Get delivery duration in minutes.
     */
    public function getDeliveryDurationMinutesAttribute(): ?int
    {
        return $this->getDeliveryDuration();
    }

    /**
     * Get package image URL.
     */
    public function getPackageImageUrlAttribute(): ?string
    {
        if ($this->package_image) {
            return url('/storage/packages/' . $this->package_image);
        }

        return null;
    }

    /**
     * Get order summary.
     */
    public function getSummaryAttribute(): array
    {
        return [
            'order_id' => $this->order_id,
            'status' => $this->status,
            'status_text' => $this->status_badge['text'],
            'package_name' => $this->package_name,
            'price' => $this->price,
            'total_amount' => $this->total_amount,
            'pickup_address' => $this->pickup_address,
            'dropoff_address' => $this->dropoff_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    // =========================================================================
    // STATUS CHECKERS
    // =========================================================================

    /**
     * Check if the order is cancellable.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, self::$activeStatuses);
    }

    /**
     * Check if the order is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the order is assigned.
     */
    public function isAssigned(): bool
    {
        return $this->status === self::STATUS_ASSIGNED;
    }

    /**
     * Check if the order is accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Check if the order is picked up.
     */
    public function isPickedUp(): bool
    {
        return $this->status === self::STATUS_PICKED_UP;
    }

    /**
     * Check if the order is delivered.
     */
    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Check if the order is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if the order is a draft.
     */
    public function isDraft(): bool
    {
        return (bool) $this->is_draft;
    }

    /**
     * Check if order is active (not terminal).
     */
    public function isActive(): bool
    {
        return in_array($this->status, self::$activeStatuses);
    }

    /**
     * Check if order is completed (terminal status).
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, self::$terminalStatuses);
    }

    /**
     * Check if the order has expired (24 hours after creation for pending).
     */
    public function isExpired(): bool
    {
        if (!$this->created_at || $this->status !== self::STATUS_PENDING) {
            return false;
        }

        return $this->created_at->addHours(24)->isPast();
    }

    /**
     * Check if order can be rated.
     */
    public function isRateable(): bool
    {
        return $this->isDelivered() && is_null($this->rider_rating);
    }

    // =========================================================================
    // ACTION METHODS
    // =========================================================================

    /**
     * Cancel the order with a reason.
     */
    public function cancel(?string $reason = null, ?int $cancelledBy = null): bool
    {
        if (!$this->isCancellable()) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->cancellation_reason = $reason;

        return $this->save();
    }

    /**
     * Mark order as assigned to rider.
     */
    public function assign(int $riderId): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->status = self::STATUS_ASSIGNED;
        $this->rider_id = $riderId;
        $this->date_modified = now();

        return $this->save();
    }

    /**
     * Mark order as accepted by rider.
     */
    public function accept(): bool
    {
        if (!$this->isAssigned()) {
            return false;
        }

        $this->status = self::STATUS_ACCEPTED;
        $this->date_accepted = now();
        $this->date_modified = now();

        return $this->save();
    }

    /**
     * Mark order as picked up.
     */
    public function pickUp(): bool
    {
        if (!$this->isAccepted()) {
            return false;
        }

        $this->status = self::STATUS_PICKED_UP;
        $this->date_modified = now();

        return $this->save();
    }

    /**
     * Mark order as delivered.
     */
    public function deliver(): bool
    {
        if (!$this->isPickedUp()) {
            return false;
        }

        $this->status = self::STATUS_DELIVERED;
        $this->date_delivered = now();
        $this->date_modified = now();

        return $this->save();
    }

    /**
     * Add rating for rider.
     */
    public function addRiderRating(float $rating, ?string $feedback = null): bool
    {
        if (!$this->isRateable()) {
            return false;
        }

        $this->rider_rating = $rating;
        $this->rider_feedback = $feedback;

        // Update rider's average rating
        if ($this->rider) {
            $this->rider->updateRating($rating);
            $this->rider->incrementTrips();
        }

        return $this->save();
    }

    /**
     * Calculate total amount based on pricing components.
     */
    public function calculateTotalAmount(): float
    {
        $total = ($this->delivery_fee ?? 0) + ($this->insurance_fee ?? 0);
        $total = $total * ($this->surge_multiplier ?? 1);
        $total = $total - ($this->discount_amount ?? 0);

        return round(max(0, $total), 2);
    }

    /**
     * Recalculate and update total amount.
     */
    public function recalculateTotal(): bool
    {
        $this->total_amount = $this->calculateTotalAmount();

        return $this->save();
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Calculate delivery duration in minutes.
     */
    public function getDeliveryDuration(): ?int
    {
        if (!$this->date_accepted || !$this->date_delivered) {
            return null;
        }

        return (int) $this->date_accepted->diffInMinutes($this->date_delivered);
    }

    /**
     * Get the next possible status.
     */
    public function getNextStatus(): ?string
    {
        $flow = [
            self::STATUS_PENDING => self::STATUS_ASSIGNED,
            self::STATUS_ASSIGNED => self::STATUS_ACCEPTED,
            self::STATUS_ACCEPTED => self::STATUS_PICKED_UP,
            self::STATUS_PICKED_UP => self::STATUS_DELIVERED,
        ];

        return $flow[$this->status] ?? null;
    }

    /**
     * Check if status transition is valid.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $allowedTransitions = [
            self::STATUS_DRAFT => [self::STATUS_PENDING, self::STATUS_CANCELLED],
            self::STATUS_PENDING => [self::STATUS_ASSIGNED, self::STATUS_CANCELLED],
            self::STATUS_ASSIGNED => [self::STATUS_ACCEPTED, self::STATUS_CANCELLED],
            self::STATUS_ACCEPTED => [self::STATUS_PICKED_UP, self::STATUS_CANCELLED],
            self::STATUS_PICKED_UP => [self::STATUS_DELIVERED, self::STATUS_CANCELLED],
            self::STATUS_DELIVERED => [],
            self::STATUS_CANCELLED => [],
        ];

        return in_array($newStatus, $allowedTransitions[$this->status] ?? []);
    }

    /**
     * Get order for API response.
     */
    public function toApiResponse(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'status_text' => $this->status_badge['text'],
            'customer' => $this->customer?->only(['id', 'first_name', 'last_name', 'phone_number']),
            'rider' => $this->rider?->only(['id', 'first_name', 'last_name', 'phone_number', 'rating']),
            'pickup' => [
                'address' => $this->pickup_address,
                'latitude' => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
                'city' => $this->pickup_city,
            ],
            'dropoff' => [
                'address' => $this->dropoff_address,
                'latitude' => $this->dropoff_latitude,
                'longitude' => $this->dropoff_longitude,
                'city' => $this->dropoff_city,
            ],
            'package' => [
                'name' => $this->package_name,
                'worth' => $this->package_worth,
                'image' => $this->package_image_url,
            ],
            'pricing' => [
                'delivery_fee' => $this->delivery_fee,
                'insurance_fee' => $this->insurance_fee,
                'discount' => $this->discount_amount,
                'surge_multiplier' => $this->surge_multiplier,
                'total' => $this->total_amount,
                'formatted_total' => $this->formatted_total_amount,
            ],
            'timeline' => [
                'created_at' => $this->created_at?->toIso8601String(),
                'accepted_at' => $this->date_accepted?->toIso8601String(),
                'picked_up_at' => $this->date_modified?->toIso8601String(),
                'delivered_at' => $this->date_delivered?->toIso8601String(),
            ],
            'meta' => $this->meta,
        ];
    }
}
