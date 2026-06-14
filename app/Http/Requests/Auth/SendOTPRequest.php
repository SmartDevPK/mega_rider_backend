<?php
// app/Http/Requests/Auth/SendOTPRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendOTPRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'email' => 'required|email'
    ];
  }

  public function getEmail(): string
  {
    return strtolower(trim($this->email));
  }
}
