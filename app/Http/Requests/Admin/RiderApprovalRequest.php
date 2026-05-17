<?php
// app/Http/Requests/Admin/RiderApprovalRequest.php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RiderApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|string|max:500',
            'admin_notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required_if' => 'Please provide a reason for rejection',
        ];
    }
}