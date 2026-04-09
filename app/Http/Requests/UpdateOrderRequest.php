<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'pickup_address'    => 'sometimes|string|max:255',
            'pickup_latitude'   => 'sometimes|numeric|between:-90,90',
            'pickup_longitude'  => 'sometimes|numeric|between:-180,180',
            'pickup_city'       => 'sometimes|string|max:100',
            'dropoff_address'   => 'sometimes|string|max:255',
            'dropoff_latitude'  => 'sometimes|numeric|between:-90,90',
            'dropoff_longitude' => 'sometimes|numeric|between:-180,180',
            'dropoff_city'      => 'sometimes|string|max:100',
            'sender_name'       => 'sometimes|string|max:100',
            'sender_phone'      => 'sometimes|string|max:20',
            'sender_email'      => 'sometimes|email|max:100',
            'receiver_name'     => 'sometimes|string|max:100',
            'receiver_phone'    => 'sometimes|string|max:20',
            'receiver_email'    => 'sometimes|email|max:100',
            'package_name'      => 'sometimes|string|max:100',
            'package_worth'     => 'sometimes|numeric|min:0',
            'package_image'     => 'nullable|image|max:2048',
            'insurance_flag'    => 'sometimes|boolean',
            'status'            => 'sometimes|in:pending,assigned,picked_up,delivered,cancelled',
        ];
    }
}