<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vehicleId = $this->route('vehicleId') ?? $this->route('vehicle');
        
        return [
            'license_plate' => 'required|string|regex:/^[A-Z0-9-]+$/|unique:vehicles,license_plate,' . $vehicleId,
            'make' => 'required|string|max:50',
            'model' => 'required|string|max:50',
            'year' => 'required|integer|min:1900|max:' . date('Y'),
            'color' => 'nullable|string|max:30',
            'vin' => 'required|vin|unique:vehicles,vin,' . $vehicleId,
            'insurance_fee' => 'nullable|numeric|min:0',
            'insurance_paid' => 'nullable|boolean',
            'insurance_paid_at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'license_plate.required' => 'License plate is required',
            'license_plate.unique' => 'This license plate already exists',
            'vin.required' => 'VIN is required',
            'vin.unique' => 'This VIN already exists',
            'year.max' => 'Year cannot be in the future',
        ];
    }
}