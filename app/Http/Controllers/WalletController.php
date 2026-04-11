<?php
namespace App\Http\Controllers;

use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function balance(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 'UNAUTHORIZED'
            ], 401);
        }

        try {
            $balance = $this->walletService->getBalance($user);

            if (is_null($balance)) {
                return response()->json([
                    'success' => false,
                    'code' => 'WALLET_NOT_FOUND'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Wallet balance fetched successfully',
                'data' => [
                    'balance' => (float) $balance,
                    'currency' => 'NGN'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Wallet error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'code' => 'SERVER_ERROR'
            ], 500);
        }
    }

     public function transactions(Request $request)
    {
        $user = $request->user();

        $limit = min((int) $request->input('limit', 10), 50);
        $cursor = $request->input('cursor');

        $result = $this->walletService->getTransactions(
            $user->id,
            $cursor,
            $limit
        );

        if ($result['transactions']->isEmpty()) {
            return response()->json([
                'success' => false,
                'code' => 'NO_TRANSACTIONS_FOUND'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Wallet transactions fetched successfully',
            'data' => [
                'transactions' => $result['transactions']->map(function ($tx) {
                    return [
                        'amount' => $tx->amount,
                        'transaction_type' => $tx->type,
                        'transaction_purpose' => $tx->purpose,
                        'date_created' => $tx->created_at->toIso8601String(),
                    ];
                }),
                'next_cursor' => $result['next_cursor']
            ]
        ]);
    }
}
