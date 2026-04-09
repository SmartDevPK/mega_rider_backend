<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

// Services
use App\Services\GoogleMapsService;
use App\Services\PriceService;
use App\Services\InsuranceService;

// Models & Observers
use App\Models\RiderReview;
use App\Observers\RiderReviewObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register singleton services
        $this->app->singleton(GoogleMapsService::class);
        $this->app->singleton(PriceService::class);
        $this->app->singleton(InsuranceService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Custom VIN validator
        Validator::extend(
            'vin',
            function ($attribute, $value, $parameters, $validator) {
                return preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $value);
            },
            'The :attribute must be a valid 17-character VIN.'
        );

        // Register RiderReview observer
        RiderReview::observe(RiderReviewObserver::class);
    }
}
