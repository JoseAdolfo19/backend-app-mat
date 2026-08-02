<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    private const ALLOWED_METHODS = 'GET, POST, PUT, DELETE, PATCH, OPTIONS';
    private const ALLOWED_HEADERS = 'Content-Type, Authorization, X-Requested-With, Accept, X-Platform';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $request->getMethod() === 'OPTIONS'
            ? response('', 204)
            : $next($request);

        $origin = $request->headers->get('Origin', '');

        // No setear headers CORS si no hay Origin (app móvil no envía Origin)
        if (empty($origin)) {
            return $response;
        }

        $allowedOrigins = array_map('trim', explode(',', config('app.cors_origins', 'http://localhost:5173,http://localhost:8000')));

        if (in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', self::ALLOWED_METHODS);
            $response->headers->set('Access-Control-Allow-Headers', self::ALLOWED_HEADERS);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '86400');
        }

        return $response;
    }
}
