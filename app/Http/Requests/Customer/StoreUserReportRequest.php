<?php

namespace App\Http\Requests\Customer\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check(); // ensure user is authenticated
    }

    public function rules(): array
    {
        return [
            'reported_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value == auth('sanctum')->id()) {
                        $fail('You cannot report yourself.');
                    }
                },
            ],
            'reason_id' => 'nullable|exists:report_reasons,id',
            'comment'   => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reported_id.required' => 'The user to report is required.',
            'reported_id.exists'   => 'The selected user does not exist.',
            'reason_id.exists'     => 'Invalid report reason.',
            'comment.max'          => 'Comment cannot exceed 500 characters.',
        ];
    }
}
