<?php

use Illuminate\Support\Facades\Route;
use Kirschbaum\Loop\Http\Controllers\McpController;
use Kirschbaum\Loop\Http\Controllers\McpSSEController;
use Kirschbaum\Loop\Http\Middleware\LegacySseEnabledMiddleware;
use Kirschbaum\Loop\Http\Middleware\StreamableHttpEnabledMiddleware;

/*
|--------------------------------------------------------------------------
| Streamable Http Endpoint
|--------------------------------------------------------------------------
*/
$path = config('loop.streamable_http.path', '/mcp');
$streamableHttpEnabled = config('loop.streamable_http.enabled', false);

Route::prefix($path)
    ->middleware([
        StreamableHttpEnabledMiddleware::class,
        ...config('loop.streamable_http.middleware', []),
    ])
    ->group(function () {
        Route::post('/', McpController::class);
    });

/*
|--------------------------------------------------------------------------
| HTTP + SSE Endpoint (Deprecated)
|--------------------------------------------------------------------------
*/
$ssePath = config('loop.sse.path', '/mcp/sse');
$sseEnabled = config('loop.sse.enabled', false);

Route::prefix($ssePath)
    ->middleware([
        LegacySseEnabledMiddleware::class,
        ...config('loop.sse.middleware', []),
    ])
    ->group(function () {
        Route::get('/', [McpSSEController::class, 'connect']);
        Route::post('message', [McpSSEController::class, 'message']);
    });
