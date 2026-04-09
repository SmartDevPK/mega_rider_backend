<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'reportable_type' => ['required', 'string', Rule::in(['order', 'user', 'rider'])],
            'reportable_id'   => ['required', 'integer', 'exists:' . $this->getReportableTable() . ',id'],
            'reason_id'       => ['required', 'exists:report_reasons,id'],
            'description'     => 'nullable|string|max:5000',
        ];
    }

    protected function getReportableTable(): string
    {
        return match ($this->reportable_type) {
            'order' => 'orders',
            'user'  => 'users',
            'rider' => 'users', // assuming riders are in users table with role 'rider'
            default => throw new \InvalidArgumentException('Invalid reportable type'),
        };
    }

    public function messages(): array
    {
        return [
            'reportable_type.in' => 'The reportable type must be order, user, or rider.',
            'reportable_id.exists' => 'The selected reportable ID does not exist.',
            'reason_id.exists'    => 'The selected reason is invalid.',
        ];
    }
}