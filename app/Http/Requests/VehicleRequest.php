<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for creating/updating a vehicle.
     */
    public function rules(): array
    {
        $vehicleId = $this->route('vehicle') ? $this->route('vehicle')->id : null;
        
        return [
            // Vehicle information
            'vehicle_type'   => 'required|string|in:motorcycle,bike,van,car',
            'license_plate'  => 'required|string|regex:/^[A-Z0-9-]+$/|max:20|unique:vehicles,license_plate,' . $vehicleId,
            'make'           => 'required|string|max:50',
            'model'          => 'required|string|max:50',
            'year'           => 'required|integer|min:1900|max:' . date('Y'),
            'color'          => 'nullable|string|max:30',
            'vin'            => 'nullable|string|size:17|unique:vehicles,vin,' . $vehicleId,

            // Driver assignment
            'driver_id'      => 'required|integer|exists:users,id',

            // Insurance
            'insurance_fee'     => 'nullable|numeric|min:0',
            'insurance_paid'    => 'nullable|boolean',
            'insurance_paid_at' => 'nullable|date|required_if:insurance_paid,true|after_or_equal:today',
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            // Vehicle type
            'vehicle_type.required' => 'Vehicle type is required',
            'vehicle_type.in'       => 'Vehicle type must be motorcycle, bike, van, or car',

            // License plate
            'license_plate.required' => 'License plate is required',
            'license_plate.regex'    => 'License plate format is invalid (only uppercase letters, numbers, and hyphens)',
            'license_plate.unique'   => 'This license plate is already registered',
            'license_plate.max'      => 'License plate must not exceed 20 characters',

            // Vehicle details
            'make.required'  => 'Vehicle make is required',
            'model.required' => 'Vehicle model is required',
            'year.required'  => 'Vehicle year is required',
            'year.min'       => 'Year must be 1900 or later',
            'year.max'       => 'Year cannot be in the future',

            // VIN
            'vin.size'       => 'VIN must be exactly 17 characters if provided',
            'vin.unique'     => 'This VIN is already registered',

            // Driver
            'driver_id.required' => 'Driver selection is required',
            'driver_id.exists'   => 'Selected driver does not exist',

            // Insurance
            'insurance_fee.numeric'               => 'Insurance fee must be a number',
            'insurance_paid.boolean'              => 'Insurance paid must be true or false',
            'insurance_paid_at.required_if'       => 'Insurance paid date is required when insurance is marked as paid',
            'insurance_paid_at.date'              => 'Insurance paid at must be a valid date',
            'insurance_paid_at.after_or_equal'    => 'Insurance paid date cannot be in the past',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert license plate to uppercase for consistency
        if ($this->has('license_plate')) {
            $this->merge([
                'license_plate' => strtoupper($this->license_plate),
            ]);
        }

        // Convert VIN to uppercase if provided
        if ($this->has('vin') && $this->vin) {
            $this->merge([
                'vin' => strtoupper($this->vin),
            ]);
        }
    }
}