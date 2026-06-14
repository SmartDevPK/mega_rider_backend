<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use App\Models\Order;
use App\Models\RiderReview;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'order_id'           => ['required', 'uuid', 'exists:orders,order_id'],
            'performance_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'speed_rating'       => ['required', 'integer', 'min:1', 'max:5'],
            'handling_rating'    => ['required', 'integer', 'min:1', 'max:5'],
            'review'             => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'review_content' => $this->input('review'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $order = Order::where('order_id', $this->order_id)->first();

            if (!$order) return;

            // The order must belong to the authenticated customer
            if ($order->customer_id !== auth('sanctum')->id()) {
                $validator->errors()->add('order_id', 'This order does not belong to you.');
            }

            // Only delivered orders can be reviewed
            if ($order->status !== 'delivered') {
                $validator->errors()->add('order_id', 'You can only review completed (delivered) orders.');
            }

            // No duplicate review
            $exists = RiderReview::where('order_id', $this->order_id)
                ->where('customer_id', auth('sanctum')->id())
                ->exists();

            if ($exists) {
                $validator->errors()->add('order_id', 'You have already reviewed this order.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Order ID is required.',
            'order_id.uuid'     => 'Invalid order format.',
            'order_id.exists'   => 'The selected order does not exist.',
            'performance_rating.required' => 'Performance rating is required.',
            'speed_rating.required'       => 'Speed rating is required.',
            'handling_rating.required'    => 'Handling rating is required.',
            'performance_rating.min'      => 'Ratings must be at least 1.',
            'performance_rating.max'      => 'Ratings cannot exceed 5.',
            // similar for speed, handling
            'review.max' => 'Review cannot exceed 1000 characters.',
        ];
    }
}
