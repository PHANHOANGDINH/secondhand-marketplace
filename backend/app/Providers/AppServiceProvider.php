<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
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
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => config('auth.password_reset_url').'?'.http_build_query([
            'token' => $token, 'email' => $user->getEmailForPasswordReset(),
        ], '', '&', PHP_QUERY_RFC3986));
        foreach (['forgot-password' => 3, 'reset-password' => 5, 'email-verification' => 3] as $name => $attempts) {
            RateLimiter::for($name, function (Request $request) use ($name, $attempts): array {
                $email = $request->input('email');
                $normalized = is_string($email) ? Str::lower(trim($email)) : '';

                return [
                    Limit::perMinute($attempts)->by($name.':identity:'.hash('sha256', $normalized.'|'.$request->ip())),
                    Limit::perMinute(20)->by($name.':ip:'.$request->ip()),
                ];
            });
        }
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $normalized = is_string($email) ? Str::lower(trim($email)) : '';

            return [
                Limit::perMinute(5)->by('identity:'.hash('sha256', $normalized.'|'.$request->ip())),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('reports', fn (Request $request): array => [Limit::perMinute(5)->by('reports:minute:'.$request->user()->id), Limit::perHour(30)->by('reports:hour:'.$request->user()->id)]);
        RateLimiter::for('auction-bid', fn (Request $request): Limit => Limit::perMinute(20)->by((string) $request->user()->id));
        RateLimiter::for('messaging', fn (Request $request): Limit => Limit::perMinute(20)->by((string) $request->user()->id));
        RateLimiter::for('video-comment', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()->id));
        RateLimiter::for('checkout-preview', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->user()->id));
        RateLimiter::for('payment', fn (Request $request): Limit => Limit::perMinute(12)->by((string) $request->user()->id));
        RateLimiter::for('upload', fn (Request $request): Limit => Limit::perMinute(20)->by((string) $request->user()->id));
        RateLimiter::for('public-read', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('domain', fn (Request $request): Limit => $request->isMethodSafe() ? Limit::none() : Limit::perMinute(60)->by((string) $request->user()->id));
        RateLimiter::for('register', fn (Request $request): Limit => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('email-verify', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        // A six-digit code must not be guessable: the caps per address hold across IPs and across new codes.
        RateLimiter::for('email-code', function (Request $request): array {
            $email = $request->input('email');
            $normalized = hash('sha256', is_string($email) ? Str::lower(trim($email)) : '');

            return [
                Limit::perMinute(5)->by('email-code:minute:'.$normalized),
                Limit::perHour(20)->by('email-code:hour:'.$normalized),
                Limit::perMinute(20)->by('email-code:ip:'.$request->ip()),
            ];
        });
    }
}
