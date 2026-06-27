<?php

namespace App\Http\Controllers\Api\V1\Rider\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RiderManagementController extends Controller
{
  /**
   * Get all riders with filtering (Admin only)
   */
  public function index(Request $request)
  {
    $query = Rider::query();

    // Apply filters
    if ($request->has('status')) {
      $query->where('status', $request->status);
    }

    if ($request->has('email_verified')) {
      $request->email_verified
        ? $query->whereNotNull('email_verified_at')
        : $query->whereNull('email_verified_at');
    }

    if ($request->has('search')) {
      $search = $request->search;
      $query->where(function ($q) use ($search) {
        $q->where('email', 'like', "%{$search}%")
          ->orWhere('first_name', 'like', "%{$search}%")
          ->orWhere('last_name', 'like', "%{$search}%")
          ->orWhere('phone_number', 'like', "%{$search}%");
      });
    }

    // Date range filter
    if ($request->has('from_date')) {
      $query->whereDate('created_at', '>=', $request->from_date);
    }

    if ($request->has('to_date')) {
      $query->whereDate('created_at', '<=', $request->to_date);
    }

    // Sort
    $sortField = $request->get('sort_by', 'created_at');
    $sortDirection = $request->get('sort_direction', 'desc');
    $allowedSortFields = ['id', 'first_name', 'last_name', 'email', 'status', 'created_at', 'approved_at'];

    if (in_array($sortField, $allowedSortFields)) {
      $query->orderBy($sortField, $sortDirection);
    }

    $riders = $query->paginate($request->get('per_page', 15));

    return response()->json([
      'status' => true,
      'data' => $riders
    ]);
  }

  /**
   * Show single rider details
   */
  public function show($id)
  {
    $rider = Rider::with('approver')->findOrFail($id);

    return response()->json([
      'status' => true,
      'data' => $rider
    ]);
  }

  /**
   * Update rider status
   */
  public function updateStatus(Request $request, $id)
  {
    $request->validate([
      'status' => 'required|in:pending,approved,rejected',
      'rejection_reason' => 'required_if:status,rejected|string|nullable'
    ]);

    $rider = Rider::findOrFail($id);

    if ($request->status === 'approved') {
      $rider->approve(auth()->id());
    } elseif ($request->status === 'rejected') {
      $rider->reject(auth()->id(), $request->rejection_reason);
    } else {
      $rider->status = $request->status;
      $rider->save();
    }

    return response()->json([
      'status' => true,
      'message' => "Rider {$request->status} successfully",
      'data' => $rider
    ]);
  }

  /**
   * Delete rider
   */
  public function destroy($id)
  {
    $rider = Rider::findOrFail($id);

    // Delete associated files
    foreach (['image_path', 'proof_of_address_path', 'driver_license_path'] as $pathField) {
      if ($rider->$pathField) {
        Storage::delete($rider->$pathField);
      }
    }

    $rider->delete();

    return response()->json([
      'status' => true,
      'message' => 'Rider deleted successfully'
    ]);
  }
}
