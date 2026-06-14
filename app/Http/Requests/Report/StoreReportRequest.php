<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reportable_type' => [
                'required',
                'string',
                Rule::in(['order', 'user', 'rider']),
            ],

            'reportable_id' => [
                'required',
                'integer',
                'exists:' . $this->getReportableTable() . ',id',
            ],

            'reason_id' => [
                'required',
                'exists:report_reasons,id',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'reportable_type.in' => 'The reportable type must be order, user, or rider.',
            'reportable_id.exists' => 'The selected reportable ID does not exist.',
            'reason_id.exists' => 'The selected reason is invalid.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'reportable_type' => 'report type',
            'reportable_id' => 'report ID',
            'reason_id' => 'reason',
            'description' => 'description',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize reportable_type to lowercase
        if ($this->has('reportable_type')) {
            $this->merge([
                'reportable_type' => strtolower($this->reportable_type),
            ]);
        }
    }

    /**
     * Get the table name based on reportable type.
     */
    protected function getReportableTable(): string
    {
        return match ($this->reportable_type) {
            'order' => 'orders',
            'user' => 'users',
            'rider' => 'users', // assuming riders are stored in users table with role = rider
            default => throw new InvalidArgumentException('Invalid reportable type'),
        };
    }
}
