<?php

use Illuminate\Support\Facades\Route;
use Kirschbaum\Loop\Http\Controllers\McpController;
use Kirschbaum\Loop\Http\Controllers\LoopController;
use Kirschbaum\Loop\Http\Middleware\SseEnabledMiddleware;

Route::prefix('mcp')
    ->middleware([SseEnabledMiddleware::class] + config('loop.sse.middleware', []))
    ->group(function () {
        Route::get('/', McpController::class);
        Route::post('/', McpController::class);
    });
