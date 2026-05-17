<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RiderRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // PERSONAL INFORMATION
            'first_name'   => ['required', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'unique:riders,email'],
            'phone_number' => ['required', 'string', 'unique:riders,phone_number'],
            'gender'       => ['required', 'in:male,female,other'],
            'address'      => ['required', 'string'],
            'nin'          => ['required', 'string', 'size:11', 'unique:riders,nin'],

            // VEHICLE INFORMATION
            'vehicle_type'         => ['required', 'in:car,bike,bicycle,tricycle'],
            'vehicle_name'         => ['required', 'string', 'max:255'],
            'vehicle_color'        => ['required', 'string', 'max:50'],
            'vehicle_plate_number' => ['required', 'string', 'unique:riders,vehicle_plate_number'],
            'driver_license_number'=> ['required', 'string', 'unique:riders,driver_license_number'],

            // WORK HISTORY
            'previous_place_of_work' => ['nullable', 'string'],
            'years_of_work'          => ['nullable', 'integer', 'min:0'],
            

            // APPROVAL FIELDS - FIXED: Made these optional for registration
            'status' => ['nullable', 'in:pending,approved,rejected'],   
            'rejection_reason' => ['nullable', 'string'],
            'approved_at' => ['nullable', 'datetime'],
            'approved_by' => ['nullable', 'exists:admins,id'],

            // GUARANTOR INFORMATION
            'guarantor_name'         => ['required', 'string'],
            'guarantor_phone'        => ['required', 'string'],
            'guarantor_relationship' => ['required', 'string'],
            'guarantor_address'      => ['nullable', 'string'],
            'guarantor_occupation'   => ['nullable', 'string'],

            // NEXT OF KIN
            'nok_name'         => ['required', 'string'],
            'nok_phone'        => ['required', 'string'],
            'nok_relationship' => ['required', 'string'],
            'nok_address'      => ['nullable', 'string'],

            // FILE UPLOADS - FIXED: Made required fields actually required
            'image'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB max  // Optional
            'driver_license_image'  => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:10240'], // 10MB max
            'utility_bill'          => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:10240'], // 10MB max
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered.',
            'phone_number.unique' => 'This phone number is already registered.',
            'nin.unique' => 'This NIN is already registered.',
            'vehicle_plate_number.unique' => 'This vehicle plate number is already registered.',
            'driver_license_number.unique' => 'This driver license number is already registered.',
            
            // Add required messages
            'driver_license_image.required' => 'Driver license image is required.',
            'utility_bill.required' => 'Utility bill is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower($this->email),
            'phone_number' => preg_replace('/\s+/', '', $this->phone_number),
        ]);
    }
}