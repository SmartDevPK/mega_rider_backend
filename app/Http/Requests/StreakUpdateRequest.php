<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StreakUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Set to true if this is internal/system-triggered
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
           'customer_id' => 'required|exists:users,id',
           'order_id' => 'required|exists:orders,order_id',
        ];
    }

    /**
     * Optional: custom error messages
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required',
            'customer_id.exists'   => 'Customer does not exist',
            'order_id.required'    => 'Order is required',
            'order_id.exists'      => 'Order does not exist',
        ];
    }
}
