<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register any custom services or bindings here if needed
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Custom VIN validator
        Validator::extend('vin', function ($attribute, $value, $parameters, $validator) {
            // VIN must be exactly 17 characters, uppercase letters (except I, O, Q) + numbers
            return preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $value);
        }, 'The :attribute must be a valid 17-character VIN.');
    }
}
