<?php

namespace App\Http\Controllers\Api\V1\Rider\Banking;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RiderBankController extends Controller
{
  /**
   * Get Supported Banks
   * 
   * Retrieves a list of supported banks from Paystack API.
   * Results are cached for 24 hours.
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getBanks(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $cacheKey = 'paystack_banks_list';
      $cacheTTL = 86400; // 24 hours

      if (Cache::has($cacheKey)) {
        return response()->json([
          'success' => true,
          'source' => 'cache',
          'data' => Cache::get($cacheKey),
          'message' => 'Banks retrieved successfully'
        ], 200);
      }

      $banks = $this->fetchBanksFromPaystack();

      if (!$banks) {
        return response()->json([
          'success' => false,
          'message' => 'Unable to retrieve banks at this time'
        ], 500);
      }

      // Sort banks alphabetically by name
      usort($banks, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
      });

      Cache::put($cacheKey, $banks, $cacheTTL);

      return response()->json([
        'success' => true,
        'source' => 'api',
        'data' => $banks,
        'message' => 'Banks retrieved successfully'
      ], 200);
    } catch (\Exception $e) {
      Log::error('Banks retrieval error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve banks at this time'
      ], 500);
    }
  }

  /**
   * Get Banks by Country
   * 
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getBanksByCountry(Request $request)
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
        'country' => 'required|string|max:2'
      ]);

      $country = strtoupper($request->country);
      $banks = $this->getBanksFromCache();

      if (!$banks) {
        $banks = $this->fetchBanksFromPaystack();
        if (!$banks) {
          return response()->json([
            'success' => false,
            'message' => 'Unable to retrieve banks'
          ], 500);
        }
      }

      $filteredBanks = array_filter($banks, function ($bank) use ($country) {
        return strtoupper($bank['country']) === $country;
      });

      return response()->json([
        'success' => true,
        'data' => array_values($filteredBanks),
        'message' => 'Banks retrieved successfully for ' . $country
      ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      Log::error('Banks by country error: ' . $e->getMessage());

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve banks for specified country'
      ], 500);
    }
  }

  /**
   * Search Banks
   * 
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function searchBanks(Request $request)
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
        'query' => 'required|string|min:2'
      ]);

      $query = strtolower($request->query);
      $banks = $this->getBanksFromCache();

      if (!$banks) {
        $banks = $this->fetchBanksFromPaystack();
        if (!$banks) {
          return response()->json([
            'success' => false,
            'message' => 'Unable to retrieve banks'
          ], 500);
        }
      }

      $filteredBanks = array_filter($banks, function ($bank) use ($query) {
        return strpos(strtolower($bank['name']), $query) !== false
          || strpos($bank['code'], $query) !== false;
      });

      return response()->json([
        'success' => true,
        'data' => array_values($filteredBanks),
        'count' => count($filteredBanks),
        'message' => 'Search completed successfully'
      ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      Log::error('Bank search error: ' . $e->getMessage());

      return response()->json([
        'success' => false,
        'message' => 'Unable to search banks'
      ], 500);
    }
  }

  /**
   * Fetch banks from Paystack API
   *
   * @return array|null
   */
  private function fetchBanksFromPaystack(): ?array
  {
    $paystackSecretKey = config('services.paystack.secret_key');

    if (!$paystackSecretKey) {
      Log::error('Paystack secret key not configured');
      return null;
    }

    try {
      $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $paystackSecretKey,
        'Content-Type' => 'application/json',
      ])->get('https://api.paystack.co/bank');

      if (!$response->successful()) {
        Log::error('Paystack API error', [
          'status' => $response->status(),
          'body' => $response->body()
        ]);
        return null;
      }

      $responseData = $response->json();

      if (!$responseData['status'] || empty($responseData['data'])) {
        return [];
      }

      return array_map(function ($bank) {
        return [
          'name' => $bank['name'],
          'code' => $bank['code'],
          'country' => $bank['country'] ?? 'Nigeria',
          'currency' => $bank['currency'] ?? 'NGN',
        ];
      }, $responseData['data']);
    } catch (\Exception $e) {
      Log::error('Failed to fetch banks: ' . $e->getMessage());
      return null;
    }
  }

  /**
   * Get banks from cache
   *
   * @return array|null
   */
  private function getBanksFromCache(): ?array
  {
    $cacheKey = 'paystack_banks_list';
    return Cache::get($cacheKey);
  }
}
