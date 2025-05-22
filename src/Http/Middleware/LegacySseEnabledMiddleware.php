<?php

namespace Kirschbaum\Loop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacySseEnabledMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('loop.sse.enabled')) {
            return response('Not Found', 404);
        }

        return $next($request);
    }
}
