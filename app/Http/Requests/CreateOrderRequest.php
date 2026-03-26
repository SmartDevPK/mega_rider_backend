<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // =============================================
            // Pickup Information (Required)
            // =============================================
            'pickup_address'       => 'required|string|max:255',
            'pickup_lat'           => 'required|numeric|between:-90,90',
            'pickup_lng'           => 'required|numeric|between:-180,180',
            'pickup_city'          => 'required|string|max:100',
            'pickup_state'         => 'required|string|max:100',
            'pickup_zip_code'      => 'nullable|string|max:20',
            'pickup_instructions'  => 'nullable|string|max:500',

            // =============================================
            // Dropoff Information (Required)
            // =============================================
            'dropoff_address'       => 'required|string|max:255',
            'dropoff_lat'           => 'required|numeric|between:-90,90',
            'dropoff_lng'           => 'required|numeric|between:-180,180',
            'dropoff_city'          => 'required|string|max:100',
            'dropoff_state'         => 'required|string|max:100',
            'dropoff_zip_code'      => 'nullable|string|max:20',
            'delivery_instructions' => 'nullable|string|max:500',

            // =============================================
            // Sender Details (Required)
            // =============================================
            'sender_name'   => 'required|string|max:255',
            'sender_email'  => 'required|email|max:255',
            'sender_phone'  => ['required', 'regex:/^\+?[0-9]{10,15}$/'],
            'use_my_details'=> 'sometimes|boolean',

            // =============================================
            // Receiver Details (Required)
            // =============================================
            'receiver_name'   => 'required|string|max:255',
            'receiver_email'  => 'required|email|max:255',
            'receiver_phone'  => ['required', 'regex:/^\+?[0-9]{10,15}$/'],

            // =============================================
            // Package Details
            // =============================================
            'package_name'       => 'required|string|max:255',
            'package_worth'      => 'required|numeric|min:0',
            'package_weight'     => 'nullable|numeric|min:0',
            'package_dimensions' => 'nullable|string|max:255',
            'package_image'      => 'nullable|string|max:255', // Can be URL or base64
            'package_insurance'  => 'sometimes|boolean',
            'insurance_fee'      => 'required_if:package_insurance,true|numeric|min:0',

            // =============================================
            // Order Details
            // =============================================
            'vehicle_type'       => ['sometimes', 'string', Rule::in(['motorcycle', 'bike', 'van', 'car'])],
            'order_instruction'  => 'nullable|string|max:500',
            'travel_time'        => 'nullable|integer|min:1',
            'distance_km'        => 'nullable|numeric|min:0',
            'delivery_fee'       => 'nullable|numeric|min:0',

            // =============================================
            // Payment Information
            // =============================================
            'payment_method'         => ['sometimes', Rule::in(['cash', 'card', 'bank_transfer', 'wallet', 'paystack'])],
            'payment_reference'      => 'nullable|string|max:255',
            'date_payment_confirmed' => 'nullable|date',

            // =============================================
            // Tip Information
            // =============================================
            'tip_amount'    => 'nullable|numeric|min:0',
            'tip_method'    => ['nullable', Rule::in(['cash', 'card', 'wallet'])],
            'tip_added_at'  => 'nullable|date',

            // =============================================
            // Tracking & Cancellation
            // =============================================
            'driver_assigned_at' => 'nullable|date',
            'picked_up_at'       => 'nullable|date',
            'delivered_at'       => 'nullable|date',
            'cancelled_at'       => 'nullable|date',
            'cancellation_reason'=> 'nullable|string|max:500',
        ];
    }

    /**
     * Prepare input data before validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert boolean fields
        $this->merge([
            'use_my_details'    => $this->boolean('use_my_details'),
            'package_insurance' => $this->boolean('package_insurance'),
        ]);

        // Auto-fill sender details from authenticated user if requested
        if ($this->boolean('use_my_details') && auth()->check()) {
            $user = auth()->user();
            $this->merge([
                'sender_name'  => $user->firstname . ' ' . $user->lastname,
                'sender_email' => $user->email,
                'sender_phone' => $user->phoneNumber,
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Required messages
            'pickup_address.required' => 'Pickup address is required.',
            'pickup_lat.required' => 'Pickup latitude is required.',
            'pickup_lng.required' => 'Pickup longitude is required.',
            'dropoff_address.required' => 'Dropoff address is required.',
            'dropoff_lat.required' => 'Dropoff latitude is required.',
            'dropoff_lng.required' => 'Dropoff longitude is required.',
            'package_name.required' => 'Package name is required.',
            'package_worth.required' => 'Package worth is required.',
            'sender_name.required' => 'Sender name is required.',
            'sender_email.required' => 'Sender email is required.',
            'sender_phone.required' => 'Sender phone number is required.',
            'receiver_name.required' => 'Receiver name is required.',
            'receiver_email.required' => 'Receiver email is required.',
            'receiver_phone.required' => 'Receiver phone number is required.',
            
            // Format messages
            'pickup_lat.between' => 'Pickup latitude must be between -90 and 90.',
            'pickup_lng.between' => 'Pickup longitude must be between -180 and 180.',
            'dropoff_lat.between' => 'Dropoff latitude must be between -90 and 90.',
            'dropoff_lng.between' => 'Dropoff longitude must be between -180 and 180.',
            'sender_email.email' => 'Please enter a valid email address for sender.',
            'receiver_email.email' => 'Please enter a valid email address for receiver.',
            'sender_phone.regex' => 'Please enter a valid phone number (10-15 digits).',
            'receiver_phone.regex' => 'Please enter a valid phone number (10-15 digits).',
            
            // Numeric messages
            'package_worth.min' => 'Package worth must be at least 0.',
            'package_weight.min' => 'Package weight must be at least 0.',
            'distance_km.min' => 'Distance must be at least 0.',
            'delivery_fee.min' => 'Delivery fee must be at least 0.',
            'tip_amount.min' => 'Tip amount must be at least 0.',
            'travel_time.min' => 'Travel time must be at least 1 minute.',
            
            // Insurance messages
            'insurance_fee.required_if' => 'Insurance fee is required when package insurance is enabled.',
            'insurance_fee.min' => 'Insurance fee must be at least 0.',
            
            // Vehicle type
            'vehicle_type.in' => 'Vehicle type must be motorcycle, bike, van, or car.',
            'payment_method.in' => 'Payment method must be cash, card, bank_transfer, wallet, or paystack.',
        ];
    }

    /**
     * Get a structured DTO for order creation.
     */
    public function getDTO(): array
    {
        return [
            // =============================================
            // Pickup Information
            // =============================================
            'pickup_address'      => $this->pickup_address,
            'pickup_latitude'     => (float) $this->pickup_lat,
            'pickup_longitude'    => (float) $this->pickup_lng,
            'pickup_city'         => $this->pickup_city,
            'pickup_state'        => $this->pickup_state,
            'pickup_zip_code'     => $this->pickup_zip_code,
            'pickup_instructions' => $this->pickup_instructions,

            // =============================================
            // Delivery Information
            // =============================================
            'delivery_address'       => $this->dropoff_address,
            'delivery_latitude'      => (float) $this->dropoff_lat,
            'delivery_longitude'     => (float) $this->dropoff_lng,
            'dropoff_city'           => $this->dropoff_city,
            'dropoff_state'          => $this->dropoff_state,
            'dropoff_zip_code'       => $this->dropoff_zip_code,
            'delivery_instructions'  => $this->delivery_instructions,

            // =============================================
            // Sender Details
            // =============================================
            'sender_name'   => $this->sender_name,
            'sender_email'  => $this->sender_email,
            'sender_phone'  => $this->sender_phone,
            'use_my_details'=> $this->boolean('use_my_details'),

            // =============================================
            // Receiver Details
            // =============================================
            'receiver_name'  => $this->receiver_name,
            'receiver_email' => $this->receiver_email,
            'receiver_phone' => $this->receiver_phone,

            // =============================================
            // Package Details
            // =============================================
            'package_name'       => $this->package_name,
            'package_worth'      => (float) $this->package_worth,
            'package_weight'     => $this->package_weight ? (float) $this->package_weight : null,
            'package_dimensions' => $this->package_dimensions,
            'package_image'      => $this->package_image ?? $this->file('package_image'),
            'package_insurance'  => $this->boolean('package_insurance'),
            'insurance_fee'      => $this->insurance_fee ? (float) $this->insurance_fee : 0,

            // =============================================
            // Order Details
            // =============================================
            'vehicle_type'       => $this->vehicle_type ?? 'motorcycle',
            'order_instruction'  => $this->order_instruction,
            'travel_time'        => $this->travel_time ? (int) $this->travel_time : null,
            'distance_km'        => $this->distance_km ? (float) $this->distance_km : null,
            'delivery_fee'       => $this->delivery_fee ? (float) $this->delivery_fee : null,

            // =============================================
            // Payment Information
            // =============================================
            'payment_method'         => $this->payment_method ?? 'cash',
            'payment_reference'      => $this->payment_reference,
            'date_payment_confirmed' => $this->date_payment_confirmed,

            // =============================================
            // Tip Information
            // =============================================
            'tip_amount'    => $this->tip_amount ? (float) $this->tip_amount : null,
            'tip_method'    => $this->tip_method,
            'tip_added_at'  => $this->tip_added_at,

            // =============================================
            // Tracking & Cancellation
            // =============================================
            'driver_assigned_at' => $this->driver_assigned_at,
            'picked_up_at'       => $this->picked_up_at,
            'delivered_at'       => $this->delivered_at,
            'cancelled_at'       => $this->cancelled_at,
            'cancellation_reason'=> $this->cancellation_reason,

            // =============================================
            // Status (Default)
            // =============================================
            'status'         => 'pending',
            'payment_status' => 'pending',
        ];
    }

    /**
     * Get validated data as a flat array for order creation.
     */
    public function validatedData(): array
    {
        return $this->getDTO();
    }
}