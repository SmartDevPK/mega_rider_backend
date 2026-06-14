<?php

namespace App\Services\Rider\Bank;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class WalletService
{
  /**
   * Cache duration in seconds (default: 5 minutes).
   */
  protected int $cacheDuration;

  /**
   * Default transaction limit per page.
   */
  protected int $defaultLimit;

  /**
   * Create a new wallet service instance.
   */
  public function __construct()
  {
    $this->cacheDuration = config('wallet.cache_duration', 300);
    $this->defaultLimit = config('wallet.transaction_limit', 10);
  }

  /**
   * Get wallet balance for a user.
   *
   * @param User $user
   * @return float|null Wallet balance or null if not found
   */
  public function getBalance(User $user): ?float
  {
    try {
      $cacheKey = $this->generateBalanceCacheKey($user->id);

      return Cache::remember($cacheKey, $this->cacheDuration, function () use ($user) {
        return $this->fetchBalanceFromDatabase($user);
      });
    } catch (\Exception $e) {
      Log::error('Failed to get wallet balance', [
        'user_id' => $user->id,
        'error' => $e->getMessage(),
      ]);

      return $this->fetchBalanceFromDatabase($user);
    }
  }

  /**
   * Get wallet balance with force refresh (bypass cache).
   *
   * @param User $user
   * @return float|null
   */
  public function getBalanceRealTime(User $user): ?float
  {
    $this->clearBalanceCache($user->id);
    return $this->fetchBalanceFromDatabase($user);
  }

  /**
   * Fetch wallet balance directly from database.
   */
  protected function fetchBalanceFromDatabase(User $user): ?float
  {
    $wallet = Wallet::where('user_id', $user->id)
      ->orWhere('customer_id', $user->id)
      ->select('wallet_balance')
      ->first();

    if (!$wallet) {
      return 0.00;
    }

    return (float) $wallet->wallet_balance;
  }

  /**
   * Generate cache key for wallet balance.
   */
  protected function generateBalanceCacheKey(int $userId): string
  {
    return "wallet:balance:{$userId}";
  }

  /**
   * Clear wallet balance cache.
   */
  public function clearBalanceCache(int $userId): bool
  {
    $cacheKey = $this->generateBalanceCacheKey($userId);
    return Cache::forget($cacheKey);
  }

  /**
   * Get wallet transactions with cursor-based pagination.
   *
   * @param int $userId
   * @param int|null $cursor
   * @param int|null $limit
   * @param array $filters Optional filters (type, status, date_from, date_to)
   * @return array
   */
  public function getTransactions(
    int $userId,
    ?int $cursor = null,
    ?int $limit = null,
    array $filters = []
  ): array {
    $limit = $limit ?? $this->defaultLimit;

    try {
      $query = $this->buildTransactionQuery($userId, $filters);

      if ($cursor) {
        $query->where('id', '<', $cursor);
      }

      $transactions = $query->take($limit + 1)->get();

      return $this->formatPaginatedResponse($transactions, $limit);
    } catch (\Exception $e) {
      Log::error('Failed to get wallet transactions', [
        'user_id' => $userId,
        'error' => $e->getMessage(),
      ]);

      return [
        'transactions' => collect(),
        'next_cursor' => null,
        'has_more' => false,
      ];
    }
  }

  /**
   * Build transaction query with filters.
   */
  protected function buildTransactionQuery(int $userId, array $filters)
  {
    $query = WalletTransaction::where('user_id', $userId)
      ->orWhere('customer_id', $userId)
      ->orderBy('id', 'desc');

    // Apply type filter (credit/debit)
    if (!empty($filters['type'])) {
      $query->where('type', $filters['type']);
    }

    // Apply status filter
    if (!empty($filters['status'])) {
      $query->where('status', $filters['status']);
    }

    // Apply date range filter
    if (!empty($filters['date_from'])) {
      $query->whereDate('created_at', '>=', $filters['date_from']);
    }

    if (!empty($filters['date_to'])) {
      $query->whereDate('created_at', '<=', $filters['date_to']);
    }

    // Apply minimum amount filter
    if (!empty($filters['min_amount'])) {
      $query->where('amount', '>=', $filters['min_amount']);
    }

    // Apply maximum amount filter
    if (!empty($filters['max_amount'])) {
      $query->where('amount', '<=', $filters['max_amount']);
    }

    return $query;
  }

  /**
   * Format paginated response.
   */
  protected function formatPaginatedResponse($transactions, int $limit): array
  {
    $hasMore = $transactions->count() > $limit;

    if ($hasMore) {
      $nextCursor = $transactions->last()->id;
      $transactions = $transactions->slice(0, $limit);
    } else {
      $nextCursor = null;
    }

    return [
      'transactions' => $transactions->values()->toArray(),
      'next_cursor' => $nextCursor,
      'has_more' => $hasMore,
      'total' => $transactions->count(),
    ];
  }
  /**
   * Get transaction summary for a user.
   *
   * @param int $userId
   * @return array
   */
  public function getTransactionSummary(int $userId): array
  {
    try {
      $totalCredits = WalletTransaction::where('user_id', $userId)
        ->where('type', 'credit')
        ->where('status', 'success')
        ->sum('amount');

      $totalDebits = WalletTransaction::where('user_id', $userId)
        ->where('type', 'debit')
        ->where('status', 'success')
        ->sum('amount');

      $todayCredits = WalletTransaction::where('user_id', $userId)
        ->where('type', 'credit')
        ->where('status', 'success')
        ->whereDate('created_at', Carbon::today())
        ->sum('amount');

      return [
        'total_credits' => (float) $totalCredits,
        'total_debits' => (float) $totalDebits,
        'net_balance' => (float) ($totalCredits - $totalDebits),
        'today_credits' => (float) $todayCredits,
        'transaction_count' => WalletTransaction::where('user_id', $userId)->count(),
      ];
    } catch (\Exception $e) {
      Log::error('Failed to get transaction summary', [
        'user_id' => $userId,
        'error' => $e->getMessage(),
      ]);

      return [
        'total_credits' => 0.00,
        'total_debits' => 0.00,
        'net_balance' => 0.00,
        'today_credits' => 0.00,
        'transaction_count' => 0,
      ];
    }
  }

  /**
   * Credit a user's wallet.
   *
   * @param User $user
   * @param float $amount
   * @param string $purpose
   * @param array $metadata
   * @return WalletTransaction
   * @throws ValidationException
   */
  public function creditWallet(User $user, float $amount, string $purpose, array $metadata = []): WalletTransaction
  {
    if ($amount <= 0) {
      throw ValidationException::withMessages([
        'amount' => ['Amount must be greater than zero.']
      ]);
    }

    return DB::transaction(function () use ($user, $amount, $purpose, $metadata) {
      // Get or create wallet
      $wallet = Wallet::firstOrCreate(
        ['user_id' => $user->id],
        ['wallet_balance' => 0]
      );

      $balanceBefore = (float) $wallet->wallet_balance;
      $balanceAfter = $balanceBefore + $amount;

      // Update wallet balance
      $wallet->increment('wallet_balance', $amount);

      // Create transaction record
      $transaction = WalletTransaction::create([
        'user_id' => $user->id,
        'type' => 'credit',
        'amount' => $amount,
        'balance_before' => $balanceBefore,
        'balance_after' => $balanceAfter,
        'purpose' => $purpose,
        'status' => 'success',
        'metadata' => $metadata,
        'reference' => $this->generateReference(),
      ]);

      // Clear cache
      $this->clearBalanceCache($user->id);

      Log::info('Wallet credited', [
        'user_id' => $user->id,
        'amount' => $amount,
        'balance_after' => $balanceAfter,
        'purpose' => $purpose,
      ]);

      return $transaction;
    });
  }

  /**
   * Debit a user's wallet.
   *
   * @param User $user
   * @param float $amount
   * @param string $purpose
   * @param array $metadata
   * @return WalletTransaction
   * @throws ValidationException
   */
  public function debitWallet(User $user, float $amount, string $purpose, array $metadata = []): WalletTransaction
  {
    if ($amount <= 0) {
      throw ValidationException::withMessages([
        'amount' => ['Amount must be greater than zero.']
      ]);
    }

    return DB::transaction(function () use ($user, $amount, $purpose, $metadata) {
      $wallet = Wallet::where('user_id', $user->id)->first();

      if (!$wallet) {
        throw ValidationException::withMessages([
          'wallet' => ['Wallet not found.']
        ]);
      }

      $balanceBefore = (float) $wallet->wallet_balance;

      if ($balanceBefore < $amount) {
        throw ValidationException::withMessages([
          'amount' => ['Insufficient wallet balance.']
        ]);
      }

      $balanceAfter = $balanceBefore - $amount;

      // Update wallet balance
      $wallet->decrement('wallet_balance', $amount);

      // Create transaction record
      $transaction = WalletTransaction::create([
        'user_id' => $user->id,
        'type' => 'debit',
        'amount' => $amount,
        'balance_before' => $balanceBefore,
        'balance_after' => $balanceAfter,
        'purpose' => $purpose,
        'status' => 'success',
        'metadata' => $metadata,
        'reference' => $this->generateReference(),
      ]);

      // Clear cache
      $this->clearBalanceCache($user->id);

      Log::info('Wallet debited', [
        'user_id' => $user->id,
        'amount' => $amount,
        'balance_after' => $balanceAfter,
        'purpose' => $purpose,
      ]);

      return $transaction;
    });
  }

  /**
   * Generate unique transaction reference.
   */
  protected function generateReference(): string
  {
    return 'WLT_' . strtoupper(uniqid() . '_' . bin2hex(random_bytes(8)));
  }

  /**
   * Get wallet details with balance and stats.
   *
   * @param User $user
   * @return array
   */
  public function getWalletDetails(User $user): array
  {
    $balance = $this->getBalance($user);
    $summary = $this->getTransactionSummary($user->id);

    return [
      'user_id' => $user->id,
      'balance' => $balance,
      'total_credited' => $summary['total_credits'],
      'total_debited' => $summary['total_debits'],
      'net_balance' => $summary['net_balance'],
      'currency' => config('wallet.currency', 'NGN'),
      'updated_at' => now(),
    ];
  }

  /**
   * Check if user has sufficient balance.
   *
   * @param User $user
   * @param float $amount
   * @return bool
   */
  public function hasSufficientBalance(User $user, float $amount): bool
  {
    $balance = $this->getBalance($user);
    return $balance >= $amount;
  }

  /**
   * Get transaction by reference.
   *
   * @param string $reference
   * @return WalletTransaction|null
   */
  public function getTransactionByReference(string $reference): ?WalletTransaction
  {
    return WalletTransaction::where('reference', $reference)->first();
  }
}
