<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    /**
     * Authorization
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        if ($order) {
            return $this->user()->id === $order->customer_id
                || $this->user()->isAdmin();
        }

        return true;
    }

    /**
     * Validation Rules
     */
    public function rules(): array
    {
        return [

            // Pickup
            'pickup_address' => 'sometimes|string|max:255',
            'pickup_latitude' => 'sometimes|numeric|between:-90,90',
            'pickup_longitude' => 'sometimes|numeric|between:-180,180',
            'pickup_city' => 'sometimes|string|max:100',
            'pickup_state' => 'sometimes|string|max:100',
            'pickup_zip_code' => 'sometimes|string|max:20',
            'pickup_instructions' => 'nullable|string|max:255',

            // Delivery
            'delivery_address' => 'sometimes|string|max:255',
            'delivery_latitude' => 'sometimes|numeric|between:-90,90',
            'delivery_longitude' => 'sometimes|numeric|between:-180,180',
            'dropoff_city' => 'sometimes|string|max:100',
            'dropoff_state' => 'sometimes|string|max:100',
            'dropoff_zip_code' => 'sometimes|string|max:20',
            'delivery_instructions' => 'nullable|string|max:255',

            // Sender
            'sender_name' => 'sometimes|string|max:255',
            'sender_email' => 'sometimes|email|max:255',
            'sender_phone' => 'sometimes|string|max:20',
            'use_my_details' => 'sometimes|boolean',

            // Receiver
            'receiver_name' => 'sometimes|string|max:255',
            'receiver_email' => 'sometimes|email|max:255',
            'receiver_phone' => 'sometimes|string|max:20',

            // Package
            'package_name' => 'sometimes|string|max:255',
            'package_image' => 'nullable|string|max:255',
            'package_dimensions' => 'nullable|string|max:100',
            'package_worth' => 'sometimes|numeric|min:0',
            'package_weight' => 'sometimes|numeric|min:0',
            'package_insurance' => 'sometimes|boolean',
            'insurance_fee' => 'nullable|required_if:package_insurance,true|numeric|min:0',

            // Order Details
            'vehicle_type' => 'sometimes|string|max:50',
            'order_instruction' => 'nullable|string|max:500',
            'travel_time' => 'nullable|numeric|min:0',
            'distance_km' => 'sometimes|numeric|min:0',
            'delivery_fee' => 'sometimes|numeric|min:0',

            // Tip
            'tip_amount' => 'nullable|numeric|min:0',
            'tip_method' => ['nullable', Rule::in(['cash', 'card'])],
            'tip_added_at' => 'nullable|date',

            // Payment
            'payment_method' => ['sometimes', Rule::in(['cash', 'card', 'bank_transfer', 'wallet'])],
            'payment_status' => ['sometimes', Rule::in(['pending', 'paid', 'failed', 'cancelled'])],
            'payment_reference' => 'nullable|string|max:255',
            'date_payment_confirmed' => 'nullable|date',

            // Tracking
            'driver_assigned_at' => 'nullable|date',
            'picked_up_at' => 'nullable|date',
            'delivered_at' => 'nullable|date',
            'cancelled_at' => 'nullable|date',
            'cancellation_reason' => 'nullable|string|max:255',

            // Status
            'status' => [
                'sometimes',
                Rule::in([
                    'pending',
                    'confirmed',
                    'processing',
                    'driver_assigned',
                    'picked_up',
                    'in_transit',
                    'delivered',
                    'completed',
                    'cancelled'
                ])
            ],

            // Protected
            'order_id' => 'prohibited',
            'customer_id' => 'prohibited',
        ];
    }

    /**
     * Prepare data before validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([

            // Booleans
            'use_my_details' => $this->boolean('use_my_details'),
            'package_insurance' => $this->boolean('package_insurance'),

            // 🔥 IMPORTANT: Fix request field mismatch
            'pickup_latitude' => $this->input('pickup_lat'),
            'pickup_longitude' => $this->input('pickup_lng'),
            'delivery_address' => $this->input('dropoff_address'),
            'delivery_latitude' => $this->input('dropoff_lat'),
            'delivery_longitude' => $this->input('dropoff_lng'),
        ]);

        // Auto-fill sender details
        if ($this->boolean('use_my_details') && $this->user()) {
            $this->merge([
                'sender_name' => $this->user()->name,
                'sender_email' => $this->user()->email,
                'sender_phone' => $this->user()->phone,
            ]);
        }
    }

    /**
     * Clean validated data
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        unset($validated['order_id'], $validated['customer_id']);

        return array_filter($validated, function ($value) {
            return !is_null($value);
        });
    }

    /**
     * Custom validation logic
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            $order = $this->route('order');

            // Status transition check
            if ($order && $this->has('status')) {
                if (
                    method_exists($order, 'isValidStatusTransition') &&
                    !$order->isValidStatusTransition($this->input('status'))
                ) {
                    $validator->errors()->add(
                        'status',
                        "Invalid status transition from {$order->status}"
                    );
                }
            }

            // Insurance validation
            if (
                $this->boolean('package_insurance') &&
                !$this->input('insurance_fee')
            ) {
                $validator->errors()->add(
                    'insurance_fee',
                    'Insurance fee is required when insurance is enabled.'
                );
            }
        });
    }
}
