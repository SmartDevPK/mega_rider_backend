<?php

namespace App\Services;

use App\Models\Order;
use App\Exceptions\OrderException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class OrderService
{
    /**
     * Updatable order statuses
     */
    protected const UPDATABLE_STATUSES = ['pending', 'confirmed', 'processing', 'draft'];
    
    /**
     * Cancellable order statuses
     */
    protected const CANCELLABLE_STATUSES = ['pending', 'confirmed', 'processing'];
    
    /**
     * Valid order types
     */
    protected const VALID_ORDER_TYPES = ['express', 'standard', 'scheduled'];
    
    /**
     * Valid vehicle types
     */
    protected const VALID_VEHICLE_TYPES = ['motorcycle', 'car', 'truck'];

    /**
     * Create a new order
     *
     * @param array $data
     * @return Order
     * @throws OrderException
     */
    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            try {
                $orderData = $this->prepareCreateData($data);
                $order = Order::create($orderData);

                $this->logOrderActivity('created', $order);
                
                return $order->fresh();
            } catch (\Exception $e) {
                Log::error('Order creation failed', [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
                throw new OrderException('Failed to create order: ' . $e->getMessage(), 0, $e);
            }
        });
    }

    /**
 * Update an existing order
 *
 * @param int|string $orderIdentifier
 * @param array $data
 * @return Order
 * @throws OrderException
 * @throws ModelNotFoundException
 */
public function update($orderIdentifier, array $data): Order
{
    try {
        // Log the incoming request
        Log::channel('daily')->info('ORDER UPDATE ATTEMPT', [
            'identifier' => $orderIdentifier,
            'identifier_type' => is_numeric($orderIdentifier) ? 'numeric' : 'string',
            'data' => $data,
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString()
        ]);

        // Find the order
        $order = $this->findOrFail($orderIdentifier);
        
        Log::info('ORDER FOUND', [
            'order_id' => $order->id,
            'order_number' => $order->order_id,
            'current_status' => $order->status,
            'payment_status' => $order->payment_status,
            'customer_id' => $order->customer_id
        ]);

        // Auto-fix empty status
        if (empty($order->status)) {
            Log::warning('ORDER HAS EMPTY STATUS - AUTO-FIXING', [
                'order_id' => $order->id
            ]);
            $order->status = 'pending';
            $order->save();
        }
        
        $this->validateOrderUpdatable($order);

        Log::info('ORDER VALIDATION PASSED', [
            'order_id' => $order->id
        ]);

        return DB::transaction(function () use ($order, $data) {
            Log::info('STARTING TRANSACTION', [
                'order_id' => $order->id
            ]);

            $updateData = $this->prepareUpdateData($order, $data);
            
            Log::info('UPDATE DATA PREPARED', [
                'order_id' => $order->id,
                'update_data' => $updateData
            ]);

            $order->update($updateData);

            Log::info('ORDER UPDATED IN DATABASE', [
                'order_id' => $order->id,
                'updated_fields' => array_keys($updateData)
            ]);

            $freshOrder = $order->fresh();
            
            Log::info('ORDER FRESH DATA RETRIEVED', [
                'order_id' => $freshOrder->id,
                'new_status' => $freshOrder->status
            ]);

            $this->logOrderActivity('updated', $freshOrder, array_keys($updateData));
            
            return $freshOrder;
        });
        
    } catch (ModelNotFoundException $e) {
        Log::error('ORDER NOT FOUND', [
            'identifier' => $orderIdentifier,
            'error' => $e->getMessage()
        ]);
        throw $e;
    } catch (OrderException $e) {
        Log::error('ORDER VALIDATION FAILED', [
            'identifier' => $orderIdentifier,
            'error' => $e->getMessage()
        ]);
        throw $e;
    } catch (\Exception $e) {
        Log::error('ORDER UPDATE FAILED - UNEXPECTED ERROR', [
            'identifier' => $orderIdentifier,
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'error_trace' => $e->getTraceAsString()
        ]);
        
        // Re-throw with more specific message for debugging
        throw new OrderException(
            'Failed to update order: ' . $e->getMessage(),
            0,
            $e
        );
    }
}

    /**
     * Update order type
     *
     * @param int|string $orderIdentifier
     * @param string $type
     * @return Order
     * @throws OrderException
     * @throws InvalidArgumentException
     */
    public function updateType($orderIdentifier, string $type): Order
    {
        if (!in_array($type, self::VALID_ORDER_TYPES)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid order type. Must be one of: %s',
                implode(', ', self::VALID_ORDER_TYPES)
            ));
        }

        $order = $this->findOrFail($orderIdentifier);
        $order->update(['order_type' => $type]);

        $this->logOrderActivity('type updated', $order, ['order_type' => $type]);
        
        return $order->fresh();
    }

    /**
     * Update vehicle type
     *
     * @param int|string $orderIdentifier
     * @param string $vehicleType
     * @return Order
     * @throws OrderException
     * @throws InvalidArgumentException
     */
    public function updateVehicleType($orderIdentifier, string $vehicleType): Order
    {
        if (!in_array($vehicleType, self::VALID_VEHICLE_TYPES)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid vehicle type. Must be one of: %s',
                implode(', ', self::VALID_VEHICLE_TYPES)
            ));
        }

        $order = $this->findOrFail($orderIdentifier);
        $order->update(['vehicle_type' => $vehicleType]);

        $this->logOrderActivity('vehicle type updated', $order, ['vehicle_type' => $vehicleType]);
        
        return $order->fresh();
    }

    /**
     * Cancel an order
     *
     * @param int|string $orderIdentifier
     * @param string|null $reason
     * @return Order
     * @throws OrderException
     */
    public function cancelOrder($orderIdentifier, ?string $reason = null): Order
    {
        $order = $this->findOrFail($orderIdentifier);

        if (!in_array($order->status, self::CANCELLABLE_STATUSES)) {
            throw new OrderException(
                sprintf('Order cannot be cancelled in "%s" status', $order->status)
            );
        }

        $order->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at' => now()
        ]);

        $this->logOrderActivity('cancelled', $order, ['reason' => $reason]);
        
        return $order->fresh();
    }

    /**
     * Get order basic details
     *
     * @param int|string $orderIdentifier
     * @return array
     * @throws OrderException
     */
    public function getBasicDetails($orderIdentifier): array
    {
        $order = $this->findOrFail($orderIdentifier);
        
        return $order->only([
            'id',
            'order_id',
            'pickup_address',
            'pickup_latitude',
            'pickup_longitude',
            'pickup_city',
            'pickup_state',
            'delivery_address',
            'delivery_latitude',
            'delivery_longitude',
            'dropoff_city',
            'dropoff_state',
            'sender_name',
            'sender_email',
            'sender_phone',
            'receiver_name',
            'receiver_email',
            'receiver_phone',
            'package_name',
            'package_image',
            'package_worth',
            'package_insurance',
            'insurance_fee',
            'vehicle_type',
            'status',
            'payment_status',
            'created_at'
        ]);
    }

    /**
     * Get order with relationships
     *
     * @param int|string $orderIdentifier
     * @param array $relations
     * @return Order|null
     */
    public function getOrderWithRelations($orderIdentifier, array $relations = ['customer', 'driver']): ?Order
    {
        $order = $this->findOrder($orderIdentifier);
        
        if ($order && !empty($relations)) {
            $order->load($relations);
        }
        
        return $order;
    }

    /**
     * Get customer order statistics
     *
     * @param int $customerId
     * @return array
     */
    public function getCustomerOrderStats(int $customerId): array
    {
        $orders = Order::where('customer_id', $customerId);
        $paidOrders = (clone $orders)->where('payment_status', 'paid');

        return [
            'total_orders' => $orders->count(),
            'pending_orders' => (clone $orders)->where('status', 'pending')->count(),
            'processing_orders' => (clone $orders)->whereIn('status', ['confirmed', 'processing'])->count(),
            'completed_orders' => (clone $orders)->where('status', 'completed')->count(),
            'cancelled_orders' => (clone $orders)->where('status', 'cancelled')->count(),
            'total_spent' => $paidOrders->sum('package_worth'),
            'total_insurance_fees' => $paidOrders->sum('insurance_fee'),
            'average_order_value' => $paidOrders->avg('package_worth') ?? 0,
            'recent_orders' => (clone $orders)->latest()->take(5)->get()
        ];
    }

    /**
     * Find order by various identifiers
     *
     * @param int|string $identifier
     * @return Order|null
     */
    private function findOrder($identifier): ?Order
    {
        // Try numeric ID
        if (is_numeric($identifier)) {
            $order = Order::find($identifier);
            if ($order) return $order;
        }

        // Try order_id string
        $order = Order::where('order_id', $identifier)->first();
        if ($order) return $order;

        // Try numeric string as ID
        if (is_string($identifier) && ctype_digit($identifier)) {
            return Order::find((int)$identifier);
        }

        return null;
    }

    /**
     * Find order or fail
     *
     * @param int|string $identifier
     * @return Order
     * @throws ModelNotFoundException
     */
    private function findOrFail($identifier): Order
    {
        $order = $this->findOrder($identifier);
        
        if (!$order) {
            throw (new ModelNotFoundException())->setModel(
                Order::class,
                $identifier
            );
        }
        
        return $order;
    }

    /**
     * Validate if order can be updated
     *
     * @param Order $order
     * @throws OrderException
     */
  private function validateOrderUpdatable(Order $order): void
{
    if ($order->payment_status !== 'pending') {
        throw new OrderException(
            sprintf('Order cannot be updated. Payment status is "%s"', $order->payment_status)
        );
    }

    // FIX: If status is empty, consider it updatable
    if (empty($order->status)) {
        Log::info('Order has empty status, allowing update', [
            'order_id' => $order->id,
            'order_number' => $order->order_id
        ]);
        return; // Allow update for orders with empty status
    }

    if (!in_array($order->status, self::UPDATABLE_STATUSES)) {
        throw new OrderException(
            sprintf('Order cannot be updated. Current status is "%s"', $order->status)
        );
    }
}

    /**
     * Prepare data for order creation
     *
     * @param array $data
     * @return array
     */
    private function prepareCreateData(array $data): array
    {
        $data['customer_id'] = Auth::id();
        $data['payment_status'] = 'pending';
        $data['status'] = $data['status'] ?? 'pending';
        
        if (empty($data['order_id'])) {
            $data['order_id'] = $this->generateOrderId();
        }

        if (request()->hasFile('package_image')) {
            $data['package_image'] = $this->uploadPackageImage(request()->file('package_image'));
        }

        if (!empty($data['package_insurance'])) {
            $data['insurance_fee'] = $this->calculateInsuranceFee($data['package_worth']);
        }

        return $data;
    }

    /**
     * Prepare data for order update
     *
     * @param Order $order
     * @param array $data
     * @return array
     */
    private function prepareUpdateData(Order $order, array $data): array
    {
        if (request()->hasFile('package_image')) {
            $data['package_image'] = $this->uploadPackageImage(request()->file('package_image'));
        }

        if (isset($data['package_worth']) || isset($data['package_insurance'])) {
            $data = $this->recalculateInsurance($order, $data);
        }

        // Remove fields that shouldn't be updated directly
        unset($data['id'], $data['order_id'], $data['customer_id'], $data['created_at']);

        return array_filter($data, fn($value) => !is_null($value));
    }

    /**
     * Upload package image
     *
     * @param UploadedFile $image
     * @return string
     * @throws OrderException
     */
    private function uploadPackageImage(UploadedFile $image): string
    {
        try {
            $path = $image->store('packages', 'public');
            
            if (!$path) {
                throw new OrderException('Failed to upload package image');
            }
            
            return $path;
        } catch (\Exception $e) {
            throw new OrderException('Image upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Recalculate insurance fee
     *
     * @param Order $order
     * @param array $data
     * @return array
     */
    private function recalculateInsurance(Order $order, array $data): array
    {
        $worth = $data['package_worth'] ?? $order->package_worth;
        $insurance = $data['package_insurance'] ?? $order->package_insurance;

        $data['insurance_fee'] = $insurance 
            ? $this->calculateInsuranceFee($worth)
            : null;

        return $data;
    }

    /**
     * Calculate insurance fee
     *
     * @param float $worth
     * @return float
     */
    private function calculateInsuranceFee(float $worth): float
    {
        return max(100.00, round($worth * 0.02, 2));
    }

    /**
     * Generate unique order ID
     *
     * @return string
     */
    private function generateOrderId(): string
    {
        do {
            $orderId = sprintf(
                'ORD-%s-%s',
                now()->format('Ymd'),
                strtoupper(substr(uniqid(), -6))
            );
        } while (Order::where('order_id', $orderId)->exists());

        return $orderId;
    }

    /**
     * Log order activity
     *
     * @param string $action
     * @param Order $order
     * @param array $additionalData
     * @return void
     */
    private function logOrderActivity(string $action, Order $order, array $additionalData = []): void
    {
        Log::info("Order {$action}", array_merge([
            'order_id' => $order->id,
            'order_number' => $order->order_id,
            'customer_id' => $order->customer_id,
            'user_id' => Auth::id()
        ], $additionalData));
    }
}