<?php

use Illuminate\Support\Facades\Route;
use Kirschbaum\Loop\Http\Controllers\McpController;
use Kirschbaum\Loop\Http\Middleware\StreamableHttpEnabledMiddleware;

$path = config('loop.streamable_http.path', '/mcp');
$streamableHttpEnabled = config('loop.streamable_http.enabled', false);

if ($streamableHttpEnabled) {
    Route::prefix($path)
        ->middleware([
            StreamableHttpEnabledMiddleware::class,
            ...config('loop.sse.middleware', []),
        ])
        ->group(function () {
            Route::post('/', McpController::class);
        });
}
