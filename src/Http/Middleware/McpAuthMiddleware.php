<?php

namespace Kirschbaum\Loop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class McpAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-MCP-API-KEY');
        $configApiKey = Config::get('loop.api_key');

        // Skip authentication if no API key is configured
        if (empty($configApiKey)) {
            return $next($request);
        }

        // Check if API key is valid
        if ($apiKey !== $configApiKey) {
            return response()->json([
                'error' => 'Unauthorized. Invalid API key.',
            ], 401);
        }

        return $next($request);
    }
}
