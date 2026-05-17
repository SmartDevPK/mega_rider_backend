<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Services\Rider\RiderRegistrationService;
use App\Services\Rider\RiderService;
use App\Http\Requests\RiderRegistrationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RiderController extends Controller
{
    protected RiderRegistrationService $registrationService;
    protected RiderService $riderService;

    public function __construct(
        RiderRegistrationService $registrationService,
        RiderService $riderService
    ) {
        $this->registrationService = $registrationService;
        $this->riderService = $riderService;
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC ROUTES
    |--------------------------------------------------------------------------
    */

    /**
     * Register a new rider
     */
    public function register(RiderRegistrationRequest $request)
    {
        try {
            $rider = $this->registrationService->register($request->validated());

            return response()->json([
                'status' => true,
                'message' => 'Registration successful. Await admin approval.',
                'data' => [
                    'rider_id' => $rider->id,
                    'email' => $rider->email,
                    'status' => $rider->status->value,
                    'status_label' => $rider->status->label()
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => false,
                'message' => 'Registration failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check rider status (approval, verification, password setup)
     */
    public function checkStatus(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);
        
        $rider = Rider::where('email', $request->email)->first();

        if (!$rider) {
            return response()->json([
                'status' => false,
                'message' => 'No rider found with this email'
            ], 404);
        }

        $response = [
            'status' => true,
            'data' => [
                'email' => $rider->email,
                'status' => $rider->status->value ?? $rider->status,
                'status_label' => ucfirst($rider->status->value ?? $rider->status),
                'email_verified' => $rider->hasVerifiedEmail(),
                'can_set_password' => $rider->canSetPassword(),
                'has_password' => !is_null($rider->password),
                'can_resend_otp' => $rider->isApproved() && !$rider->hasVerifiedEmail(),
                'can_send_verification' => $rider->isApproved() && !$rider->hasVerifiedEmail()
            ]
        ];

        // Add OTP status if email not verified
        if ($rider->isApproved() && !$rider->hasVerifiedEmail()) {
            $response['data']['otp_expired'] = $this->riderService->isOtpExpired($rider);
            $response['data']['otp_remaining_attempts'] = $this->riderService->getRemainingAttempts($rider);
        }

        // Add rejection reason if rejected
        if ($rider->isRejected() && $rider->rejection_reason) {
            $response['data']['rejection_reason'] = $rider->rejection_reason;
            $response['message'] = 'Your application has been rejected. Reason: ' . $rider->rejection_reason;
        } 
        elseif ($rider->isApproved()) {
            if (!$rider->hasVerifiedEmail()) {
                $response['message'] = 'Your account is approved. Please verify your email first.';
                $response['data']['next_step'] = 'verify_email';
            } elseif (is_null($rider->password)) {
                $response['message'] = 'Your account is approved and email verified. Please set your password.';
                $response['data']['next_step'] = 'set_password';
            } else {
                $response['message'] = 'Your account is active. You can now login.';
                $response['data']['next_step'] = 'login';
            }
        } 
        elseif ($rider->isPending()) {
            $response['message'] = 'Your application is pending admin approval.';
            $response['data']['next_step'] = 'wait_for_approval';
        }
        elseif ($rider->isRejected()) {
            $response['message'] = 'Your application has been rejected.';
            $response['data']['next_step'] = 'contact_support';
        }

        return response()->json($response);
    }

    /**
     * Set password for approved and email-verified rider
     */
    public function setPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:riders,email',
            'password' => 'required|min:8|confirmed',
            'password_confirmation' => 'required'
        ]);

        $rider = Rider::where('email', $request->email)->first();

        if (!$rider) {
            return response()->json([
                'status' => false,
                'message' => 'Rider not found'
            ], 404);
        }

        // Check if password is already set
        if (!is_null($rider->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Password already set. Please login instead.',
                'data' => [
                    'can_login' => true
                ]
            ], 400);
        }

        // Check if rider can set password (approved AND email verified)
        if (!$rider->canSetPassword()) {
            $message = 'Cannot set password. ';
            
            if (!$rider->isApproved()) {
                $message .= 'Your application is ' . ($rider->isPending() ? 'still pending approval.' : 'has been rejected.');
            } elseif (!$rider->hasVerifiedEmail()) {
                $message .= 'Please verify your email first.';
            }
            
            return response()->json([
                'status' => false,
                'message' => $message,
                'data' => [
                    'is_approved' => $rider->isApproved(),
                    'email_verified' => $rider->hasVerifiedEmail(),
                    'has_password' => !is_null($rider->password),
                    'next_step' => !$rider->hasVerifiedEmail() ? 'verify_email' : 'wait_for_approval'
                ]
            ], 403);
        }

        // Set password
        $rider->setPasswordAndActivate($request->password);

        return response()->json([
            'status' => true,
            'message' => 'Password set successfully. You can now login.',
            'data' => [
                'email' => $rider->email,
                'can_login' => true
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN ROUTES
    |--------------------------------------------------------------------------
    */

    /**
     * Get all riders with filtering (Admin only)
     */
    public function index(Request $request)
    {
        $query = Rider::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by verification status
        if ($request->has('email_verified')) {
            if ($request->email_verified) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        // Filter by email/name search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
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
        
        // Allow only safe sort fields
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
     * Show single rider details (Admin only)
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
     * Update rider status (Admin only)
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
            
            // Optionally send notification email to rider
            // Mail::to($rider->email)->send(new RiderApprovedMail($rider));
            
        } elseif ($request->status === 'rejected') {
            $rider->reject(auth()->id(), $request->rejection_reason);
            
            // Optionally send rejection email to rider
            // Mail::to($rider->email)->send(new RiderRejectedMail($rider, $request->rejection_reason));
            
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
     * Delete rider (Admin only)
     */
    public function destroy($id)
    {
        $rider = Rider::findOrFail($id);
        
        // Optional: Delete associated files from storage
        if ($rider->image_path) {
            \Storage::delete($rider->image_path);
        }
        if ($rider->proof_of_address_path) {
            \Storage::delete($rider->proof_of_address_path);
        }
        if ($rider->driver_license_path) {
            \Storage::delete($rider->driver_license_path);
        }
        
        $rider->delete();

        return response()->json([
            'status' => true,
            'message' => 'Rider deleted successfully'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PROTECTED RIDER ROUTES
    |--------------------------------------------------------------------------
    */

    /**
     * Get rider dashboard (Authenticated rider)
     */
    public function dashboard(Request $request)
    {
        $rider = $request->user();
        
        return response()->json([
            'status' => true,
            'data' => [
                'rider' => $rider,
                'statistics' => [
                    'total_deliveries' => 0, // Implement actual logic
                    'completed_deliveries' => 0,
                    'pending_deliveries' => 0,
                    'total_earnings' => 0,
                    'rating' => 0
                ],
                'recent_activities' => [] // Implement recent activities
            ]
        ]);
    }

    /**
     * Get rider profile (Authenticated rider)
     */
    public function profile(Request $request)
    {
        $rider = $request->user();
        
        return response()->json([
            'status' => true,
            'data' => $rider
        ]);
    }

    /**
     * Update rider profile (Authenticated rider)
     */
    public function updateProfile(Request $request)
    {
        $rider = $request->user();
        
        $request->validate([
            'phone_number' => 'sometimes|string|unique:riders,phone_number,' . $rider->id,
            'address' => 'sometimes|string|max:255',
            'vehicle_color' => 'sometimes|string|max:50',
            'vehicle_plate_number' => 'sometimes|string|max:20|unique:riders,vehicle_plate_number,' . $rider->id,
        ]);
        
        $rider->update($request->only([
            'phone_number', 'address', 'vehicle_color', 'vehicle_plate_number'
        ]));
        
        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'data' => $rider
        ]);
    }

/**
 * Update Rider Vehicle Details
 * 
 * Updates the authenticated rider's vehicle information.
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function updateVehicleDetails(Request $request)
{
    $rider = $request->user();

    if (!$rider) {
        return response()->json([
            'success' => false,
            'message' => 'Account does not exist'
        ], 404);
    }

    $validated = $request->validate([
        'vehicle_name' => 'required|string|max:255',
        'vehicle_color' => 'required|string|max:255',
        'vehicle_number_plate' => 'required|string|max:255|unique:riders,vehicle_plate_number,' . $rider->id,
    ]);

    // Update vehicle details
    $rider->vehicle_name = $validated['vehicle_name'];
    $rider->vehicle_color = $validated['vehicle_color'];
    $rider->vehicle_plate_number = $validated['vehicle_number_plate'];
    
    // REMOVE THIS LINE - it doesn't exist in your database
    // $rider->date_modified = now();
    
    // Laravel will automatically update the 'updated_at' timestamp
    // No need to manually set it unless you want to
    // $rider->updated_at = now();

    $rider->save();

    // Log the activity for audit trail (optional)
    Log::info('Rider vehicle details updated', [
        'rider_id' => $rider->id,
        'email' => $rider->email,
        'updated_fields' => array_keys($validated)
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Rider vehicle details updated successfully'
    ]);
}
/**
 * Update Rider Name
 * 
 * Updates the authenticated rider's first name and last name.
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function updateRiderName(Request $request)
{
    $rider = $request->user();

    if (!$rider) {
        return response()->json([
            'success' => false,
            'message' => 'Account does not exist'
        ], 404);
    }

    $validated = $request->validate([
        'firstname' => 'required|string|max:255',
        'lastname' => 'required|string|max:255',  // Changed from 'surname' to 'lastname'
    ]);

    // Update rider name fields
    $rider->first_name = $validated['firstname'];
    $rider->last_name = $validated['lastname'];  // Changed from 'surname' to 'last_name'
    
    $rider->save();

    Log::info('Rider name updated', [
        'rider_id' => $rider->id,
        'email' => $rider->email,
        'updated_fields' => array_keys($validated)
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Rider name updated successfully'
    ]);
}

/**
 * Upload Rider Profile Picture
 * 
 * Uploads and updates the authenticated rider's profile picture.
 * The uploaded image is stored securely and the filename is saved in the rider account.
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function updateRiderProfilePicture(Request $request)
{
    $rider = $request->user();

    if (!$rider) {
        return response()->json([
            'success' => false,
            'message' => 'Account does not exist'
        ], 404);
    }

    $request->validate([
        'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120' // 5MB max, optional
    ]);

    // Check if file was uploaded
    if (!$request->hasFile('image')) {
        return response()->json([
            'success' => false,
            'message' => 'No image file provided',
            'errors' => [
                'image' => ['Please select an image file to upload']
            ]
        ], 422);
    }

    $file = $request->file('image');

    // Delete old profile picture if exists
    if ($rider->image_path && !str_contains($rider->image_path, 'default')) {
        $oldFilePath = storage_path('app/public/' . $rider->image_path);
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
    }

    // Generate unique filename
    $filename = 'rider_profile_' . $rider->id . '_' . time() . '.' . $file->getClientOriginalExtension();
    
    // Store the file in 'profile_images' directory (matching your registration structure)
    $path = $file->storeAs('profile_images', $filename, 'public');
    
    // Update rider record - using 'image_path' as per your database
    $rider->image_path = $path; // Store the full path: 'profile_images/filename.jpg'
    $rider->save();

    // Generate full URL for response
    $imageUrl = asset('storage/' . $path);

    Log::info('Rider profile picture updated', [
        'rider_id' => $rider->id,
        'email' => $rider->email,
        'path' => $path,
        'filename' => $filename,
        'file_size' => $file->getSize()
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Profile picture updated successfully',
        'data' => [
            'image_path' => $path,
            'photo_url' => $imageUrl
        ]
    ]);
}

/**
 * Upload Rider Driver License
 * 
 * Uploads and updates the authenticated rider's driver license.
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function updateDriverLicense(Request $request)
{
    $rider = $request->user();

    if (!$rider) {
        return response()->json([
            'success' => false,
            'message' => 'Account does not exist'
        ], 404);
    }

    $request->validate([
        'driver_license_image' => 'required|file|mimes:jpg,jpeg,png,pdf,webp|max:10240' // 10MB max
    ]);

    $file = $request->file('driver_license_image');

    // Delete old driver license if exists
    if ($rider->driver_license_path) {
        $oldFilePath = storage_path('app/public/' . $rider->driver_license_path);
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
    }

    // Generate unique filename
    $filename = 'driver_license_' . $rider->id . '_' . time() . '.' . $file->getClientOriginalExtension();
    
    // Store the file in 'driver_licenses' directory
    $path = $file->storeAs('driver_licenses', $filename, 'public');
    
    // Update rider record
    $rider->driver_license_path = $path;
    $rider->save();

    // Generate full URL for response
    $fileUrl = asset('storage/' . $path);

    Log::info('Rider driver license updated', [
        'rider_id' => $rider->id,
        'email' => $rider->email,
        'path' => $path,
        'file_size' => $file->getSize()
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Driver license updated successfully',
        'data' => [
            'driver_license_path' => $path,
            'driver_license_url' => $fileUrl
        ]
    ]);
}
/**
 * Upload Rider Utility Bill
 * 
 * Uploads and updates the authenticated rider's utility bill (proof of address).
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function updateUtilityBill(Request $request)
{
    $rider = $request->user();

    if (!$rider) {
        return response()->json([
            'success' => false,
            'message' => 'Account does not exist'
        ], 404);
    }

    $request->validate([
        'utility_bill' => 'required|file|mimes:jpg,jpeg,png,pdf,webp|max:10240' // 10MB max
    ]);

    $file = $request->file('utility_bill');

    // Delete old utility bill if exists
    if ($rider->proof_of_address_path) {
        $oldFilePath = storage_path('app/public/' . $rider->proof_of_address_path);
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
    }

    // Generate unique filename
    $filename = 'utility_bill_' . $rider->id . '_' . time() . '.' . $file->getClientOriginalExtension();
    
    // Store the file in 'utility_bills' directory
    $path = $file->storeAs('utility_bills', $filename, 'public');
    
    // Update rider record
    $rider->proof_of_address_path = $path;
    $rider->save();

    // Generate full URL for response
    $fileUrl = asset('storage/' . $path);

    Log::info('Rider utility bill updated', [
        'rider_id' => $rider->id,
        'email' => $rider->email,
        'path' => $path,
        'file_size' => $file->getSize()
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Utility bill updated successfully',
        'data' => [
            'utility_bill_path' => $path,
            'utility_bill_url' => $fileUrl
        ]
    ]);
}
}