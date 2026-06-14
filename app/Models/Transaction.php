<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/**
 * Transaction Model
 * 
 * Manages all financial transactions including:
 * - Wallet deposits and withdrawals
 * - Referral rewards
 * - Order payments
 * - Refunds and adjustments
 */
class Transaction extends Model
{
  use HasFactory;

    // =========================================================================
    // TABLE CONFIGURATION
    // =========================================================================

  /**
   * The table associated with the model.
   */
  protected $table = 'transactions';

  /**
   * The attributes that are mass assignable.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    // Relationships
    'user_id',

    // Transaction details
    'type',
    'purpose',
    'amount',
    'reference',

    // Balance tracking
    'balance_before',
    'balance_after',

    // Status
    'status',

    // Additional data
    'metadata',
    'description',

    // Approval tracking
    'approved_by',
    'approved_at',

    // Failure tracking
    'failure_reason',
    'failed_at',
  ];

  /**
   * The attributes that should be hidden for serialization.
   *
   * @var array<int, string>
   */
  protected $hidden = [
    // No sensitive fields by default
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array<string, string>
   */
  protected $casts = [
    // Monetary values
    'amount' => 'decimal:2',
    'balance_before' => 'decimal:2',
    'balance_after' => 'decimal:2',

    // JSON
    'metadata' => 'array',

    // Booleans
    'is_flagged' => 'boolean',

    // Dates
    'approved_at' => 'datetime',
    'failed_at' => 'datetime',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  /**
   * The model's default values for attributes.
   */
  protected $attributes = [
    'status' => 'pending',
    'is_flagged' => false,
  ];

  /**
   * The accessors to append to the model's array form.
   */
  protected $appends = [
    'formatted_amount',
    'formatted_balance_before',
    'formatted_balance_after',
    'purpose_label',
    'type_label',
    'status_label',
    'status_badge',
  ];

    // =========================================================================
    // CONSTANTS
    // =========================================================================

  /**
   * Transaction types.
   */
  public const TYPE_CREDIT = 'credit';
  public const TYPE_DEBIT = 'debit';

  /**
   * Transaction purposes.
   */
  public const PURPOSE_REFERRAL_REWARD = 'referral_reward';
  public const PURPOSE_ORDER_PAYMENT = 'order_payment';
  public const PURPOSE_WALLET_FUNDING = 'wallet_funding';
  public const PURPOSE_WALLET_WITHDRAWAL = 'wallet_withdrawal';
  public const PURPOSE_REFUND = 'refund';
  public const PURPOSE_ADJUSTMENT = 'adjustment';
  public const PURPOSE_BONUS = 'bonus';
  public const PURPOSE_CASHBACK = 'cashback';
  public const PURPOSE_PENALTY = 'penalty';

  /**
   * Transaction statuses.
   */
  public const STATUS_PENDING = 'pending';
  public const STATUS_SUCCESS = 'success';
  public const STATUS_FAILED = 'failed';
  public const STATUS_REVERSED = 'reversed';
  public const STATUS_PENDING_APPROVAL = 'pending_approval';

  /**
   * Available transaction types.
   */
  public static array $types = [
    self::TYPE_CREDIT,
    self::TYPE_DEBIT,
  ];

  /**
   * Available transaction purposes.
   */
  public static array $purposes = [
    self::PURPOSE_REFERRAL_REWARD,
    self::PURPOSE_ORDER_PAYMENT,
    self::PURPOSE_WALLET_FUNDING,
    self::PURPOSE_WALLET_WITHDRAWAL,
    self::PURPOSE_REFUND,
    self::PURPOSE_ADJUSTMENT,
    self::PURPOSE_BONUS,
    self::PURPOSE_CASHBACK,
    self::PURPOSE_PENALTY,
  ];

  /**
   * Available transaction statuses.
   */
  public static array $statuses = [
    self::STATUS_PENDING,
    self::STATUS_SUCCESS,
    self::STATUS_FAILED,
    self::STATUS_REVERSED,
    self::STATUS_PENDING_APPROVAL,
  ];

  /**
   * Status badge mapping.
   */
  public static array $statusBadges = [
    self::STATUS_PENDING => ['class' => 'warning', 'icon' => '⏳', 'text' => 'Pending'],
    self::STATUS_SUCCESS => ['class' => 'success', 'icon' => '✅', 'text' => 'Success'],
    self::STATUS_FAILED => ['class' => 'danger', 'icon' => '❌', 'text' => 'Failed'],
    self::STATUS_REVERSED => ['class' => 'info', 'icon' => '🔄', 'text' => 'Reversed'],
    self::STATUS_PENDING_APPROVAL => ['class' => 'secondary', 'icon' => '📝', 'text' => 'Pending Approval'],
  ];

  /**
   * Purpose labels for display.
   */
  public static array $purposeLabels = [
    self::PURPOSE_REFERRAL_REWARD => 'Referral Reward',
    self::PURPOSE_ORDER_PAYMENT => 'Order Payment',
    self::PURPOSE_WALLET_FUNDING => 'Wallet Funding',
    self::PURPOSE_WALLET_WITHDRAWAL => 'Wallet Withdrawal',
    self::PURPOSE_REFUND => 'Refund',
    self::PURPOSE_ADJUSTMENT => 'Adjustment',
    self::PURPOSE_BONUS => 'Bonus',
    self::PURPOSE_CASHBACK => 'Cashback',
    self::PURPOSE_PENALTY => 'Penalty',
  ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

  /**
   * Get the user (customer) that owns this transaction.
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(Customer::class, 'user_id');
  }

  /**
   * Get the admin who approved this transaction (if applicable).
   */
  public function approver(): BelongsTo
  {
    return $this->belongsTo(Admin::class, 'approved_by');
  }

  /**
   * Get the order associated with this transaction.
   */
  public function order(): BelongsTo
  {
    return $this->belongsTo(Order::class, 'order_id');
  }

    // =========================================================================
    // SCOPES
    // =========================================================================

  /**
   * Scope to get credit transactions.
   */
  public function scopeCredits(Builder $query): Builder
  {
    return $query->where('type', self::TYPE_CREDIT);
  }

  /**
   * Scope to get debit transactions.
   */
  public function scopeDebits(Builder $query): Builder
  {
    return $query->where('type', self::TYPE_DEBIT);
  }

  /**
   * Scope to get successful transactions.
   */
  public function scopeSuccessful(Builder $query): Builder
  {
    return $query->where('status', self::STATUS_SUCCESS);
  }

  /**
   * Scope to get failed transactions.
   */
  public function scopeFailed(Builder $query): Builder
  {
    return $query->where('status', self::STATUS_FAILED);
  }

  /**
   * Scope to get pending transactions.
   */
  public function scopePending(Builder $query): Builder
  {
    return $query->where('status', self::STATUS_PENDING);
  }

  /**
   * Scope to get reversed transactions.
   */
  public function scopeReversed(Builder $query): Builder
  {
    return $query->where('status', self::STATUS_REVERSED);
  }

  /**
   * Scope to get transactions for a specific user.
   */
  public function scopeForUser(Builder $query, int $userId): Builder
  {
    return $query->where('user_id', $userId);
  }

  /**
   * Scope to get transactions by purpose.
   */
  public function scopeOfPurpose(Builder $query, string $purpose): Builder
  {
    return $query->where('purpose', $purpose);
  }

  /**
   * Scope to get transactions by date range.
   */
  public function scopeDateRange(Builder $query, string $startDate, string $endDate): Builder
  {
    return $query->whereBetween('created_at', [$startDate, $endDate]);
  }

  /**
   * Scope to get today's transactions.
   */
  public function scopeToday(Builder $query): Builder
  {
    return $query->whereDate('created_at', today());
  }

  /**
   * Scope to get this month's transactions.
   */
  public function scopeThisMonth(Builder $query): Builder
  {
    return $query->whereMonth('created_at', now()->month)
      ->whereYear('created_at', now()->year);
  }

  /**
   * Scope to get transactions with amount greater than.
   */
  public function scopeAmountGreaterThan(Builder $query, float $amount): Builder
  {
    return $query->where('amount', '>', $amount);
  }

  /**
   * Scope to get flagged transactions.
   */
  public function scopeFlagged(Builder $query): Builder
  {
    return $query->where('is_flagged', true);
  }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

  /**
   * Get formatted amount (e.g., "₦1,500.00").
   */
  public function getFormattedAmountAttribute(): string
  {
    $prefix = $this->type === self::TYPE_CREDIT ? '+' : '-';
    return $prefix . '₦' . number_format($this->amount, 2);
  }

  /**
   * Get formatted balance before.
   */
  public function getFormattedBalanceBeforeAttribute(): string
  {
    return '₦' . number_format($this->balance_before ?? 0, 2);
  }

  /**
   * Get formatted balance after.
   */
  public function getFormattedBalanceAfterAttribute(): string
  {
    return '₦' . number_format($this->balance_after ?? 0, 2);
  }

  /**
   * Get transaction purpose label.
   */
  public function getPurposeLabelAttribute(): string
  {
    return self::$purposeLabels[$this->purpose] ?? ucwords(str_replace('_', ' ', $this->purpose));
  }

  /**
   * Get transaction type label.
   */
  public function getTypeLabelAttribute(): string
  {
    return ucfirst($this->type);
  }

  /**
   * Get transaction status label.
   */
  public function getStatusLabelAttribute(): string
  {
    return self::$statusBadges[$this->status]['text'] ?? ucfirst($this->status);
  }

  /**
   * Get status badge HTML class.
   */
  public function getStatusBadgeAttribute(): array
  {
    return self::$statusBadges[$this->status] ?? [
      'class' => 'secondary',
      'icon' => '❓',
      'text' => ucfirst($this->status),
    ];
  }

  /**
   * Get transaction icon based on type.
   */
  public function getIconAttribute(): string
  {
    if ($this->type === self::TYPE_CREDIT) {
      return '💰';
    }

    return match ($this->purpose) {
      self::PURPOSE_ORDER_PAYMENT => '🛒',
      self::PURPOSE_WALLET_WITHDRAWAL => '💸',
      self::PURPOSE_REFUND => '🔄',
      default => '💳',
    };
  }

    // =========================================================================
    // BUSINESS LOGIC METHODS
    // =========================================================================

  /**
   * Check if transaction is a credit.
   */
  public function isCredit(): bool
  {
    return $this->type === self::TYPE_CREDIT;
  }

  /**
   * Check if transaction is a debit.
   */
  public function isDebit(): bool
  {
    return $this->type === self::TYPE_DEBIT;
  }

  /**
   * Check if transaction is successful.
   */
  public function isSuccessful(): bool
  {
    return $this->status === self::STATUS_SUCCESS;
  }

  /**
   * Check if transaction is pending.
   */
  public function isPending(): bool
  {
    return $this->status === self::STATUS_PENDING;
  }

  /**
   * Check if transaction is failed.
   */
  public function isFailed(): bool
  {
    return $this->status === self::STATUS_FAILED;
  }

  /**
   * Check if transaction is reversed.
   */
  public function isReversed(): bool
  {
    return $this->status === self::STATUS_REVERSED;
  }

  /**
   * Mark transaction as successful.
   */
  public function markAsSuccessful(): bool
  {
    $this->status = self::STATUS_SUCCESS;

    return $this->save();
  }

  /**
   * Mark transaction as failed.
   */
  public function markAsFailed( $reason = null): bool
  {
    $this->status = self::STATUS_FAILED;
    $this->failure_reason = $reason;
    $this->failed_at = now();

    return $this->save();
  }

  /**
   * Mark transaction as reversed.
   */
  public function markAsReversed(): bool
  {
    $this->status = self::STATUS_REVERSED;

    return $this->save();
  }

  /**
   * Approve transaction (for pending approval transactions).
   */
  public function approve(int $adminId): bool
  {
    $this->status = self::STATUS_SUCCESS;
    $this->approved_by = $adminId;
    $this->approved_at = now();

    return $this->save();
  }

  /**
   * Get metadata value by key.
   */
  public function getMetadata(string $key, $default = null)
  {
    return $this->metadata[$key] ?? $default;
  }

  /**
   * Set metadata value.
   */
  public function setMetadata(string $key, $value): self
  {
    $metadata = $this->metadata ?? [];
    $metadata[$key] = $value;
    $this->metadata = $metadata;

    return $this;
  }

  /**
   * Create a reversal transaction.
   */
  public function reverse(?string $reason = null): ?self
  {
    if ($this->isReversed()) {
      return null;
    }

    $reversal = self::create([
      'user_id' => $this->user_id,
      'type' => $this->type === self::TYPE_CREDIT ? self::TYPE_DEBIT : self::TYPE_CREDIT,
      'purpose' => self::PURPOSE_REFUND,
      'amount' => $this->amount,
      'reference' => $this->reference . '_REVERSAL',
      'balance_before' => $this->balance_after,
      'balance_after' => $this->balance_before,
      'status' => self::STATUS_SUCCESS,
      'metadata' => [
        'original_transaction_id' => $this->id,
        'original_reference' => $this->reference,
        'reason' => $reason,
      ],
    ]);

    $this->markAsReversed();

    return $reversal;
  }

    // =========================================================================
    // STATISTICS METHODS
    // =========================================================================

  /**
   * Get total credits for a user.
   */
  public static function getTotalCredits(int $userId): float
  {
    return (float) self::forUser($userId)
      ->credits()
      ->successful()
      ->sum('amount');
  }

  /**
   * Get total debits for a user.
   */
  public static function getTotalDebits(int $userId): float
  {
    return (float) self::forUser($userId)
      ->debits()
      ->successful()
      ->sum('amount');
  }

  /**
   * Get current balance for a user.
   */
  public static function getCurrentBalance(int $userId): float
  {
    $credits = self::getTotalCredits($userId);
    $debits = self::getTotalDebits($userId);

    return $credits - $debits;
  }

  /**
   * Get transaction summary for a user.
   */
  public static function getSummary(int $userId): array
  {
    return [
      'total_credits' => self::getTotalCredits($userId),
      'total_debits' => self::getTotalDebits($userId),
      'current_balance' => self::getCurrentBalance($userId),
      'transaction_count' => self::forUser($userId)->count(),
      'last_transaction' => self::forUser($userId)
        ->latest()
        ->first(),
    ];
  }

  // =========================================================================
  // BOOT METHOD
  // =========================================================================

  protected static function boot(): void
  {
    parent::boot();

    static::creating(function (Transaction $transaction): void {
      // Generate unique reference if not provided
      if (empty($transaction->reference)) {
        $transaction->reference = self::generateReference();
      }

      // Set default status if not set
      if (empty($transaction->status)) {
        $transaction->status = self::STATUS_PENDING;
      }

      Log::info('Transaction created', [
        'user_id' => $transaction->user_id,
        'type' => $transaction->type,
        'amount' => $transaction->amount,
        'reference' => $transaction->reference,
      ]);
    });

    static::updating(function (Transaction $transaction): void {
      if ($transaction->isDirty('status')) {
        Log::info('Transaction status changed', [
          'transaction_id' => $transaction->id,
          'old_status' => $transaction->getOriginal('status'),
          'new_status' => $transaction->status,
        ]);
      }
    });
  }

  /**
   * Generate unique transaction reference.
   */
  public static function generateReference(): string
  {
    $prefix = 'TXN';
    $date = now()->format('YmdHis');
    $random = strtoupper(substr(uniqid(), -6));

    do {
      $reference = $prefix . $date . $random;
    } while (self::where('reference', $reference)->exists());

    return $reference;
  }
}
