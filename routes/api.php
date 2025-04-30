<?php

use Illuminate\Support\Facades\Route;
use Kirschbaum\Loop\Http\Controllers\McpController;
use Kirschbaum\Loop\Http\Controllers\McpSseController;

Route::prefix('mcp')
    ->group(function () {
        Route::get('/', McpController::class);
        Route::post('/', McpController::class);

        if (config('loop.sse.enabled')) {
            Route::post('/sse', McpSseController::class)
                ->middleware(config('loop.sse.middleware'));
        }
    });
