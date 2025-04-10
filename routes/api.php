<?php

use Illuminate\Support\Facades\Route;
use Kirschbaum\Loop\Http\Controllers\LoopController;
use Kirschbaum\Loop\Http\Controllers\McpController;

Route::prefix('mcp')
    // ->middleware(['loop.api'])
    ->group(function () {
        Route::get('/', McpController::class);
        Route::post('/', McpController::class);

        // Route::post('/messages', [LoopController::class, 'storeMessage']);
        // Route::get('/messages', [LoopController::class, 'getMessages']);
        // Route::delete('/messages', [LoopController::class, 'clearMessages']);
    });
