<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\UpdateOrderTypeRequest;
use App\Models\CustomerSurCharge;
use App\Models\OrderType;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    // ----------------------------------------------------
    // CREATE ORDER
    // ----------------------------------------------------
    public function store(StoreOrderRequest $request)
    {
        $order = $this->orderService->createOrder($request->validated(), auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => [
                'order_id' => $order->order_id,
                'status'   => $order->status,
            ],
        ], 201);
    }

    // ----------------------------------------------------
    // LIST ALL ORDERS FOR AUTHENTICATED USER
    // ----------------------------------------------------
    public function index()
    {
        $orders = Order::where('customer_id', auth()->id())
                       ->orderBy('created_at', 'desc')
                       ->get();

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    // ----------------------------------------------------
    // SHOW SINGLE ORDER
    // ----------------------------------------------------
    public function show(string $orderId)
    {
        $order = Order::where('order_id', $orderId)
                      ->where('customer_id', auth()->id())
                      ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $order,
        ]);
    }

    // ----------------------------------------------------
    // UPDATE ORDER
    // ----------------------------------------------------
    public function update(UpdateOrderRequest $request, string $orderId)
    {
        $order = Order::where('order_id', $orderId)
                      ->where('customer_id', auth()->id())
                      ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $updatedOrder = $this->orderService->updateOrder($order, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data'    => $updatedOrder,
        ]);
    }

    // ----------------------------------------------------
    // RECENT ORDER ACTIVITIES (FOR NOTIFICATIONS)
    // ----------------------------------------------------
    public function activities(Request $request)
    {
        $user = $request->user();
        $limit = (int) $request->input('limit', 10);
        $status = $request->input('status');
        $orderId = $request->input('order_id');
        $cursor = $request->input('cursor');

        $query = Order::where('customer_id', $user->id)
                      ->orderBy('created_at', 'desc');

        if ($status) $query->where('status', $status);
        if ($orderId) $query->where('order_id', $orderId);
        if ($cursor) {
            $decoded = json_decode(base64_decode($cursor), true);
            if (isset($decoded['id'])) $query->where('id', '<', $decoded['id']);
        }

        $orders = $query->take($limit + 1)->get();
        $hasMore = $orders->count() > $limit;
        if ($hasMore) {
            $nextCursor = base64_encode(json_encode(['id' => $orders->last()->id]));
            $orders = $orders->slice(0, $limit);
        } else {
            $nextCursor = null;
        }

        $groups = $orders->groupBy(function ($order) {
            $created = Carbon::parse($order->created_at);
            if ($created->isToday()) return 'Today';
            if ($created->isYesterday()) return 'Yesterday';
            return $created->format('Y-m-d');
        })->map(function ($items, $key) {
            return [
                'date' => $key,
                'orders' => $items->map(function ($order) {
                    return [
                        'order_id' => $order->order_id,
                        'status' => $order->status,
                        'price' => $order->price ?? 0,
                        'item_name' => $order->item_name ?? '',
                        'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => $groups->isEmpty() ? 'No activities found' : 'Activities fetched successfully',
            'code' => $groups->isEmpty() ? 'NO_ACTIVITIES' : 'ACTIVITIES_FETCHED',
            'data' => [
                'groups' => $groups,
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
            ],
        ]);
    }

    // ----------------------------------------------------
    // LIVE PACKAGES FOR CUSTOMER (CURSOR PAGINATION)
    // ----------------------------------------------------
    public function livePackages(Request $request)
    {
        $user = $request->user();
        $limit = (int) $request->input('limit', 10);
        if ($limit <= 0 || $limit > 50) {
            return response()->json([
                'success' => false,
                'message' => 'Limit must be between 1 and 50',
            ], 400);
        }

        $orderIdFilter = $request->input('order_id_filter');
        $statusFilter = $request->input('status_filter');
        $cursor = $request->input('cursor');

        $query = Order::where('customer_id', $user->id)->orderBy('id', 'desc');

        if ($orderIdFilter) $query->where('order_id', $orderIdFilter);
        if ($statusFilter) $query->where('status', $statusFilter);
        if ($cursor) {
            $decoded = json_decode(base64_decode($cursor), true);
            if (isset($decoded['id'])) $query->where('id', '<', $decoded['id']);
        }

        $orders = $query->take($limit + 1)->get();
        $hasMore = $orders->count() > $limit;
        if ($hasMore) {
            $nextCursor = base64_encode(json_encode(['id' => $orders->last()->id]));
            $orders = $orders->slice(0, $limit);
        } else {
            $nextCursor = null;
        }

        $packages = $orders->map(fn($order) => [
            'order_id' => $order->order_id,
            'status' => $order->status,
            'pickup_address' => $order->pickup_address,
            'dropoff_address' => $order->dropoff_address,
            'item_name' => $order->item_name,
            'package_image' => $order->package_image,
            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
        ]);

        return response()->json([
            'success' => true,
            'packages' => $packages,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ]);
    }

    // ----------------------------------------------------
    // UPDATE ORDER TYPE
    // ----------------------------------------------------
    public function updateOrderType(UpdateOrderTypeRequest $request)
    {
        $user = $request->user();

        if (!$user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Customer is not verified',
                'code' => 'CUSTOMER_NOT_VERIFIED'
            ], 400);
        }

        $orderId = $request->order_id;
        $orderTypeId = $request->order_type_id;

        try {
            $updatedOrder = DB::transaction(function () use ($orderId, $orderTypeId, $user) {
                $order = Order::where('order_id', $orderId)
                              ->where('customer_id', $user->id)
                              ->lockForUpdate()
                              ->first();

                if (!$order) {
                    throw ValidationException::withMessages([
                        'order_id' => ['Order not found.']
                    ]);
                }

                $terminalStates = ['paid', 'picked_up', 'delivered', 'cancelled'];
                if (in_array($order->status, $terminalStates)) {
                    throw ValidationException::withMessages([
                        'status' => ['Operation not allowed on this order.']
                    ]);
                }

                return $this->orderService->updateOrderType($order, $orderTypeId);
            });

            return response()->json([
                'success' => true,
                'message' => 'Order type updated successfully',
                'code' => 'ORDER_TYPE_UPDATED',
                'data' => $updatedOrder
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors(),
                'code' => 'VALIDATION_ERROR'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    // ----------------------------------------------------
    // GET ORDER TYPES WITH REAL-TIME PRICING
    // ----------------------------------------------------
    public function getOrderTypes(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string|exists:orders,order_id',
        ]);

        $user = $request->user();
        $order = Order::where('order_id', $request->order_id)
                      ->where('customer_id', $user->id)
                      ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'code'    => 'ORDER_NOT_FOUND',
            ], 404);
        }

        $distance = $order->distance ?? 5.0;
        $travelTime = $order->estimated_travel_time ?? 15;
        $zoneId = $order->zone_id ?? 1;
        $orderTypes = OrderType::all();
        $response = collect();

        foreach ($orderTypes as $type) {
            $cacheKey = "surge:zone:{$zoneId}";
            $surgeMultiplier = Cache::get($cacheKey);

            if (is_null($surgeMultiplier)) {
                $activeOrders = Order::whereIn('status', ['pending', 'searching'])
                                     ->where('zone_id', $zoneId)
                                     ->count();
                $availableRiders = \App\Models\User::where('role', 'rider')
                                                   ->where('is_available', true)
                                                   ->where('zone_id', $zoneId)
                                                   ->count();

                $ratio = $activeOrders / max($availableRiders, 1);

                $surgeMultiplier = match(true) {
                    $ratio <= 1    => 0.0,
                    $ratio <= 1.5  => 0.2,
                    $ratio <= 2    => 0.5,
                    $ratio <= 3    => 1.0,
                    default        => 1.5,
                };
                Cache::put($cacheKey, $surgeMultiplier, 30);
            }

            if ($distance <= $type->base_distance) {
                $deliveryFee = $type->base_price;
            } else {
                $extraKm = $distance - $type->base_distance;
                $deliveryFee = $type->base_price + ($extraKm * $type->price_per_km);
            }

            $surgeFee = $deliveryFee * $surgeMultiplier;
            $totalAmount = $deliveryFee + $surgeFee;

            if (class_exists(CustomerSurCharge::class)) {
                CustomerSurCharge::updateOrCreate(
                    ['order_id' => $order->id, 'order_type_id' => $type->id],
                    [
                        'delivery_fee'     => $deliveryFee,
                        'surge_multiplier' => $surgeMultiplier,
                        'surge_fee'        => $surgeFee,
                        'total_amount'     => $totalAmount,
                        'updated_at'       => now(),
                    ]
                );
            }

            $response->push([
                'order_type_id'   => $type->id,
                'order_type_name' => $type->title,
                'distance'        => round($distance, 2),
                'estimated_travel_time' => $travelTime,
                'pricing' => [
                    'delivery_fee'      => round($deliveryFee, 2),
                    'surge_multiplier'  => $surgeMultiplier,
                    'surge_fee'         => round($surgeFee, 2),
                    'total_amount'      => round($totalAmount, 2),
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order types retrieved successfully',
            'code'    => 'ORDER_TYPES_FETCHED',
            'data'    => $response,
        ]);
    }

    // ----------------------------------------------------
    // ORDER SUMMARY ENDPOINT
    // ----------------------------------------------------
    public function summary(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string|exists:orders,order_id',
        ]);

        $user = $request->user();
        $order = Order::where('order_id', $request->order_id)
                      ->where('customer_id', $user->id)
                      ->with('orderType')
                      ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'code'    => 'ORDER_NOT_FOUND',
            ], 404);
        }

        $pricing = $this->orderService->getOrderSummary($order);

        return response()->json([
            'success' => true,
            'message' => 'Order summary retrieved successfully',
            'code'    => 'ORDER_SUMMARY_FETCHED',
            'data'    => [
                'order_id' => $order->order_id,
                'pickup'   => [
                    'address' => $order->pickup_address,
                    'name'    => $order->sender_name,
                    'phone'   => $order->sender_phone,
                ],
                'dropoff'  => [
                    'address' => $order->dropoff_address,
                    'name'    => $order->receiver_name,
                    'phone'   => $order->receiver_phone,
                ],
                'pricing'  => $pricing,
            ],
        ]);
    }

    /**
     * Update special instructions for an existing order.
     *
     * POST /api/customer/orders/update-instructions
     * Auth: Bearer Token (Sanctum)
     */
  public function updateInstructions(Request $request)
{
    $request->validate([
        'order_id' => 'required|string|exists:orders,order_id',
        'instruction' => 'nullable|string|max:500',
    ]);

    $user = $request->user();

    $order = Order::where('order_id', $request->order_id)
                  ->where('customer_id', $user->id)
                  ->lockForUpdate()
                  ->first();

    if (!$order) {
        return response()->json([
            'success' => false,
            'message' => 'Order not found',
            'code' => 'ORDER_NOT_FOUND'
        ], 404);
    }

    $terminalStates = ['picked_up', 'delivered', 'cancelled'];
    if (in_array($order->status, $terminalStates)) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot update instructions at this stage',
            'code' => 'INVALID_ORDER_STATE'
        ], 400);
    }

    $order->special_instructions = $request->instruction;
    $order->date_modified = now();

    // Persist changes
    $order->save();

    return response()->json([
        'success' => true,
        'message' => 'Special instructions updated successfully',
        'code' => 'ORDER_INSTRUCTIONS_UPDATED',
        'data' => [
            'order_id' => $order->order_id,
            'instruction' => $order->special_instructions
        ]
    ]);
}

public function paymentBreakdown(Request $request)
{
    // Validate input
    $request->validate([
        'order_id' => 'required|string|exists:orders,order_id',
    ]);

    $user = $request->user();

    // Optional role check
    if ($user->role !== 'customer') {
        return response()->json([
            'success' => false,
            'code' => 'FORBIDDEN'
        ], 403);
    }

    // Fetch order
    $order = Order::where('order_id', $request->order_id)
                  ->where('customer_id', $user->id)
                  ->with('customer')
                  ->first();

    if (!$order) {
        return response()->json([
            'success' => false,
            'code' => 'ORDER_NOT_FOUND'
        ], 404);
    }

    try {
        // Delegate logic to service
        $breakdown = $this->orderService->getPaymentBreakdown($order);

        return response()->json([
            'success' => true,
            'message' => 'Payment breakdown fetched successfully',
            'data' => $breakdown
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'code' => 'SERVER_ERROR'
        ], 500);
    }
}




}