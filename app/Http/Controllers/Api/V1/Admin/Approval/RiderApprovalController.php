<?php
// app/Http/Controllers/Admin/RiderApprovalController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Models\Admin;
use App\Enums\RiderStatus;
use App\Mail\RiderApprovedMail;
use App\Mail\RiderRejectedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RiderApprovalController extends Controller
{
    // ... other methods remain the same ...

    /**
     * Approve a rider
     */
    public function approve(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            
            $rider = Rider::findOrFail($id);
            $admin = $request->user();
            
            // Check if rider is pending
            if (!$rider->isPending()) {
                return response()->json([
                    'status' => false,
                    'message' => 'This rider has already been processed',
                    'current_status' => $rider->status->value
                ], 400);
            }
            
            // Approve the rider
            $rider->approve($admin->id, $request->admin_notes);
            
            DB::commit();
            
            // Send approval email with password setup link
            try {
                Mail::to($rider->email)->send(new RiderApprovedMail($rider));
                Log::info('Approval email sent to rider', ['rider_email' => $rider->email]);
            } catch (\Exception $e) {
                Log::error('Failed to send approval email', [
                    'rider_email' => $rider->email,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the approval if email fails
            }
            
            // Log the action
            Log::info('Rider approved', [
                'rider_id' => $rider->id,
                'rider_email' => $rider->email,
                'approved_by' => $admin->id,
                'approved_by_email' => $admin->email
            ]);
            
            return response()->json([
                'status' => true,
                'message' => 'Rider approved successfully. An email has been sent with password setup instructions.',
                'data' => [
                    'rider_id' => $rider->id,
                    'status' => $rider->status->value,
                    'approved_at' => $rider->approved_at,
                    'approved_by' => $admin->name
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to approve rider', [
                'rider_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to approve rider',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Reject a rider
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:10|max:500'
        ]);
        
        try {
            DB::beginTransaction();
            
            $rider = Rider::findOrFail($id);
            $admin = $request->user();
            
            // Check if rider is pending
            if (!$rider->isPending()) {
                return response()->json([
                    'status' => false,
                    'message' => 'This rider has already been processed',
                    'current_status' => $rider->status->value
                ], 400);
            }
            
            // Reject the rider
            $rider->reject($admin->id, $request->rejection_reason);
            
            DB::commit();
            
            // Send rejection email
            try {
                Mail::to($rider->email)->send(new RiderRejectedMail($rider, $request->rejection_reason));
                Log::info('Rejection email sent to rider', ['rider_email' => $rider->email]);
            } catch (\Exception $e) {
                Log::error('Failed to send rejection email', [
                    'rider_email' => $rider->email,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the rejection if email fails
            }
            
            // Log the action
            Log::info('Rider rejected', [
                'rider_id' => $rider->id,
                'rider_email' => $rider->email,
                'rejected_by' => $admin->id,
                'reason' => $request->rejection_reason
            ]);
            
            return response()->json([
                'status' => true,
                'message' => 'Rider rejected successfully. An email notification has been sent.',
                'data' => [
                    'rider_id' => $rider->id,
                    'status' => $rider->status->value,
                    'rejection_reason' => $rider->rejection_reason,
                    'rejected_by' => $admin->name
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to reject rider', [
                'rider_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to reject rider',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk approve riders
     */
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'rider_ids' => 'required|array|min:1|max:50', // Limit to 50 per batch
            'rider_ids.*' => 'exists:riders,id'
        ]);
        
        try {
            DB::beginTransaction();
            
            $admin = $request->user();
            $count = 0;
            $emailSent = 0;
            
            foreach ($request->rider_ids as $riderId) {
                $rider = Rider::find($riderId);
                if ($rider && $rider->isPending()) {
                    $rider->approve($admin->id);
                    $count++;
                    
                    // Send approval email
                    try {
                        Mail::to($rider->email)->send(new RiderApprovedMail($rider));
                        $emailSent++;
                    } catch (\Exception $e) {
                        Log::error('Failed to send bulk approval email', [
                            'rider_email' => $rider->email,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            DB::commit();
            
            return response()->json([
                'status' => true,
                'message' => "{$count} rider(s) approved successfully. {$emailSent} notification(s) sent.",
                'data' => [
                    'approved_count' => $count,
                    'emails_sent' => $emailSent
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to bulk approve riders',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // Add these methods to your RiderApprovalController.php

/**
 * Get statistics
 */
public function statistics()
{
    $stats = [
        'total_pending' => Rider::where('status', RiderStatus::PENDING)->count(),
        'total_approved' => Rider::where('status', RiderStatus::APPROVED)->count(),
        'total_rejected' => Rider::where('status', RiderStatus::REJECTED)->count(),
        'approval_rate' => $this->calculateApprovalRate(),
        'monthly_trends' => $this->getMonthlyTrends(),
    ];
    
    return response()->json([
        'status' => true,
        'data' => $stats
    ]);
}

/**
 * Export pending riders
 */
public function exportPending()
{
    $riders = Rider::where('status', RiderStatus::PENDING)
        ->select('first_name', 'last_name', 'email', 'phone', 'created_at')
        ->get();
    
    $filename = "pending_riders_" . date('Y-m-d_H-i-s') . ".csv";
    $handle = fopen('php://temp', 'w+');
    
    // Add CSV headers
    fputcsv($handle, ['First Name', 'Last Name', 'Email', 'Phone', 'Registration Date']);
    
    // Add data
    foreach ($riders as $rider) {
        fputcsv($handle, [
            $rider->first_name,
            $rider->last_name,
            $rider->email,
            $rider->phone,
            $rider->created_at->format('Y-m-d H:i:s')
        ]);
    }
    
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);
    
    return response($csv, 200)
        ->header('Content-Type', 'text/csv')
        ->header('Content-Disposition', "attachment; filename={$filename}");
}

/**
 * Delete a rider
 */
public function deleteRider($id)
{
    try {
        $rider = Rider::findOrFail($id);
        
        // Optional: Check if rider can be deleted (e.g., only if pending)
        if (!$rider->isPending()) {
            return response()->json([
                'status' => false,
                'message' => 'Only pending riders can be deleted'
            ], 422);
        }
        
        // Delete associated files if any
        if ($rider->image_path) {
            \Storage::disk('public')->delete($rider->image_path);
        }
        if ($rider->proof_of_address_path) {
            \Storage::disk('public')->delete($rider->proof_of_address_path);
        }
        if ($rider->driver_license_path) {
            \Storage::disk('public')->delete($rider->driver_license_path);
        }
        
        $rider->delete();
        
        return response()->json([
            'status' => true,
            'message' => 'Rider deleted successfully'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to delete rider',
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Calculate approval rate (Helper method)
 */
private function calculateApprovalRate()
{
    $total = Rider::count();
    if ($total == 0) return 0;
    
    $approved = Rider::where('status', RiderStatus::APPROVED)->count();
    return round(($approved / $total) * 100, 2);
}

/**
 * Get monthly trends (Helper method)
 */
private function getMonthlyTrends()
{
    return Rider::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved'),
            DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected')
        )
        ->groupBy('month')
        ->orderBy('month', 'desc')
        ->limit(6)
        ->get();
}
}