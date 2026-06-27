<?php

namespace App\Http\Requests\Rider;

use Illuminate\Foundation\Http\FormRequest;

class RiderActivitiesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isRider();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'cursor' => 'nullable|string',
            'page_size' => 'required|integer|min:1|max:100',
            'order_id' => 'nullable|string|max:255',
            'order_status' => 'nullable|string|in:pending,assigned,picked_up,delivered,cancelled',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'page_size.required' => 'Page size is required',
            'page_size.integer' => 'Page size must be a number',
            'page_size.min' => 'Page size must be greater than zero',
            'page_size.max' => 'Page size cannot exceed 100',
            'order_status.in' => 'Invalid order status provided',
        ];
    }
}