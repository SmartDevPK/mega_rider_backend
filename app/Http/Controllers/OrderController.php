<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Vehicle;
use App\Models\Package;
use App\Models\User;
use App\Services\OrderService;
use App\Services\RiderService;  
use App\Services\GoogleMapsService;
use App\Services\PaymentService;
use App\Services\TipService;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\VehicleRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\OrderResource;
use App\Exceptions\OrderException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class OrderController extends Controller
{
    protected OrderService $orderService;
    protected PaymentService $paymentService;
    protected TipService $tipService;
    protected RiderService $riderService;

    public function __construct(
        OrderService $orderService,
        PaymentService $paymentService,
        TipService $tipService,
        RiderService $riderService
    ) {
        $this->orderService = $orderService;
        $this->paymentService = $paymentService;
        $this->tipService = $tipService;
        $this->riderService = $riderService;
    }

    /**
 * Display a listing of the user's orders
 */
public function index(Request $request): JsonResponse
{
    try {
        $user = $request->user();
        
        // Get orders for the authenticated user
        $orders = Order::where('customer_id', $user->id)
            ->orWhere('driver_id', $user->id)
            ->with(['customer', 'driver'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $orders,
            'message' => 'Orders retrieved successfully'
        ], 200);
        
    } catch (\Exception $e) {
        Log::error('Failed to retrieve orders', [
            'user_id' => $request->user()->id ?? null,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve orders',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

    // ========================================
    // Order Management
    // ========================================

    /**
     * Display the specified order
     */
    public function show(int $id): JsonResponse
    {
        try {
            $order = Order::with(['customer', 'driver'])->find($id);

            if (!$order) {
                return $this->errorResponse('Order not found', 404);
            }

            return $this->successResponse(
                new OrderResource($order),
                'Order retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve order', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to retrieve order', 500);
        }
    }

    /**
     * Create a new order - Uses DTO from CreateOrderRequest
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            $orderData = $request->getDTO();
            $order = $this->orderService->create($orderData);

            return $this->successResponse(
                new OrderResource($order->load('customer')),
                'Order created successfully',
                201
            );

        } catch (OrderException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Failed to create order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new ride order with delivery fees
     */
    public function createOrder(Request $request): JsonResponse
    {
        try {
            $data = $request->all();

            // Create the order
            $order = $this->orderService->create($data);

            // Calculate delivery fees based on vehicle type & distance
            $pickupLat = $order->pickup_latitude;
            $pickupLng = $order->pickup_longitude;
            $dropoffLat = $order->delivery_latitude;
            $dropoffLng = $order->delivery_longitude;
            $vehicleType = $order->vehicle_type;

            $deliveryFees = $this->riderService->calculateDeliveryFee(
                $pickupLat, $pickupLng, $dropoffLat, $dropoffLng, $vehicleType
            );

            return response()->json([
                'success' => true,
                'order_id' => $order->order_id,
                'delivery_fees' => $deliveryFees,
                'status' => 'success'
            ]);

        } catch (OrderException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Create order failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to create order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing order
     */
    public function update(UpdateOrderRequest $request, $orderId): JsonResponse
    {
        try {
            // Find order
            $order = $this->findOrderByIdentifier($orderId);

            if (!$order) {
                return $this->errorResponse(
                    "Order not found with identifier: {$orderId}",
                    404
                );
            }

            // Get validated & cleaned data
            $data = $request->validated();

            // Extra safety: ensure not empty
            if (empty($data)) {
                return $this->errorResponse(
                    'No valid fields provided for update',
                    422
                );
            }

            // Update via service
            $updatedOrder = $this->orderService->update($order->id, $data);

            // Load relationships
            $updatedOrder->load(['customer', 'driver']);

            return $this->successResponse(
                new OrderResource($updatedOrder),
                'Order updated successfully'
            );

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Order not found', 404);

        } catch (OrderException | InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);

        } catch (\Throwable $e) {
            Log::error('Order update failed', [
                'order_id' => $orderId,
                'payload' => $request->all(), 
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to update order', 500);
        }
    }

    /**
     * Update order type
     */
    public function updateOrderType(Request $request, $orderId): JsonResponse
    {
        try {
            $requestData = $request->json()->all();
            
            if (!isset($requestData['order_type'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'order_type field is required'
                ], 422);
            }

            $orderType = $requestData['order_type'];
            $validTypes = ['express', 'standard', 'scheduled'];
            
            if (!in_array($orderType, $validTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'order_type must be one of: ' . implode(', ', $validTypes)
                ], 422);
            }

            $order = Order::find($orderId);
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => "Order #{$orderId} not found"
                ], 404);
            }

            $order->order_type = $orderType;
            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Order type updated',
                'data' => [
                    'id' => $order->id,
                    'order_type' => $order->order_type
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel order
     */
    public function cancelOrder(Request $request, $orderId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'sometimes|string|max:255'
            ]);

            $order = $this->orderService->cancelOrder(
                $orderId,
                $validated['reason'] ?? null
            );

            return $this->successResponse(
                new OrderResource($order),
                'Order cancelled successfully'
            );

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Order not found', 404);
        } catch (OrderException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Order cancellation failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to cancel order', 500);
        }
    }

    /**
     * Get basic order details
     */
    public function getBasicDetails($orderId): JsonResponse
    {
        try {
            $orderDetails = $this->orderService->getBasicDetails($orderId);

            return $this->successResponse(
                $orderDetails,
                'Order details retrieved successfully'
            );

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Order not found', 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve order details', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to retrieve order details', 500);
        }
    }

    /**
     * Get customer order statistics
     */
    public function getCustomerStats(Request $request): JsonResponse
    {
        try {
            $stats = $this->orderService->getCustomerOrderStats($request->user()->id);

            return $this->successResponse($stats, 'Order statistics retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to retrieve order stats', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to retrieve order statistics', 500);
        }
    }

    // ========================================
    // Vehicle Management
    // ========================================

  

    // ========================================
    // Driver Management
    // ========================================

    /**
     * Get driver information for an order
     */
    public function getDriverInfo($orderId): JsonResponse
    {
        try {
            $order = Order::with('driver')->findOrFail($orderId);
            
            // Verify ownership
            if ($order->customer_id !== auth()->id() && $order->driver_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            
            if (!$order->driver) {
                return response()->json([
                    'success' => false,
                    'message' => 'No driver assigned yet'
                ], 404);
            }
            
            $driver = $order->driver;
            
            return response()->json([
                'success' => true,
                'driver' => $this->prepareDriverInfo($driver, $order),
                'estimated_arrival' => $this->calculateEstimatedArrival($driver, $order)
            ]);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get driver info', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve driver information'
            ], 500);
        }
    }

    /**
     * Get driver real-time location
     */
    public function getDriverLocation($orderId): JsonResponse
    {
        try {
            $order = Order::findOrFail($orderId);
            
            // Verify ownership
            if ($order->customer_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            
            $location = Cache::get("driver_location_{$order->driver_id}");
            
            if (!$location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Driver location not available'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'location' => $location,
                'last_update' => $location['updated_at'] ?? null
            ]);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get driver location', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve driver location'
            ], 500);
        }
    }

    // ========================================
    // Payment & Tip Management
    // ========================================

    /**
     * Process payment for an order
     */
    public function processPayment(PaymentRequest $request, $orderId): JsonResponse
    {
        try {
            Log::info('Processing payment', [
                'order_id' => $orderId,
                'payment_method' => $request->payment_method,
                'user_id' => auth()->id()
            ]);

            $order = $this->paymentService->process($orderId, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_id,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'payment_reference' => $order->payment_reference,
                    'amount' => $order->package_worth
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Add tip to order
     */
    public function addTip(Request $request, $orderId): JsonResponse
    {
        try {
            Log::info('Adding tip to order', [
                'order_id' => $orderId,
                'request_data' => $request->all(),
                'user_id' => auth()->id()
            ]);

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0',
                'method' => 'sometimes|string|in:cash,card,paystack'
            ]);

            $order = Order::find($orderId);
            
            if (!$order) {
                if (!is_numeric($orderId)) {
                    $order = Order::where('order_id', $orderId)->first();
                }
                
                if (!$order) {
                    return response()->json([
                        'success' => false,
                        'message' => "Order with ID {$orderId} not found"
                    ], 404);
                }
            }

            if ($order->customer_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to add tip to this order'
                ], 403);
            }

            $tip = $this->tipService->create($order->id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Tip added successfully',
                'data' => $tip
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to add tip', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add tip: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========================================
    // Ride Calculations
    // ========================================

    /**
     * Calculate ride cost
     */
    public function calculateRide(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'pickup_lat'  => 'required|numeric',
                'pickup_lng'  => 'required|numeric',
                'dropoff_lat' => 'required|numeric',
                'dropoff_lng' => 'required|numeric',
            ]);

            $pickupLat  = $validated['pickup_lat'];
            $pickupLng  = $validated['pickup_lng'];
            $dropoffLat = $validated['dropoff_lat'];
            $dropoffLng = $validated['dropoff_lng'];

            $result = GoogleMapsService::getDistanceAndDuration($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

            $distance = $result['distance'];
            $duration = $result['duration'];

            $pricing = [
                'bike' => [
                    'base_fare'   => env('BIKE_BASE_FARE', 1000),
                    'rate_per_km' => env('BIKE_RATE_PER_KM', 100),
                    'rate_per_min'=> env('BIKE_RATE_PER_MIN', 5),
                ],
                'car' => [
                    'base_fare'   => env('CAR_BASE_FARE', 1500),
                    'rate_per_km' => env('CAR_RATE_PER_KM', 200),
                    'rate_per_min'=> env('CAR_RATE_PER_MIN', 10),
                ],
            ];

            $bikePrice = $pricing['bike']['base_fare'] + 
                        ($distance * $pricing['bike']['rate_per_km']) + 
                        ($duration * $pricing['bike']['rate_per_min']);

            $carPrice = $pricing['car']['base_fare'] + 
                       ($distance * $pricing['car']['rate_per_km']) + 
                       ($duration * $pricing['car']['rate_per_min']);

            return response()->json([
                'success' => true,
                'distance_km'        => round($distance, 2),
                'estimated_time_min' => round($duration),
                'prices' => [
                    'bike' => round($bikePrice, 2),
                    'car'  => round($carPrice, 2),
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Ride calculation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate ride: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find nearby riders
     */
    public function nearbyRiders(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lat' => 'required|numeric',
                'lng' => 'required|numeric',
                'radius' => 'sometimes|numeric|min:1|max:50'
            ]);

            $lat = $validated['lat'];
            $lng = $validated['lng'];
            $radius = $validated['radius'] ?? 5;

            $riders = $this->riderService->findNearbyRiders($lat, $lng, $radius);

            return response()->json([
                'success' => true,
                'count' => $riders->count(),
                'riders' => $riders
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to find nearby riders', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to find nearby riders: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========================================
    // Package Management
    // ========================================

    /**
     * Store package image
     */
    public function storeImage(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'package_image' => 'required|image|max:2048',
                'package_name' => 'required|string',
                'package_worth' => 'required|numeric',
            ]);

            if ($request->hasFile('package_image')) {
                $file = $request->file('package_image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('packages', $filename, 'public');

                $validated['package_image'] = $path;
            }

            $package = Package::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Package created successfully',
                'package' => $package
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Package creation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create package: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========================================
    // Security & Emergency
    // ========================================

    /**
     * Emergency alert
     */
    public function emergencyAlert($orderId): JsonResponse
    {
        try {
            $order = Order::findOrFail($orderId);
            
            // Verify ownership
            if ($order->customer_id !== auth()->id() && $order->driver_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            
            // Update order with emergency flag
            $order->update([
                'emergency_triggered' => true,
                'emergency_time' => now(),
                'emergency_notes' => 'Emergency SOS triggered by ' . auth()->user()->firstname
            ]);
            
            // Log emergency
            Log::warning('EMERGENCY ALERT', [
                'order_id' => $orderId,
                'user_id' => auth()->id(),
                'user_role' => auth()->user()->role,
                'pickup_location' => [
                    'address' => $order->pickup_address,
                    'lat' => $order->pickup_latitude,
                    'lng' => $order->pickup_longitude
                ],
                'timestamp' => now()
            ]);
            
            // TODO: Send SMS to emergency contacts
            // TODO: Notify security team
            // TODO: Call API to alert nearby authorities
            
            return response()->json([
                'success' => true,
                'message' => 'Emergency alert sent. Help is on the way!',
                'instructions' => 'Please stay in a safe location. Security has been notified.'
            ]);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Emergency alert failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send emergency alert'
            ], 500);
        }
    }

    // ========================================
    // Private Helper Methods
    // ========================================

    /**
     * Find order by identifier (ID or order number)
     */
    private function findOrderByIdentifier($identifier): ?Order
    {
        if (is_numeric($identifier)) {
            $order = Order::find($identifier);
            if ($order) return $order;
        }

        return Order::where('order_id', $identifier)->first();
    }

    /**
     * Notify driver about new assignment
     */
    private function notifyDriverAssignment(User $driver, Order $order): void
    {
        try {
            // Create notification in database
            DB::table('notifications')->insert([
                'user_id' => $driver->id,
                'type' => 'new_assignment',
                'title' => 'New Delivery Assignment',
                'message' => "You've been assigned to a new delivery from {$order->pickup_address} to {$order->delivery_address}",
                'order_id' => $order->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify driver', [
                'driver_id' => $driver->id,
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify customer about driver assignment
     */
    private function notifyCustomerAssignment(User $customer, Order $order, User $driver): void
    {
        try {
            // Create notification for customer
            DB::table('notifications')->insert([
                'user_id' => $customer->id,
                'type' => 'driver_assigned',
                'title' => 'Driver Assigned',
                'message' => "Your driver {$driver->firstname} has been assigned to your order",
                'order_id' => $order->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify customer', [
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Prepare driver information for response
     */
    private function prepareDriverInfo(User $driver, Order $order): array
    {
        return [
            'id' => $driver->id,
            'name' => $driver->firstname . ' ' . $driver->lastname,
            'phone' => $driver->phoneNumber,
            'email' => $driver->email,
            'profile_image' => $driver->profile_image 
                ? asset('storage/' . $driver->profile_image) 
                : ($driver->profile_picture ? asset('storage/' . $driver->profile_picture) : null),
            'rating' => $driver->rating,
            'total_trips' => $driver->total_trips,
            'vehicle' => [
                'type' => $order->vehicle_type,
                'make' => $order->make,
                'model' => $order->model,
                'color' => $order->color,
                'license_plate' => $order->license_plate,
                'year' => $order->year,
            ]
        ];
    }

    /**
     * Calculate estimated arrival time
     */
    private function calculateEstimatedArrival(User $driver, Order $order): array
    {
        // This would integrate with Google Maps or similar service
        // For now, return mock data based on distance
        $baseTime = ceil(($order->distance_km ?? 5) * 2); // 2 minutes per km
        $estimatedMinutes = max(5, $baseTime);
        
        return [
            'minutes' => $estimatedMinutes,
            'human_readable' => $estimatedMinutes . ' ' . ($estimatedMinutes === 1 ? 'minute' : 'minutes'),
            'eta' => now()->addMinutes($estimatedMinutes)->format('H:i'),
            'distance_km' => $order->distance_km ?? 5
        ];
    }

    /**
     * Check if user profile is complete
     */
    private function isProfileComplete($user): bool
    {
        return !empty($user->firstname) &&
               !empty($user->lastname) &&
               !empty($user->phoneNumber) &&
               !empty($user->address);
    }

    /**
     * Success response helper
     */
    private function successResponse($data, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Error response helper
     */
    private function errorResponse(string $message, int $statusCode = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}