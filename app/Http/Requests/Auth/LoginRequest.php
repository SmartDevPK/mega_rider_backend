<?php
// app/Http/Requests/Auth/LoginRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'email' => 'required|email',
      'password' => 'required|string',
    ];
  }

  public function getCredentials(): array
  {
    return [
      'email' => strtolower(trim($this->email)),
      'password' => $this->password,
    ];
  }
}
