<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** @var list<string> */
    private const SUPPORTED = ['es', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $userLocale = $request->user()?->locale;
        if (is_string($userLocale) && in_array($userLocale, self::SUPPORTED, true)) {
            return $userLocale;
        }

        if ($request->hasSession()) {
            $sessionLocale = $request->session()->get('locale');
            if (is_string($sessionLocale) && in_array($sessionLocale, self::SUPPORTED, true)) {
                return $sessionLocale;
            }
        }

        $default = (string) config('app.locale', 'es');

        return in_array($default, self::SUPPORTED, true) ? $default : 'es';
    }
}
