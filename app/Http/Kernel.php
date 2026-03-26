<?php
// app/Http/Kernel.php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     */
    protected $middleware = [
        \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\ForceJsonResponse::class,
        ],
    ];

    /**
     * The application's route middleware.
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        
        
        // Custom Middleware
        'verified' => \App\Http\Middleware\VerifiedMiddleware::class,
        'active' => \App\Http\Middleware\ActiveMiddleware::class,
    ];

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting()
    {
        // Define the 'api' rate limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many requests. Please slow down.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });
        
        // Auth rate limiter
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many authentication attempts. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });
        
        // Financial operations rate limiter
        RateLimiter::for('financial', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many financial operations. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });
        
        // Admin rate limiter
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(200)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many admin requests. Please slow down.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });
        
        // Orders rate limiter
        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->user()?->id)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many order attempts. Please wait before creating more orders.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });
        
        // Emergency rate limiter
        RateLimiter::for('emergency', function (Request $request) {
            return Limit::perMinute(2)
                ->by($request->user()?->id)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Emergency alerts limited. Please wait before sending another alert.',
                        'retry_after' => $headers['Retry-After'] ?? 300
                    ], 429);
                });
        });
        
        // Uploads rate limiter
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many upload attempts. Please wait before uploading more files.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });
    }
}