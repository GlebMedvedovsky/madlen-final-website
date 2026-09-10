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
        RateLimiter::for('contact', function (Request $request): array {
            $ipFingerprint = hash('sha256', (string) $request->ip());
            $response = static function (Request $request, array $headers) {
                $language = $request->input('language') === 'en' ? 'en' : 'de';

                return response()->json([
                    'ok' => false,
                    'message' => $language === 'en'
                        ? 'Too many attempts. Please wait before trying again.'
                        : 'Zu viele Versuche. Bitte warten Sie, bevor Sie es erneut versuchen.',
                ], 429, $headers);
            };

            return [
                Limit::perMinute(max(1, (int) config('contact.rate_limit.per_minute', 3)))
                    ->by("contact-minute-ip:{$ipFingerprint}")
                    ->response($response),
                Limit::perHour(max(1, (int) config('contact.rate_limit.per_hour', 10)))
                    ->by("contact-hour-ip:{$ipFingerprint}")
                    ->response($response),
            ];
        });
    }
}
