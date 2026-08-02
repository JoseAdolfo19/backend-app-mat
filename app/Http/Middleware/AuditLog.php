<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditLog
{
    private const LOGGED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!in_array($request->method(), self::LOGGED_METHODS)) {
            return $response;
        }

        try {
            DB::table('audit_logs')->insert([
                'id' => Str::uuid(),
                'user_id' => $request->user()?->id,
                'action' => strtolower($request->method()) . '.' . str_replace('/', '.', $request->path()),
                'auditable_type' => '',
                'auditable_id' => null,
                'old_values' => null,
                'new_values' => null,
                'method' => $request->method(),
                'path' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr($request->userAgent() ?? '', 0, 500),
                'platform' => $request->header('X-Platform', 'unknown'),
                'status_code' => $response->getStatusCode(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            report($e);
        }

        return $response;
    }
}
