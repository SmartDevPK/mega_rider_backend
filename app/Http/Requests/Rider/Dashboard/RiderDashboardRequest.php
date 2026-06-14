<?php

namespace App\Http\Requests\Rider\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * RiderDashboardRequest
 * 
 * Form request class for validating and authorizing access to the rider dashboard.
 * This request ensures that only authenticated users with the 'rider' role can access
 * the dashboard functionality.
 * 
 * @package App\Http\Requests\Rider\Dashboard
 */
class RiderDashboardRequest extends FormRequest
{
    public function authorize()
    {
        return Auth::check() && Auth::user()->role === 'rider';
    }

    public function rules()
    {
        return [];
    }

    public function messages()
    {
        return [
            'authorize' => 'You must be a rider to access this dashboard'
        ];
    }
}
