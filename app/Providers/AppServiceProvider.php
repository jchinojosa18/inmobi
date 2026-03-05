<?php

namespace App\Providers;

use App\Listeners\RecordAuthEvent;
use App\Models\AuthEvent;
use App\Support\SystemHeartbeatService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
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
        $this->registerAuthRateLimiters();

        Event::listen(Login::class, [RecordAuthEvent::class, 'handleLogin']);
        Event::listen(Failed::class, [RecordAuthEvent::class, 'handleFailed']);
        Event::listen(Logout::class, [RecordAuthEvent::class, 'handleLogout']);

        Queue::after(function (JobProcessed $event): void {
            try {
                app(SystemHeartbeatService::class)->touch('queue_worker', 'ok', [
                    'connection' => $event->connectionName,
                    'job' => $event->job->resolveName(),
                    'queue' => $event->job->getQueue(),
                ]);
            } catch (\Throwable) {
                // Never block queue worker due to heartbeat instrumentation.
            }
        });

        Queue::failing(function (JobFailed $event): void {
            try {
                app(SystemHeartbeatService::class)->touch('queue_worker', 'failed', [
                    'connection' => $event->connectionName,
                    'job' => $event->job->resolveName(),
                    'queue' => $event->job->getQueue(),
                    'exception' => $event->exception->getMessage(),
                ]);
            } catch (\Throwable) {
                // Never block queue worker due to heartbeat instrumentation.
            }
        });
    }

    private function registerAuthRateLimiters(): void
    {
        RateLimiter::for('login-email', function (Request $request) {
            $key = $this->loginEmailThrottleKey($request);

            return [
                Limit::perMinute(5)
                    ->by($key)
                    ->response(function (Request $request, array $headers) use ($key) {
                        $retryAfter = max((int) RateLimiter::availableIn($key), 1);
                        $this->logLoginThrottle($request, 'login-email', $retryAfter);

                        return response()->view('auth.login', [
                            'throttleMessage' => "Demasiados intentos. Intenta de nuevo en {$retryAfter} segundos.",
                        ], 429, $headers);
                    }),
            ];
        });

        RateLimiter::for('login-ip', function (Request $request) {
            $key = $this->loginIpThrottleKey($request);

            return [
                Limit::perMinute(20)
                    ->by($key)
                    ->response(function (Request $request, array $headers) use ($key) {
                        $retryAfter = max((int) RateLimiter::availableIn($key), 1);
                        $this->logLoginThrottle($request, 'login-ip', $retryAfter);

                        return response()->view('auth.login', [
                            'throttleMessage' => "Demasiados intentos. Intenta de nuevo en {$retryAfter} segundos.",
                        ], 429, $headers);
                    }),
            ];
        });

        RateLimiter::for('register-hourly', function (Request $request) {
            $key = 'register-hourly|'.$request->ip();

            return [
                Limit::perHour(3)
                    ->by($key)
                    ->response(function (Request $request, array $headers) use ($key) {
                        $retryAfter = max((int) RateLimiter::availableIn($key), 1);

                        return response()->view('auth.register', [
                            'inviteToken' => null,
                            'invitation' => null,
                            'throttleMessage' => "Demasiados intentos. Intenta de nuevo en {$retryAfter} segundos.",
                        ], 429, $headers);
                    }),
            ];
        });

        RateLimiter::for('register-daily', function (Request $request) {
            $key = 'register-daily|'.$request->ip();

            return [
                Limit::perDay(10)
                    ->by($key)
                    ->response(function (Request $request, array $headers) use ($key) {
                        $retryAfter = max((int) RateLimiter::availableIn($key), 1);

                        return response()->view('auth.register', [
                            'inviteToken' => null,
                            'invitation' => null,
                            'throttleMessage' => "Demasiados intentos. Intenta de nuevo en {$retryAfter} segundos.",
                        ], 429, $headers);
                    }),
            ];
        });

        RateLimiter::for('verification-send', function (Request $request) {
            $userId = $request->user()?->id ?: 'guest';
            $key = "verification-send|{$userId}|".$request->ip();

            return [
                Limit::perMinute(3)
                    ->by($key)
                    ->response(function (Request $request, array $headers) use ($key) {
                        $retryAfter = max((int) RateLimiter::availableIn($key), 1);

                        return response()->view('auth.verify-email', [
                            'throttleMessage' => "Demasiados intentos. Intenta de nuevo en {$retryAfter} segundos.",
                        ], 429, $headers);
                    }),
            ];
        });
    }

    private function loginEmailThrottleKey(Request $request): string
    {
        $email = mb_strtolower(trim((string) $request->input('email', '')));

        return 'login-email|'.$request->ip().'|'.$email;
    }

    private function loginIpThrottleKey(Request $request): string
    {
        return 'login-ip|'.$request->ip();
    }

    private function logLoginThrottle(Request $request, string $limiter, int $retryAfter): void
    {
        try {
            AuthEvent::query()->create([
                'organization_id' => null,
                'user_id' => null,
                'email' => mb_strtolower(trim((string) $request->input('email', ''))),
                'event' => 'login_failed',
                'occurred_at' => now(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'plaza_id' => null,
                'meta' => [
                    'guard' => 'web',
                    'throttled' => true,
                    'limiter' => $limiter,
                    'retry_after_seconds' => $retryAfter,
                ],
            ]);
        } catch (\Throwable) {
            // Never block auth flow due to throttle logging failure.
        }
    }
}
