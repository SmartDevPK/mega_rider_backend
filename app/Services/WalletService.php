<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Cache;

class WalletService
{
    public function getBalance($user)
    {
        $cacheKey = "wallet:balance:{$user->id}";

        return Cache::remember($cacheKey, 30, function () use ($user) {

            $wallet = Wallet::where('customer_id', $user->id)
                ->select('wallet_balance')
                ->first();

            if (!$wallet) {
                return null;
            }

            return $wallet->wallet_balance;
        });
    }

    public function getTransactions($userId, $cursor = null, $limit = 10)
    {
        $query = WalletTransaction::where('customer_id', $userId)
            ->orderBy('id', 'desc');

        if ($cursor) {
            $query->where('id', '<', $cursor);
        }

        $transactions = $query->take($limit + 1)->get();

        $hasMore = $transactions->count() > $limit;

        if ($hasMore) {
            $nextCursor = $transactions->last()->id;
            $transactions = $transactions->slice(0, $limit);
        } else {
            $nextCursor = null;
        }

        return [
            'transactions' => $transactions,
            'next_cursor' => $nextCursor,
        ];
    }
}


