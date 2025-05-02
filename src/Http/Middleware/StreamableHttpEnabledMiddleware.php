<?php

namespace Kirschbaum\Loop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StreamableHttpEnabledMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('loop.streamable_http.enabled')) {
            return response('Not Found', 404);
        }

        return $next($request);
    }
}
