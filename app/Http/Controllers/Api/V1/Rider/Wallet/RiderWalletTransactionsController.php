<?php

namespace App\Http\Controllers\Api\V1\Rider\Wallet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RiderWalletTransactionsController extends Controller
{
  /**
   * Get Rider Wallet Transactions with Cursor Pagination
   * 
   * Retrieves a paginated list of wallet transactions for the authenticated rider.
   * Supports filtering by transaction type and cursor-based pagination.
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getWalletTransactions(Request $request)
  {
    try {
      $rider = $request->user();

      // Check if authenticated user is actually a rider
      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      // Validate request
      $request->validate([
        'page_size' => 'required|integer|min:1|max:100',
        'cursor' => 'nullable|string',
        'transaction_type' => 'nullable|string|in:Credit,Debit,credit,debit'
      ]);

      // Use cache for better performance (15 seconds TTL for transactions)
      $cacheKey = $this->generateTransactionCacheKey($rider->id, $request);
      $cacheTTL = 15;

      $result = Cache::remember($cacheKey, $cacheTTL, function () use ($rider, $request) {
        return $this->fetchWalletTransactions($rider, $request);
      });

      return response()->json([
        'success' => true,
        'data' => $result
      ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      Log::error('Wallet transactions error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id,
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve wallet transactions'
      ], 500);
    }
  }

  /**
   * Fetch wallet transactions from database with cursor pagination
   *
   * @param Rider $rider
   * @param Request $request
   * @return array
   */
  private function fetchWalletTransactions($rider, Request $request): array
  {
    $pageSize = (int) $request->input('page_size', 10);
    $cursor = $request->input('cursor');
    $transactionType = $request->input('transaction_type');

    // Check if rider_wallet_transactions table exists
    if (!Schema::hasTable('rider_wallet_transactions')) {
      // Fallback: Generate transactions from orders
      return $this->getFallbackWalletTransactions($rider->id, $pageSize, $cursor);
    }

    // Build query
    $query = DB::table('rider_wallet_transactions')
      ->where('rider_id', $rider->id);

    // Apply transaction type filter
    if ($transactionType) {
      $type = strtolower($transactionType);
      $query->where('type', $type);
    }

    // Apply cursor pagination
    if ($cursor) {
      $this->applyTransactionCursor($query, $cursor);
    }

    // Order by date_modified DESC, id DESC
    $query->orderBy('date_modified', 'desc')
      ->orderBy('id', 'desc');

    // Fetch one extra record to determine if there's more
    $transactions = $query->limit($pageSize + 1)->get();

    // Determine if there's more
    $hasMore = $transactions->count() > $pageSize;

    // Remove the extra record if it exists
    if ($hasMore) {
      $transactions = $transactions->slice(0, $pageSize);
    }

    // Transform transactions
    $formattedTransactions = $transactions->map(function ($transaction) {
      return [
        'transaction_id' => $transaction->id,
        'date_created' => $transaction->date_created ?? $transaction->created_at,
        'transaction_amount' => (float) $transaction->amount,
        'transaction_purpose' => $transaction->purpose ?? $this->getTransactionPurpose($transaction->type),
        'transaction_type' => ucfirst($transaction->type),
        'reference' => $transaction->reference ?? null,
        'status' => $transaction->status ?? 'completed',
        'order_id' => $transaction->order_id ?? null,
      ];
    })->values()->toArray();

    // Generate next cursor
    $nextCursor = null;
    if ($hasMore && $transactions->isNotEmpty()) {
      $lastTransaction = $transactions->last();
      $nextCursor = $this->generateTransactionCursor($lastTransaction);
    }

    return [
      'transactions' => $formattedTransactions,
      'pagination' => [
        'has_more' => $hasMore,
        'next_cursor' => $nextCursor,
        'page_size' => $pageSize
      ]
    ];
  }

  /**
   * Fallback: Generate wallet transactions from delivered orders
   *
   * @param int $riderId
   * @param int $pageSize
   * @param string|null $cursor
   * @return array
   */
  private function getFallbackWalletTransactions(int $riderId, int $pageSize, ?string $cursor): array
  {
    $query = DB::table('orders')
      ->where('rider_id', $riderId)
      ->where('status', 'delivered')
      ->whereNotNull('delivered_at');

    // Apply cursor pagination
    if ($cursor) {
      $this->applyOrderCursor($query, $cursor);
    }

    // Order by delivered_at DESC, id DESC
    $query->orderBy('delivered_at', 'desc')
      ->orderBy('id', 'desc');

    // Fetch one extra record
    $orders = $query->limit($pageSize + 1)->get(['id', 'delivered_at as date_created', 'price as amount', 'order_id']);

    $hasMore = $orders->count() > $pageSize;

    if ($hasMore) {
      $orders = $orders->slice(0, $pageSize);
    }

    $transactions = $orders->map(function ($order) {
      return [
        'transaction_id' => $order->id,
        'date_created' => $order->date_created,
        'transaction_amount' => (float) $order->amount,
        'transaction_purpose' => 'Order Payout - ' . ($order->order_id ?? 'Order #' . $order->id),
        'transaction_type' => 'Credit',
        'reference' => null,
        'status' => 'completed',
        'order_id' => $order->order_id,
      ];
    })->values()->toArray();

    // Generate next cursor
    $nextCursor = null;
    if ($hasMore && $orders->isNotEmpty()) {
      $lastOrder = $orders->last();
      $nextCursor = base64_encode($lastOrder->date_created . '|' . $lastOrder->id);
    }

    return [
      'transactions' => $transactions,
      'pagination' => [
        'has_more' => $hasMore,
        'next_cursor' => $nextCursor,
        'page_size' => $pageSize
      ]
    ];
  }

  /**
   * Apply cursor condition to transaction query
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param string $cursor
   * @return void
   */
  private function applyTransactionCursor($query, string $cursor): void
  {
    $cursorData = $this->decodeTransactionCursor($cursor);

    if (!$cursorData || !isset($cursorData['date_modified']) || !isset($cursorData['id'])) {
      return;
    }

    $query->where(function ($q) use ($cursorData) {
      $q->where('date_modified', '<', $cursorData['date_modified'])
        ->orWhere(function ($subQuery) use ($cursorData) {
          $subQuery->where('date_modified', '=', $cursorData['date_modified'])
            ->where('id', '<', $cursorData['id']);
        });
    });
  }

  /**
   * Apply cursor condition to orders query (fallback)
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param string $cursor
   * @return void
   */
  private function applyOrderCursor($query, string $cursor): void
  {
    $cursorData = $this->decodeTransactionCursor($cursor);

    if (!$cursorData || !isset($cursorData['date_modified']) || !isset($cursorData['id'])) {
      return;
    }

    $query->where(function ($q) use ($cursorData) {
      $q->where('delivered_at', '<', $cursorData['date_modified'])
        ->orWhere(function ($subQuery) use ($cursorData) {
          $subQuery->where('delivered_at', '=', $cursorData['date_modified'])
            ->where('id', '<', $cursorData['id']);
        });
    });
  }

  /**
   * Generate cursor for next page
   *
   * @param object $transaction
   * @return string
   */
  private function generateTransactionCursor($transaction): string
  {
    $dateModified = $transaction->date_modified ?? $transaction->date_created ?? $transaction->delivered_at;

    if ($dateModified instanceof \DateTime) {
      $dateModified = $dateModified->format('Y-m-d H:i:s');
    }

    return base64_encode($dateModified . '|' . $transaction->id);
  }

  /**
   * Decode cursor from request
   *
   * @param string $cursor
   * @return array|null
   */
  private function decodeTransactionCursor(string $cursor): ?array
  {
    try {
      $decoded = base64_decode($cursor);
      $parts = explode('|', $decoded);

      if (count($parts) !== 2) {
        return null;
      }

      return [
        'date_modified' => $parts[0],
        'id' => (int) $parts[1]
      ];
    } catch (\Exception $e) {
      return null;
    }
  }

  /**
   * Get transaction purpose based on type
   * 
   * @param string $type
   * @return string
   */
  private function getTransactionPurpose(string $type): string
  {
    return match ($type) {
      'credit' => 'Order Payout',
      'debit' => 'Withdrawal',
      'bonus' => 'Bonus Reward',
      'adjustment' => 'Balance Adjustment',
      'fee' => 'Service Fee',
      default => 'Transaction',
    };
  }

  /**
   * Generate cache key for wallet transactions
   *
   * @param int $riderId
   * @param Request $request
   * @return string
   */
  private function generateTransactionCacheKey(int $riderId, Request $request): string
  {
    $parts = [
      'rider_wallet_transactions',
      $riderId,
      $request->input('page_size', 10),
      $request->input('transaction_type', 'all'),
      md5($request->input('cursor', 'first'))
    ];

    return implode(':', $parts);
  }
}
