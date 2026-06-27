<?php

namespace App\Http\Controllers\Api\V1\Rider\Wallet;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RiderWalletController extends Controller
{
  /**
   * Get Rider Wallet Dashboard
   * 
   * Retrieves wallet balance, monthly earnings, and recent transactions
   * for the authenticated rider.
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function walletDashboard(Request $request)
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

      // Use cache for better performance (30 seconds TTL)
      $cacheKey = "rider_wallet_dashboard_{$rider->id}";

      $walletData = Cache::remember($cacheKey, 30, function () use ($rider) {
        return $this->fetchWalletDashboardData($rider);
      });

      return response()->json([
        'success' => true,
        'data' => $walletData
      ], 200);
    } catch (\Exception $e) {
      Log::error('Wallet dashboard error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id,
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve wallet dashboard'
      ], 500);
    }
  }

  /**
   * Fetch wallet dashboard data from database
   * 
   * @param Rider $rider
   * @return array
   */
  private function fetchWalletDashboardData($rider): array
  {
    // 1. Get wallet balance from rider_wallets table
    $walletBalance = $this->getWalletBalance($rider->id);

    // 2. Calculate monthly earnings from orders
    $monthlyEarnings = $this->getMonthlyEarnings($rider->id);

    // 3. Get recent transactions
    $recentTransactions = $this->getRecentTransactions($rider->id);

    return [
      'balance' => (float) $walletBalance,
      'amount_earned' => (float) $monthlyEarnings,
      'transactions' => $recentTransactions,
      'summary' => $this->getWalletSummary($rider->id)
    ];
  }

  /**
   * Get rider's current wallet balance
   * 
   * @param int $riderId
   * @return float
   */
  private function getWalletBalance(int $riderId): float
  {
    // Check if rider_wallet table exists, otherwise use riders table
    if (Schema::hasTable('rider_wallets')) {
      $wallet = DB::table('rider_wallets')
        ->where('rider_id', $riderId)
        ->first();

      return $wallet ? (float) $wallet->wallet_balance : 0;
    }

    // Fallback: Use wallet_balance from riders table
    $rider = Rider::find($riderId);
    return $rider ? (float) ($rider->wallet_balance ?? 0) : 0;
  }

  /**
   * Get total earnings for current month from delivered orders
   * 
   * @param int $riderId
   * @return float
   */
  private function getMonthlyEarnings(int $riderId): float
  {
    $currentMonth = now()->startOfMonth();
    $nextMonth = now()->endOfMonth();

    $earnings = DB::table('orders')
      ->where('rider_id', $riderId)
      ->where('status', 'delivered')
      ->whereBetween('delivered_at', [$currentMonth, $nextMonth])
      ->sum('price');

    return (float) $earnings;
  }

  /**
   * Get recent wallet transactions (last 4)
   * 
   * @param int $riderId
   * @return array
   */
  private function getRecentTransactions(int $riderId): array
  {
    // Check if rider_wallet_transactions table exists
    if (!Schema::hasTable('rider_wallet_transactions')) {
      // Fallback: Generate transactions from orders
      return $this->getFallbackTransactions($riderId);
    }

    $transactions = DB::table('rider_wallet_transactions')
      ->where('rider_id', $riderId)
      ->orderBy('date_modified', 'desc')
      ->orderBy('id', 'desc')
      ->limit(4)
      ->get();

    if ($transactions->isEmpty()) {
      return [];
    }

    return $transactions->map(function ($transaction) {
      return [
        'date_created' => $transaction->date_created ?? $transaction->created_at,
        'transaction_amount' => (float) $transaction->amount,
        'transaction_purpose' => $transaction->purpose ?? $this->getTransactionPurpose($transaction->type),
        'transaction_type' => ucfirst($transaction->type),
        'reference' => $transaction->reference ?? null,
        'status' => $transaction->status ?? 'completed',
      ];
    })->toArray();
  }

  /**
   * Fallback: Generate recent transactions from delivered orders
   * 
   * @param int $riderId
   * @return array
   */
  private function getFallbackTransactions(int $riderId): array
  {
    $recentOrders = DB::table('orders')
      ->where('rider_id', $riderId)
      ->where('status', 'delivered')
      ->whereNotNull('delivered_at')
      ->orderBy('delivered_at', 'desc')
      ->limit(4)
      ->get(['delivered_at as date_created', 'price as amount', 'order_id']);

    if ($recentOrders->isEmpty()) {
      return [];
    }

    return $recentOrders->map(function ($order) {
      return [
        'date_created' => $order->date_created,
        'transaction_amount' => (float) $order->amount,
        'transaction_purpose' => 'Order Payout - ' . ($order->order_id ?? 'Order'),
        'transaction_type' => 'Credit',
      ];
    })->toArray();
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
   * Get additional wallet summary statistics
   * 
   * @param int $riderId
   * @return array
   */
  private function getWalletSummary(int $riderId): array
  {
    // Get current week earnings
    $weekStart = now()->startOfWeek();
    $weekEnd = now()->endOfWeek();

    $weeklyEarnings = DB::table('orders')
      ->where('rider_id', $riderId)
      ->where('status', 'delivered')
      ->whereBetween('delivered_at', [$weekStart, $weekEnd])
      ->sum('price');

    // Get pending withdrawals (if withdrawals table exists)
    $pendingWithdrawals = 0;
    if (Schema::hasTable('rider_withdrawals')) {
      $pendingWithdrawals = (float) DB::table('rider_withdrawals')
        ->where('rider_id', $riderId)
        ->where('status', 'pending')
        ->sum('amount');
    }

    // Get total lifetime earnings
    $totalEarnings = (float) DB::table('orders')
      ->where('rider_id', $riderId)
      ->where('status', 'delivered')
      ->sum('price');

    return [
      'weekly_earnings' => (float) $weeklyEarnings,
      'pending_withdrawals' => $pendingWithdrawals,
      'total_lifetime_earnings' => $totalEarnings,
      'available_for_withdrawal' => (float) ($this->getWalletBalance($riderId) - $pendingWithdrawals),
    ];
  }
}
