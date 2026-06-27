<?php

namespace App\Http\Controllers\Api\V1\Rider\Banking;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RiderAccountResolutionController extends Controller
{
  /**
   * Resolve Bank Account Name
   * 
   * Resolves and returns the account name associated with
   * a supplied bank account number and bank code using Paystack API.
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function resolveBankAccount(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $request->validate([
        'account_number' => 'required|string|min:10|max:10',
        'bank_code' => 'required|string|min:3|max:3'
      ]);

      $accountNumber = $request->account_number;
      $bankCode = $request->bank_code;

      // Check cache first
      $cacheKey = "bank_resolution_{$bankCode}_{$accountNumber}";
      $cacheTTL = 600; // 10 minutes

      if (Cache::has($cacheKey)) {
        return response()->json([
          'success' => true,
          'source' => 'cache',
          'data' => Cache::get($cacheKey),
          'message' => 'Account resolved successfully'
        ], 200);
      }

      $result = $this->resolveAccount($accountNumber, $bankCode);

      if (!$result['success']) {
        return response()->json([
          'success' => false,
          'message' => $result['message']
        ], 400);
      }

      Cache::put($cacheKey, $result['data'], $cacheTTL);

      return response()->json([
        'success' => true,
        'source' => 'api',
        'data' => $result['data'],
        'message' => 'Account resolved successfully'
      ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      Log::error('Account resolution error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to resolve account details'
      ], 500);
    }
  }

  /**
   * Resolve Bank Account with Multiple Attempts
   * 
   * Enhanced version with retry logic for network issues
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function resolveBankAccountWithRetry(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $request->validate([
        'account_number' => 'required|string|min:10|max:10',
        'bank_code' => 'required|string|min:3|max:3',
        'retry_count' => 'nullable|integer|min:1|max:3'
      ]);

      $accountNumber = $request->account_number;
      $bankCode = $request->bank_code;
      $maxRetries = $request->input('retry_count', 2);

      // Check cache first
      $cacheKey = "bank_resolution_{$bankCode}_{$accountNumber}";

      if (Cache::has($cacheKey)) {
        return response()->json([
          'success' => true,
          'source' => 'cache',
          'data' => Cache::get($cacheKey),
          'message' => 'Account resolved successfully'
        ], 200);
      }

      // Attempt resolution with retries
      $attempt = 0;
      $lastError = null;

      while ($attempt < $maxRetries) {
        try {
          $result = $this->resolveAccount($accountNumber, $bankCode);

          if ($result['success']) {
            Cache::put($cacheKey, $result['data'], 600);

            return response()->json([
              'success' => true,
              'source' => 'api',
              'attempts' => $attempt + 1,
              'data' => $result['data'],
              'message' => 'Account resolved successfully'
            ], 200);
          }

          $attempt++;
          $lastError = $result['message'];

          if ($attempt < $maxRetries) {
            usleep(500000 * $attempt); // Exponential backoff
          }
        } catch (\Exception $e) {
          $attempt++;
          $lastError = $e->getMessage();

          if ($attempt < $maxRetries) {
            usleep(500000 * $attempt);
          }
        }
      }

      return response()->json([
        'success' => false,
        'message' => $lastError ?? 'Unable to resolve account',
        'attempts_made' => $maxRetries
      ], 400);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      Log::error('Account resolution with retry error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to resolve account details'
      ], 500);
    }
  }

  /**
   * Resolve account internally
   *
   * @param string $accountNumber
   * @param string $bankCode
   * @return array
   */
  private function resolveAccount(string $accountNumber, string $bankCode): array
  {
    try {
      $paystackSecretKey = config('services.paystack.secret_key');

      if (!$paystackSecretKey) {
        return [
          'success' => false,
          'message' => 'Payment service not configured'
        ];
      }

      $response = Http::timeout(10)
        ->withHeaders([
          'Authorization' => 'Bearer ' . $paystackSecretKey,
          'Content-Type' => 'application/json',
        ])
        ->get('https://api.paystack.co/bank/resolve', [
          'account_number' => $accountNumber,
          'bank_code' => $bankCode
        ]);

      if (!$response->successful()) {
        return [
          'success' => false,
          'message' => $this->getErrorMessage($response->status(), $response->json())
        ];
      }

      $responseData = $response->json();

      if (!$responseData['status'] || empty($responseData['data'])) {
        return [
          'success' => false,
          'message' => 'Invalid account number or bank code'
        ];
      }

      $bankName = $this->getBankNameByCode($bankCode);

      return [
        'success' => true,
        'data' => [
          'account_name' => strtoupper($responseData['data']['account_name']),
          'account_number' => $accountNumber,
          'bank_code' => $bankCode,
          'bank_name' => $bankName,
        ]
      ];
    } catch (\Exception $e) {
      return [
        'success' => false,
        'message' => $e->getMessage()
      ];
    }
  }

  /**
   * Get bank name by bank code
   *
   * @param string $bankCode
   * @return string|null
   */
  private function getBankNameByCode(string $bankCode): ?string
  {
    $cacheKey = 'paystack_banks_list';
    $banks = Cache::get($cacheKey);

    if ($banks) {
      $bank = collect($banks)->firstWhere('code', $bankCode);
      return $bank['name'] ?? null;
    }

    return null;
  }

  /**
   * Get user-friendly error message
   *
   * @param int $statusCode
   * @param array $response
   * @return string
   */
  private function getErrorMessage(int $statusCode, array $response): string
  {
    if (isset($response['message'])) {
      $message = strtolower($response['message']);

      if (str_contains($message, 'invalid account number')) {
        return 'Invalid account number. Please check and try again.';
      }
      if (str_contains($message, 'invalid bank code')) {
        return 'Invalid bank code. Please select a valid bank.';
      }
      if (str_contains($message, 'could not resolve account')) {
        return 'Unable to verify account. Please confirm the account number.';
      }
    }

    switch ($statusCode) {
      case 400:
        return 'Invalid request. Please check account details.';
      case 401:
        return 'Payment service authentication failed.';
      case 404:
        return 'Account not found. Please verify the account number.';
      case 429:
        return 'Too many requests. Please try again later.';
      default:
        return 'Unable to verify account. Please try again.';
    }
  }
}
