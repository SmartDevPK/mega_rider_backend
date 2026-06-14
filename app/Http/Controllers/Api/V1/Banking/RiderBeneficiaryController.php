<?php

namespace App\Http\Controllers\Api\V1\Rider\Banking;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RiderBeneficiaryController extends Controller
{
  /**
   * Get Wallet Beneficiaries
   * 
   * Retrieves all wallet beneficiaries associated with the authenticated rider.
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getWalletBeneficiaries(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $cacheKey = "rider_beneficiaries_{$rider->id}";
      $cacheTTL = 300; // 5 minutes

      if (Cache::has($cacheKey)) {
        return response()->json([
          'success' => true,
          'source' => 'cache',
          'data' => Cache::get($cacheKey),
          'message' => 'Beneficiaries retrieved successfully'
        ], 200);
      }

      if (!Schema::hasTable('rider_beneficiaries')) {
        return response()->json([
          'success' => true,
          'data' => [],
          'message' => 'No beneficiaries found'
        ], 200);
      }

      $beneficiaries = DB::table('rider_beneficiaries')
        ->where('rider_id', $rider->id)
        ->whereNull('deleted_at')
        ->orderBy('is_default', 'desc')
        ->orderBy('created_at', 'desc')
        ->get();

      if ($beneficiaries->isEmpty()) {
        return response()->json([
          'success' => true,
          'data' => [],
          'message' => 'No beneficiaries found'
        ], 200);
      }

      $transformedBeneficiaries = $beneficiaries->map(function ($beneficiary) {
        return [
          'id' => $beneficiary->id,
          'account_name' => $beneficiary->account_name,
          'account_number' => $this->maskAccountNumber($beneficiary->account_number),
          'full_account_number' => $beneficiary->account_number,
          'bank_code' => $beneficiary->bank_code,
          'bank_name' => $beneficiary->bank_name,
          'beneficiary_name' => $beneficiary->beneficiary_name ?? $beneficiary->account_name,
          'is_default' => (bool) ($beneficiary->is_default ?? false),
          'created_at' => $beneficiary->created_at,
          'updated_at' => $beneficiary->updated_at,
        ];
      });

      Cache::put($cacheKey, $transformedBeneficiaries, $cacheTTL);

      return response()->json([
        'success' => true,
        'source' => 'database',
        'data' => $transformedBeneficiaries,
        'message' => 'Beneficiaries retrieved successfully'
      ], 200);
    } catch (\Exception $e) {
      Log::error('Beneficiaries retrieval error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve wallet beneficiaries'
      ], 500);
    }
  }

  /**
   * Add Wallet Beneficiary
   * 
   * Adds a new bank account as a beneficiary for the authenticated rider.
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function addWalletBeneficiary(Request $request)
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
        'beneficiary_name' => 'nullable|string|max:255',
        'set_as_default' => 'boolean'
      ]);

      if (!Schema::hasTable('rider_beneficiaries')) {
        return response()->json([
          'success' => false,
          'message' => 'Beneficiary feature is not available'
        ], 500);
      }

      // Check if beneficiary already exists
      $existingBeneficiary = DB::table('rider_beneficiaries')
        ->where('rider_id', $rider->id)
        ->where('account_number', $request->account_number)
        ->where('bank_code', $request->bank_code)
        ->whereNull('deleted_at')
        ->first();

      if ($existingBeneficiary) {
        return response()->json([
          'success' => false,
          'message' => 'Beneficiary already exists',
          'data' => [
            'id' => $existingBeneficiary->id,
            'account_name' => $existingBeneficiary->account_name,
            'account_number' => $this->maskAccountNumber($existingBeneficiary->account_number),
            'bank_name' => $existingBeneficiary->bank_name,
          ]
        ], 409);
      }

      $setAsDefault = $request->input('set_as_default', false);

      if ($setAsDefault) {
        DB::table('rider_beneficiaries')
          ->where('rider_id', $rider->id)
          ->update(['is_default' => false]);
      }

      $beneficiaryId = DB::table('rider_beneficiaries')->insertGetId([
        'rider_id' => $rider->id,
        'account_name' => $request->account_name ?? 'Unknown',
        'account_number' => $request->account_number,
        'bank_code' => $request->bank_code,
        'bank_name' => $request->bank_name ?? 'Unknown Bank',
        'beneficiary_name' => $request->beneficiary_name ?? $request->account_name,
        'is_default' => $setAsDefault,
        'created_at' => now(),
        'updated_at' => now(),
      ]);

      Cache::forget("rider_beneficiaries_{$rider->id}");

      return response()->json([
        'success' => true,
        'data' => [
          'id' => $beneficiaryId,
          'account_number' => $this->maskAccountNumber($request->account_number),
          'is_default' => $setAsDefault,
        ],
        'message' => 'Beneficiary added successfully'
      ], 201);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      Log::error('Add beneficiary error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to add beneficiary'
      ], 500);
    }
  }

  /**
   * Delete Wallet Beneficiary
   * 
   * Deletes a beneficiary from the rider's wallet beneficiaries list.
   *
   * @param Request $request
   * @param int $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function deleteWalletBeneficiary(Request $request, $id)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      if (!Schema::hasTable('rider_beneficiaries')) {
        return response()->json([
          'success' => false,
          'message' => 'Beneficiary feature is not available'
        ], 500);
      }

      $beneficiary = DB::table('rider_beneficiaries')
        ->where('id', $id)
        ->where('rider_id', $rider->id)
        ->whereNull('deleted_at')
        ->first();

      if (!$beneficiary) {
        return response()->json([
          'success' => false,
          'message' => 'Beneficiary not found'
        ], 404);
      }

      $forceDelete = $request->input('force', false);

      if (Schema::hasColumn('rider_beneficiaries', 'deleted_at') && !$forceDelete) {
        DB::table('rider_beneficiaries')
          ->where('id', $id)
          ->update([
            'deleted_at' => now(),
            'updated_at' => now()
          ]);
        $message = 'Beneficiary moved to trash';
      } else {
        DB::table('rider_beneficiaries')
          ->where('id', $id)
          ->delete();
        $message = 'Beneficiary deleted permanently';
      }

      if ($beneficiary->is_default) {
        $newDefault = DB::table('rider_beneficiaries')
          ->where('rider_id', $rider->id)
          ->where('id', '!=', $id)
          ->whereNull('deleted_at')
          ->orderBy('created_at', 'asc')
          ->first();

        if ($newDefault) {
          DB::table('rider_beneficiaries')
            ->where('id', $newDefault->id)
            ->update(['is_default' => true]);
        }
      }

      Cache::forget("rider_beneficiaries_{$rider->id}");

      return response()->json([
        'success' => true,
        'message' => $message
      ], 200);
    } catch (\Exception $e) {
      Log::error('Delete beneficiary error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id,
        'beneficiary_id' => $id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to delete beneficiary'
      ], 500);
    }
  }

  /**
   * Set Default Beneficiary
   * 
   * Sets a beneficiary as the default withdrawal account.
   *
   * @param Request $request
   * @param int $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function setDefaultBeneficiary(Request $request, $id)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      if (!Schema::hasTable('rider_beneficiaries')) {
        return response()->json([
          'success' => false,
          'message' => 'Beneficiary feature is not available'
        ], 500);
      }

      $beneficiary = DB::table('rider_beneficiaries')
        ->where('id', $id)
        ->where('rider_id', $rider->id)
        ->whereNull('deleted_at')
        ->first();

      if (!$beneficiary) {
        return response()->json([
          'success' => false,
          'message' => 'Beneficiary not found'
        ], 404);
      }

      DB::table('rider_beneficiaries')
        ->where('rider_id', $rider->id)
        ->update(['is_default' => false]);

      DB::table('rider_beneficiaries')
        ->where('id', $id)
        ->update(['is_default' => true, 'updated_at' => now()]);

      Cache::forget("rider_beneficiaries_{$rider->id}");

      return response()->json([
        'success' => true,
        'data' => [
          'id' => $beneficiary->id,
          'account_name' => $beneficiary->account_name,
          'account_number' => $this->maskAccountNumber($beneficiary->account_number),
          'bank_name' => $beneficiary->bank_name,
        ],
        'message' => 'Default beneficiary set successfully'
      ], 200);
    } catch (\Exception $e) {
      Log::error('Set default beneficiary error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id,
        'beneficiary_id' => $id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to set default beneficiary'
      ], 500);
    }
  }

  /**
   * Mask account number for display
   *
   * @param string $accountNumber
   * @return string
   */
  private function maskAccountNumber(string $accountNumber): string
  {
    $length = strlen($accountNumber);
    if ($length <= 4) {
      return $accountNumber;
    }

    return str_repeat('*', $length - 4) . substr($accountNumber, -4);
  }
}
