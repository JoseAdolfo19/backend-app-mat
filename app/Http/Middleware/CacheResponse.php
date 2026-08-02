<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $content = $response->getContent();
            $etag = '"' . md5($content) . '"';

            $response->headers->set('ETag', $etag);
            $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
            $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s', time()) . ' GMT');

            if ($request->headers->get('If-None-Match') === $etag) {
                return response()->setNotModified();
            }
        }

        return $response;
    }
}
