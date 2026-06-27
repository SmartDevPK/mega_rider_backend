<?php
// app/Http/Requests/PaymentRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Set to false if you want to add authorization logic
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'payment_method' => 'required|string|in:card,widget,paystack,wallet,cash,bank_transfer',
            'payment_reference' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'transaction_id' => 'sometimes|string|max:255',
            'payment_status' => 'sometimes|in:pending,paid,failed,refunded'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Payment method is required',
            'payment_method.in' => 'Invalid payment method selected',
            'payment_reference.string' => 'Payment reference must be a string',
            'amount.numeric' => 'Amount must be a number',
            'amount.min' => 'Amount must be greater than or equal to 0',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => $this->amount ? (float) $this->amount : null,
        ]);
    }
}