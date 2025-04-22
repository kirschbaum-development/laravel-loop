<?php

namespace Kirschbaum\Loop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class SseEnabledMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('loop.sse.enabled')) {
            return response('Not Found', 404);
        }

        return $next($request);
    }
}
