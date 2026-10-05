<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $normalized = is_string($email) ? Str::lower(trim($email)) : '';

            return [
                Limit::perMinute(5)->by('identity:'.hash('sha256', $normalized.'|'.$request->ip())),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('payment', fn (Request $request): Limit => Limit::perMinute(12)->by((string) $request->user()->id));
        RateLimiter::for('upload', fn (Request $request): Limit => Limit::perMinute(20)->by((string) $request->user()->id));
        RateLimiter::for('public-read', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('domain', fn (Request $request): Limit => $request->isMethodSafe() ? Limit::none() : Limit::perMinute(60)->by((string) $request->user()->id));
        RateLimiter::for('register', fn (Request $request): Limit => Limit::perHour(5)->by($request->ip()));
    }
}
