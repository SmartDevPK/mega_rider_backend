<?php
// app/Models/Admin.php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasApiTokens, Notifiable;
    
    protected $table = 'admins';
    
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active'
    ];
    
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];
}