<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled in controller
    }

    public function rules(): array
    {
        return [
            'order_id'      => 'required|string|exists:orders,order_id',
            'order_type_id' => 'required|integer|exists:order_types,id',
        ];
    }
}
