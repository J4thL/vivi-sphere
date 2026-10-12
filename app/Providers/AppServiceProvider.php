<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        $this->configureRateLimiting();
    }

    /**
     * Each kind of request gets its own budget. The old unnamed `throttle:6,1` shared one bucket per
     * IP across login, register and password reset, so on a salon's single Wi-Fi a few staff signing in
     * could lock each other out, and password-reset spam used up everyone's login attempts.
     */
    private function configureRateLimiting(): void
    {
        // Guessing one account's password is slowed per email+IP; the per-IP ceiling is generous
        // enough for a whole front desk sharing a connection.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(6)->by('login|'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by('login-ip|'.$request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));

        // Reset mail is limited per address as well, so one address cannot be flooded from many IPs.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(6)->by('reset|'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('reset-ip|'.$request->ip()),
        ]);

        // Signed-in dashboard calls are counted per user, not per IP.
        RateLimiter::for('bookings', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }
}
