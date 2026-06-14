<?php

namespace App\Http\Controllers\Api\V1\Rider\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RiderDocumentController extends Controller
{
  /**
   * Upload profile picture
   */
  public function updateProfilePicture(Request $request)
  {
    $rider = $request->user();

    $request->validate([
      'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120'
    ]);

    $file = $request->file('image');

    // Delete old image if exists
    if ($rider->image_path && !str_contains($rider->image_path, 'default')) {
      Storage::disk('public')->delete($rider->image_path);
    }

    $filename = 'rider_profile_' . $rider->id . '_' . time() . '.' . $file->getClientOriginalExtension();
    $path = $file->storeAs('profile_images', $filename, 'public');
    $rider->image_path = $path;
    $rider->save();

    return response()->json([
      'success' => true,
      'message' => 'Profile picture updated successfully',
      'data' => [
        'image_path' => $path,
        'photo_url' => asset('storage/' . $path)
      ]
    ]);
  }

  /**
   * Upload driver license
   */
  public function updateDriverLicense(Request $request)
  {
    $rider = $request->user();

    $request->validate([
      'driver_license_image' => 'required|file|mimes:jpg,jpeg,png,pdf,webp|max:10240'
    ]);

    $file = $request->file('driver_license_image');

    if ($rider->driver_license_path) {
      Storage::disk('public')->delete($rider->driver_license_path);
    }

    $filename = 'driver_license_' . $rider->id . '_' . time() . '.' . $file->getClientOriginalExtension();
    $path = $file->storeAs('driver_licenses', $filename, 'public');
    $rider->driver_license_path = $path;
    $rider->save();

    return response()->json([
      'success' => true,
      'message' => 'Driver license updated successfully',
      'data' => [
        'driver_license_path' => $path,
        'driver_license_url' => asset('storage/' . $path)
      ]
    ]);
  }

  /**
   * Upload utility bill
   */
  public function updateUtilityBill(Request $request)
  {
    $rider = $request->user();

    $request->validate([
      'utility_bill' => 'required|file|mimes:jpg,jpeg,png,pdf,webp|max:10240'
    ]);

    $file = $request->file('utility_bill');

    if ($rider->proof_of_address_path) {
      Storage::disk('public')->delete($rider->proof_of_address_path);
    }

    $filename = 'utility_bill_' . $rider->id . '_' . time() . '.' . $file->getClientOriginalExtension();
    $path = $file->storeAs('utility_bills', $filename, 'public');
    $rider->proof_of_address_path = $path;
    $rider->save();

    return response()->json([
      'success' => true,
      'message' => 'Utility bill updated successfully',
      'data' => [
        'utility_bill_path' => $path,
        'utility_bill_url' => asset('storage/' . $path)
      ]
    ]);
  }
}
