<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP API Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for configuring the Model Context Protocol (MCP) API
    | provided by the Laravel Loop package.
    |
    */
    'sse' => [
        /*
        |--------------------------------------------------------------------------
        | SSE Endpoint Enabled?
        |--------------------------------------------------------------------------
        */
        'enabled' => env('LOOP_SSE_ENABLED', false),

        /*
        |--------------------------------------------------------------------------
        | SSE Endpoint Authentication Middleware
        |--------------------------------------------------------------------------
        |
        | The middleware used to authenticate MCP requests.
        | We recommend using something like Laravel Sanctum here.
        |
        | WARNING: DO NOT LEAVE THIS ENDPOINT ENABLED AND UNPROTECTED IN PRODUCTION.
        */
        'middleware' => ['auth:sanctum'],

        /*
        |--------------------------------------------------------------------------
        | SSE Heartbeat Interval
        |--------------------------------------------------------------------------
        |
        | The interval in seconds at which to send heartbeat comments to keep
        | the connection alive. Set to 0 to disable heartbeats.
        |
        */
        'heartbeat_interval' => env('LOOP_SSE_HEARTBEAT_INTERVAL', 30),
    ],
];
