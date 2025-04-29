<?php

use Illuminate\Support\Facades\Route;
use Kirschbaum\Loop\Http\Controllers\McpController;
use Kirschbaum\Loop\Http\Middleware\SseEnabledMiddleware;

Route::prefix('mcp')
    ->middleware(array_merge([SseEnabledMiddleware::class], (array) config('loop.sse.middleware', [])))
    ->group(function () {
        Route::get('/', McpController::class);
        Route::post('/', McpController::class);
    });
