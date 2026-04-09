<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // Pickup
            'pickup_address'    => 'required|string|max:255',
            'pickup_latitude'   => 'required|numeric|between:-90,90',
            'pickup_longitude'  => 'required|numeric|between:-180,180',
            'pickup_city'       => 'required|string|max:100',

            // Dropoff
            'dropoff_address'   => 'required|string|max:255',
            'dropoff_latitude'  => 'required|numeric|between:-90,90',
            'dropoff_longitude' => 'required|numeric|between:-180,180',
            'dropoff_city'      => 'required|string|max:100',

            // Sender
            'sender_name'       => 'required|string|max:100',
            'sender_phone'      => 'required|string|max:20',
            'sender_email'      => 'required|email|max:100',

            // Receiver
            'receiver_name'     => 'required|string|max:100',
            'receiver_phone'    => 'required|string|max:20',
            'receiver_email'    => 'required|email|max:100',
            'role'              => 'required|in:customer,driver',

            // Package
            'package_name'      => 'required|string|max:100',
            'package_worth'     => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/', // DECIMAL(10,2)
            'package_image'     => 'nullable|image|max:2048', // 2MB
            'insurance_flag'    => 'sometimes|boolean',
            'insurance_fee'     => 'nullable|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/', // DECIMAL(10,2)

            // Optional fields
            'price'             => 'nullable|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/', // matches DECIMAL(10,2)
            'item_name'         => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'package_worth.regex' => 'The package worth must have at most two decimal places.',
            'price.regex'         => 'The price must have at most two decimal places.',
            'pickup_latitude.between'  => 'The pickup latitude must be between -90 and 90.',
            'pickup_longitude.between' => 'The pickup longitude must be between -180 and 180.',
            // add other custom messages as needed
        ];
    }
}
