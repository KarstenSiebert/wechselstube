<?php

namespace App\Providers;

use Session;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Inertia::share([
            'flash' => function () {
                return [
                    'success' => Session::get('success'),
                    'error' => Session::get('error'),
                ];
            },
            'locale' => fn() => app()->getLocale(),
        ]);

        RateLimiter::for('api', function (Request $request) {
            return [
                // Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()),
                Limit::perMinute(10)->by($request->input('email')),
            ];
        });
    }
}
