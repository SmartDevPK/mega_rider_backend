<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\TipService;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\OrderResource;
use App\Exceptions\OrderException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class OrderController extends Controller
{
    /**
     * @var OrderService
     */
    protected OrderService $orderService;

    /**
     * @var PaymentService
     */
    protected PaymentService $paymentService;

    /**
     * @var TipService
     */
    protected TipService $tipService;

    /**
     * OrderController constructor.
     *
     * @param OrderService $orderService
     * @param PaymentService $paymentService
     * @param TipService $tipService
     */
    public function __construct(
        OrderService $orderService,
        PaymentService $paymentService,
        TipService $tipService
    ) {
        $this->orderService = $orderService;
        $this->paymentService = $paymentService;
        $this->tipService = $tipService;
    }

    /**
     * Display the specified order
     *
     * @param int $id
     * @return JsonResponse
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
     * Create a new order
     *
     * @param CreateOrderRequest $request
     * @return JsonResponse
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->create($request->validated());

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

            return $this->errorResponse('Failed to create order', 500);
        }
    }

    /**
     * Update an existing order
     *
     * @param UpdateOrderRequest $request
     * @param int|string $orderId
     * @return JsonResponse
     */
    public function update(UpdateOrderRequest $request, $orderId): JsonResponse
    {
        try {
            // First find the order to get its details
            $order = $this->findOrderByIdentifier($orderId);
            
            if (!$order) {
                return $this->errorResponse(
                    "Order not found with identifier: {$orderId}",
                    404
                );
            }

            // Update using the order's ID (primary key)
            $updatedOrder = $this->orderService->update(
                $order->id,
                $request->validated()
            );

            return $this->successResponse(
                new OrderResource($updatedOrder->load(['customer', 'driver'])),
                'Order updated successfully'
            );

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Order not found', 404);
        } catch (OrderException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Order update failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Failed to update order', 500);
        }
    }

    /**
     * Update order type
     *
     * @param Request $request
     * @param int|string $orderId
     * @return JsonResponse
     */
   public function updateOrderType(Request $request, $orderId)
{
    try {
        // Manual validation
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

        // Find order
        $order = Order::find($orderId);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => "Order #{$orderId} not found"
            ], 404);
        }

        // Update
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
     * Select vehicle for order
     *
     * @param Request $request
     * @param int|string $orderId
     * @return JsonResponse
     */
/**
 * Select vehicle for the order
 */
public function selectVehicle(Request $request, $orderId)
{
    try {
        $user = $request->user();

        // Log user data for debugging
        \Log::info('User data for vehicle selection:', [
            'user_id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'phoneNumber' => $user->phoneNumber,
            'is_verified' => $user->is_verified
        ]);

        // Validate vehicle type
        $validated = $request->validate([
            'vehicle_type' => 'required|string|in:motorcycle,car,truck,bike'
        ]);

        // Ensure user is verified
        if (!$user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before proceeding.'
            ], 403);
        }

        // SIMPLIFIED CHECK: Only check required fields that exist
        if (!$user->firstname || !$user->lastname || !$user->phoneNumber) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete your profile information (firstname, lastname, and phone number).'
            ], 403);
        }

        // Update vehicle type
        $order = Order::find($orderId);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => "Order with ID {$orderId} not found"
            ], 404);
        }

        // Check if user owns this order
        if ($order->customer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this order'
            ], 403);
        }

        // Update the vehicle type
        $order->vehicle_type = $validated['vehicle_type'];
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle type selected successfully',
            'order' => [
                'id' => $order->id,
                'order_id' => $order->order_id,
                'vehicle_type' => $order->vehicle_type
            ]
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        \Log::error('Vehicle selection failed:', [
            'error' => $e->getMessage(),
            'order_id' => $orderId
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to select vehicle: ' . $e->getMessage()
        ], 500);
    }
}


    /**
     * Get basic order details
     *
     * @param int|string $orderId
     * @return JsonResponse
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
     * Process payment for an order
     *
     * @param PaymentRequest $request
     * @param int|string $orderId
     * @return JsonResponse
     */
  public function processPayment(PaymentRequest $request, $orderId)
{
    try {
        // Log the request
        Log::info('Processing payment', [
            'order_id' => $orderId,
            'payment_method' => $request->payment_method,
            'user_id' => auth()->id()
        ]);

        // Process payment through service
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
     *
     * @param Request $request
     * @param int|string $orderId
     * @return JsonResponse
     */
   /**
 * Add tip to an order
 */
public function addTip(Request $request, $orderId)
{
    try {
        // Log the request
        Log::info('Adding tip to order', [
            'order_id' => $orderId,
            'request_data' => $request->all(),
            'user_id' => auth()->id()
        ]);

        // Validate the request
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'method' => 'sometimes|string|in:cash,card,paystack'
        ]);

        // First, check if order exists
        $order = Order::find($orderId);
        
        if (!$order) {
            // Try to find by order_id string if numeric fails
            if (!is_numeric($orderId)) {
                $order = Order::where('order_id', $orderId)->first();
            }
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => "Order with ID {$orderId} not found",
                    'debug' => [
                        'available_orders' => Order::select('id', 'order_id')->get()
                    ]
                ], 404);
            }
        }

        // Check if user owns this order
        if ($order->customer_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to add tip to this order'
            ], 403);
        }

        // Add tip using service
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

    /**
     * Cancel order
     *
     * @param Request $request
     * @param int|string $orderId
     * @return JsonResponse
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
     * Get customer order statistics
     *
     * @param Request $request
     * @return JsonResponse
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

    /**
     * Find order by identifier (ID or order number)
     *
     * @param int|string $identifier
     * @return Order|null
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
     * Check if user profile is complete
     *
     * @param $user
     * @return bool
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
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
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
     *
     * @param string $message
     * @param int $statusCode
     * @param mixed $errors
     * @return JsonResponse
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