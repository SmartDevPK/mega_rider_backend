<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class VehicleController extends Controller
{
    /**
     * Store a new vehicle
     *
     * @param VehicleRequest $request
     * @return JsonResponse
     */
    public function storeVehicle(VehicleRequest $request): JsonResponse
    {
        try {
            // Get validated data
            $validatedData = $request->validated();
            
            // Handle insurance paid at
            if (isset($validatedData['insurance_paid']) && $validatedData['insurance_paid']) {
                $validatedData['insurance_paid_at'] = now();
            } else {
                $validatedData['insurance_paid_at'] = null;
                $validatedData['insurance_paid'] = false;
            }
            
            // Get driver details for denormalized fields
            $driver = User::find($validatedData['driver_id']);
            if ($driver) {
                $validatedData['driver_name'] = $driver->name ?? $driver->firstname . ' ' . ($driver->lastname ?? '');
                $validatedData['driver_phone'] = $driver->phone ?? $driver->phoneNumber ?? null;
                $validatedData['driver_image'] = $driver->profile_image ?? $driver->profile_picture ?? null;
            }
            
            // Create vehicle
            $vehicle = Vehicle::create($validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'Vehicle created successfully',
                'data' => $vehicle
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Vehicle creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create vehicle: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all vehicles
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $vehicles = Vehicle::with('driver')->get();
            
            return response()->json([
                'success' => true,
                'data' => $vehicles
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vehicles: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get a specific vehicle
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $vehicle = Vehicle::with('driver')->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $vehicle
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vehicle: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update a vehicle
     *
     * @param VehicleRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(VehicleRequest $request, $id): JsonResponse
    {
        try {
            $vehicle = Vehicle::findOrFail($id);
            
            $validatedData = $request->validated();
            
            // Handle insurance paid at
            if (isset($validatedData['insurance_paid']) && $validatedData['insurance_paid']) {
                $validatedData['insurance_paid_at'] = now();
            } else {
                $validatedData['insurance_paid_at'] = null;
                $validatedData['insurance_paid'] = false;
            }
            
            // Get driver details if driver_id changed
            if (isset($validatedData['driver_id']) && $validatedData['driver_id'] != $vehicle->driver_id) {
                $driver = User::find($validatedData['driver_id']);
                if ($driver) {
                    $validatedData['driver_name'] = $driver->name ?? $driver->firstname . ' ' . ($driver->lastname ?? '');
                    $validatedData['driver_phone'] = $driver->phone ?? $driver->phoneNumber ?? null;
                    $validatedData['driver_image'] = $driver->profile_image ?? $driver->profile_picture ?? null;
                }
            }
            
            $vehicle->update($validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'Vehicle updated successfully',
                'data' => $vehicle
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Vehicle update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a vehicle
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $vehicle = Vehicle::findOrFail($id);
            $vehicle->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Vehicle deleted successfully'
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Vehicle deletion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vehicle: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get vehicles by driver
     *
     * @param int $driverId
     * @return JsonResponse
     */
    public function getByDriver($driverId): JsonResponse
    {
        try {
            $vehicles = Vehicle::where('driver_id', $driverId)->get();
            
            return response()->json([
                'success' => true,
                'data' => $vehicles
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vehicles: ' . $e->getMessage()
            ], 500);
        }
    }
}