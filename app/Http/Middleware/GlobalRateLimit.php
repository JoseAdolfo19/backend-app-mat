<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class GlobalRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $maxAttempts = 60;
        $decayMinutes = 1;
        $limiterKey = $this->resolveLimiterKey($request);

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($limiterKey);

            return response()->json([
                'message' => 'Demasiadas solicitudes. Intenta de nuevo en ' . $retryAfter . ' segundos.',
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($limiterKey, $decayMinutes * 60);

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => RateLimiter::remaining($limiterKey, $maxAttempts),
        ]);
    }

    private function resolveLimiterKey(Request $request): string
    {
        $userId = $request->user()?->id;
        $ip = $request->ip();

        if ($userId) {
            return 'global_api_token_' . $userId;
        }

        return 'global_api_ip_' . $ip;
    }
}
