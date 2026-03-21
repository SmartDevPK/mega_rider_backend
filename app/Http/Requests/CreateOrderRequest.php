<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Add your authorization logic if needed
    }

    public function rules()
    {
        return [
            // Pickup Information
            'pickup_address' => 'required|string|max:255',
            'pickup_latitude' => 'required|numeric|between:-90,90',
            'pickup_longitude' => 'required|numeric|between:-180,180',
            'pickup_city' => 'required|string|max:100',
            'pickup_state' => 'required|string|max:100',

            // Delivery Information
            'delivery_address' => 'required|string|max:255',
            'delivery_latitude' => 'required|numeric|between:-90,90',
            'delivery_longitude' => 'required|numeric|between:-180,180',
            'dropoff_city' => 'required|string|max:100',
            'dropoff_state' => 'required|string|max:100',

            // Sender Information
            'sender_name' => 'required|string|max:255',
            'sender_email' => 'required|email|max:255',
            'sender_phone' => 'required|string|max:20',
            'use_my_details' => 'sometimes|boolean',

            // Receiver Information
            'receiver_name' => 'required|string|max:255',
            'receiver_email' => 'required|email|max:255',
            'receiver_phone' => 'required|string|max:20',

            // Package Information
            'package_name' => 'required|string|max:255',
            'package_image' => 'nullable|string|max:255', // if uploading file
            'package_worth' => 'required|numeric|min:0',
            'package_insurance' => 'sometimes|boolean',
            'insurance_fee' => 'required_if:package_insurance,true|numeric|min:0|nullable',
            'order_instruction' => 'nullable|string|max:500',
            'travel_time' => 'nullable|integer|min:1',

            // Payment Information (optional at creation)
            'payment_method' => 'sometimes|in:cash,card,bank_transfer,wallet',
        ];
    }

    public function messages()
    {
        return [
            'pickup_address.required' => 'The pickup address is required.',
            'delivery_address.required' => 'The delivery address is required.',
            'sender_name.required' => 'Sender name is required.',
            'receiver_name.required' => 'Receiver name is required.',
            'package_name.required' => 'Package name is required.',
            'package_worth.required' => 'Package worth is required.',
            'package_worth.numeric' => 'Package worth must be a number.',
            'insurance_fee.required_if' => 'Insurance fee is required when insurance is selected.',
        ];
    }

    protected function prepareForValidation()
    {
        // Set default values if needed
        $this->merge([
            'use_my_details' => $this->boolean('use_my_details'),
            'package_insurance' => $this->boolean('package_insurance'),
        ]);
    }
}