<?php
// app/Http/Requests/UpdateOrderRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize()
    {
        $order = $this->route('order');
        
        if ($order) {
            // Check if user owns this order or is admin
            return $this->user()->id === $order->customer_id || $this->user()->isAdmin();
        }
        
        return true; // Temporarily allow for testing
    }

    public function rules()
    {
        $order = $this->route('order');
        $orderId = $order ? $order->id : null;
        
        return [
            // Pickup Information
            'pickup_address' => 'sometimes|string|max:255',
            'pickup_latitude' => 'sometimes|numeric|between:-90,90',
            'pickup_longitude' => 'sometimes|numeric|between:-180,180',
            'pickup_city' => 'sometimes|string|max:100',
            'pickup_state' => 'sometimes|string|max:100',

            // Delivery Information
            'delivery_address' => 'sometimes|string|max:255',
            'delivery_latitude' => 'sometimes|numeric|between:-90,90',
            'delivery_longitude' => 'sometimes|numeric|between:-180,180',
            'dropoff_city' => 'sometimes|string|max:100',
            'dropoff_state' => 'sometimes|string|max:100',

            // Sender Information
            'sender_name' => 'sometimes|string|max:255',
            'sender_email' => 'sometimes|email|max:255',
            'sender_phone' => 'sometimes|string|max:20',
            'use_my_details' => 'sometimes|boolean',

            // Receiver Information
            'receiver_name' => 'sometimes|string|max:255',
            'receiver_email' => 'sometimes|email|max:255',
            'receiver_phone' => 'sometimes|string|max:20',

            // Package Information
            'package_name' => 'sometimes|string|max:255',
            'package_image' => 'nullable|string|max:255',
            'package_worth' => 'sometimes|numeric|min:0',
            'package_insurance' => 'sometimes|boolean',
            'insurance_fee' => 'nullable|required_if:package_insurance,true|numeric|min:0',
            'order_type' => ['sometimes', Rule::in(['express', 'standard', 'scheduled'])],
            'order_instruction' => 'nullable|string|max:500',
            'travel_time' => 'nullable|string|max:100',

            // Payment Information
            'payment_method' => ['sometimes', Rule::in(['cash', 'card', 'bank_transfer', 'wallet'])],
            'payment_status' => ['sometimes', Rule::in(['pending', 'paid', 'failed', 'cancelled'])],
            
            // Order Status
            'status' => [
                'sometimes',
                Rule::in(['pending', 'confirmed', 'processing', 'picked_up', 'in_transit', 'delivered', 'completed', 'cancelled'])
            ],
            
            // Prevent updating certain fields
            'order_id' => 'prohibited',
            'customer_id' => 'prohibited',
        ];
    }

    public function messages()
    {
        return [
            'pickup_address.required' => 'The pickup address is required.',
            'pickup_latitude.required' => 'Pickup latitude is required.',
            'pickup_longitude.required' => 'Pickup longitude is required.',
            'pickup_city.required' => 'Pickup city is required.',
            'pickup_state.required' => 'Pickup state is required.',
            'delivery_address.required' => 'The delivery address is required.',
            'delivery_latitude.required' => 'Delivery latitude is required.',
            'delivery_longitude.required' => 'Delivery longitude is required.',
            'dropoff_city.required' => 'Dropoff city is required.',
            'dropoff_state.required' => 'Dropoff state is required.',
            'sender_name.required' => 'Sender name is required.',
            'sender_email.required' => 'Sender email is required.',
            'sender_phone.required' => 'Sender phone is required.',
            'receiver_name.required' => 'Receiver name is required.',
            'receiver_email.required' => 'Receiver email is required.',
            'receiver_phone.required' => 'Receiver phone is required.',
            'package_name.required' => 'Package name is required.',
            'package_worth.required' => 'Package worth is required.',
            'package_worth.numeric' => 'Package worth must be a number.',
            'insurance_fee.required_if' => 'Insurance fee is required when insurance is selected.',
            'order_type.required' => 'Order type is required.',
            'order_id.prohibited' => 'Order ID cannot be updated.',
            'customer_id.prohibited' => 'Customer ID cannot be updated.',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'use_my_details' => $this->boolean('use_my_details'),
            'package_insurance' => $this->boolean('package_insurance'),
        ]);

        // If use_my_details is true, populate sender details from authenticated user
        if ($this->input('use_my_details') && $this->user()) {
            $this->merge([
                'sender_name' => $this->user()->name,
                'sender_email' => $this->user()->email,
                'sender_phone' => $this->user()->phone,
            ]);
        }
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();
        
        // Remove prohibited fields
        unset($validated['order_id'], $validated['customer_id']);
        
        // Remove fields that weren't actually provided
        return array_filter($validated, function ($value) {
            return !is_null($value);
        });
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $order = $this->route('order');
            
            if ($order && $this->has('status')) {
                if (!$order->isValidStatusTransition($this->input('status'))) {
                    $validator->errors()->add(
                        'status', 
                        'Cannot change status from ' . $order->status . ' to ' . $this->input('status')
                    );
                }
            }

            // Validate insurance fee when insurance is true
            if ($this->input('package_insurance') && !$this->input('insurance_fee')) {
                $validator->errors()->add('insurance_fee', 'Insurance fee is required when insurance is enabled.');
            }
        });
    }
}