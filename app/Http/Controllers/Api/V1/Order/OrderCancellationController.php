<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Requests\CancelOrderRequest;
use App\Models\Order;
use App\Services\OrderCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderCancellationController extends Controller
{
    public function __construct(protected OrderCancellationService $service) {}

    public function cancel(CancelOrderRequest $request): JsonResponse
    {
        $order = Order::where('order_id', $request->order_id)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'code'    => 'ORDER_NOT_FOUND'
            ], 404);
        }

        // Authorize using the policy
        if (Gate::denies('cancel', $order)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to cancel this order',
                'code'    => 'UNAUTHORIZED'
            ], 403);
        }

        try {
            $cancelled = $this->service->cancel($order, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'code'    => 'ORDER_CANCELLED',
                'data'    => [
                    'order_id'      => $cancelled->order_id,
                    'status'        => $cancelled->status,
                    'cancelled_at'  => $cancelled->cancelled_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code'    => 'CANCELLATION_FAILED'
            ], 400);
        }
    }
}
