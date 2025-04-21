<?php

use Illuminate\Support\Facades\Route;
use Kirschbaum\Loop\Http\Controllers\LoopController;
use Kirschbaum\Loop\Http\Controllers\McpController;

Route::prefix('mcp')
    ->middleware(config('loop.middleware', []))
    ->group(function () {
        Route::get('/', McpController::class);
        Route::post('/', McpController::class);
    });
