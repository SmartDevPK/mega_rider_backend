<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Let the policy handle authorization
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|string|exists:orders,order_id',
            'reason'   => 'nullable|string|max:500',
        ];
    }
}