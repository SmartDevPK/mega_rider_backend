<?php
// App\Http\Requests\Auth\VerifyOTPAndRegisterRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerifyOTPAndRegisterRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'otp' => 'required|string|size:8',
      'firstname' => 'required|string|max:255',
      'lastname' => 'required|string|max:255',
      'phoneNumber' => 'required|string|max:20',
      'email' => 'required|email|max:255|unique:customers,email',
      'password' => [
        'required',
        'string',
        'min:8',
        'confirmed',
        'regex:/[a-z]/',
        'regex:/[A-Z]/',
        'regex:/[0-9]/',
        'regex:/[@$!%*?&]/',
      ],
      'password_confirmation' => 'required|string|min:8',
      'referralCode' => 'nullable|string|exists:customers,referral_code',
    ];
  }

  protected function prepareForValidation(): void
  {
    $phoneNumber = $this->phoneNumber;

    if (Str::startsWith($phoneNumber, '0')) {
      $phoneNumber = '+234' . substr($phoneNumber, 1);
    }

    $this->merge([
      'normalized_phone' => $phoneNumber,
      'normalized_email' => strtolower(trim($this->email)),
    ]);
  }

  public function getServiceData(): array
  {
    return [
      'first_name' => $this->firstname,
      'last_name' => $this->lastname,
      'phone_number' => $this->normalized_phone ?? $this->phoneNumber,
      'email' => $this->normalized_email ?? strtolower(trim($this->email)),
      'password' => $this->password,
      'otp' => $this->otp,
      'referral_code' => $this->referralCode,
    ];
  }

  public function validatePhoneNumber(): void
  {
    $phone = $this->phoneNumber;
    if (!preg_match('/^(?:\+234|0)[789][01]\d{8}$/', $phone)) {
      throw ValidationException::withMessages([
        'phoneNumber' => ['The phone number must be a valid Nigerian number (e.g., 08012345678 or +2348012345678)']
      ]);
    }
  }

  public function messages(): array
  {
    return [
      'phoneNumber.regex' => 'The phone number must be a valid Nigerian number (e.g., 08012345678 or +2348012345678)',
      'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (@ $ ! % * ? &).',
      'email.unique' => 'This email is already registered.',
      'referralCode.exists' => 'The referral code is invalid.',
      'password.confirmed' => 'The password confirmation does not match.',
    ];
  }
}
