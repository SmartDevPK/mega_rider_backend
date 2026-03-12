<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\ContactController;



// Public routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('check-email', [AuthController::class, 'checkEmail']);

// Protected routes (requires Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [AuthController::class, 'profile']);
    Route::apiResource('team-members', TeamMemberController::class);
    Route::apiResource('contact-messages', ContactController::class);
});
