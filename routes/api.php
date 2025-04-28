<?php

use Illuminate\Support\Facades\Route;
use Kirschbaum\Loop\Http\Controllers\McpController;
use Kirschbaum\Loop\Http\Controllers\McpSseController;
use Kirschbaum\Loop\Http\Middleware\SseEnabledMiddleware;

Route::prefix('mcp')
    ->middleware([SseEnabledMiddleware::class] + config('loop.sse.middleware', []))
    ->group(function () {
        Route::get('/', McpController::class);
        Route::post('/', McpController::class);

        // SSE endpoint
        if (config('loop.sse.enabled', true)) {
            Route::post('/sse', McpSseController::class)
                ->middleware(config('loop.sse.middleware', []))
                ->name('mcp.sse');
        }
    });
