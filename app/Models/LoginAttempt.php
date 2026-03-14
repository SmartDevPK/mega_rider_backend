<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'status',
        'attempted_at'
    ];
}
