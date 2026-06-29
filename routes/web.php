<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'API is running',
        'status' => 'ok',
        'version' => '1.0.0'
    ]);
});
