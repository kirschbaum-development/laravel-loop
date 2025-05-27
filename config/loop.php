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
    'streamable_http' => [
        /*
        |--------------------------------------------------------------------------
        | Streamable HTTP Endpoint Enabled?
        |--------------------------------------------------------------------------
        */
        'enabled' => env('LOOP_STREAMABLE_HTTP_ENABLED', false),

        /*
        |--------------------------------------------------------------------------
        | Streamable HTTP Endpoint Path
        |--------------------------------------------------------------------------
        |
        | The path where the MCP HTTP endpoint will be available.
        |
        */
        'path' => env('LOOP_STREAMABLE_HTTP_PATH', '/mcp'),

        /*
        |--------------------------------------------------------------------------
        | Streamable HTTP Endpoint Authentication Middleware
        |--------------------------------------------------------------------------
        |
        | The middleware used to authenticate MCP requests.
        | We recommend using something like Laravel Sanctum here.
        |
        | WARNING: DO NOT LEAVE THIS ENDPOINT ENABLED AND UNPROTECTED IN PRODUCTION.
        */
        'middleware' => ['auth:sanctum'],
    ],

    'sse' => [
        /*
        |--------------------------------------------------------------------------
        | HTTP + SSE Transport Enabled
        |--------------------------------------------------------------------------
        |
        | Determines whether SSE is enabled for the application.
        |
        */
        'enabled' => env('LOOP_SSE_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | SSE Driver
        |--------------------------------------------------------------------------
        |
        | This option controls the default SSE driver that will be used for
        | maintaining server-sent events connections. Supported drivers: "file", "redis".
        |
        */
        'driver' => env('LOOP_SSE_DRIVER', 'file'),

        /*
        |--------------------------------------------------------------------------
        | SSE Path
        |--------------------------------------------------------------------------
        |
        | The base path for SSE routes.
        |
        */
        'path' => env('LOOP_SSE_PATH', '/mcp/sse'),

        /*
        |--------------------------------------------------------------------------
        | SSE Middleware
        |--------------------------------------------------------------------------
        |
        | The middleware used to authenticate MCP requests.
        | We recommend using something like Laravel Sanctum here.
        |
        | WARNING: DO NOT LEAVE THIS ENDPOINT ENABLED AND UNPROTECTED IN PRODUCTION.
        |
        */
        'middleware' => ['auth:sanctum'],

        /*
        |--------------------------------------------------------------------------
        | SSE Drivers
        |--------------------------------------------------------------------------
        |
        | Configuration for each SSE driver.
        |
        */
        'drivers' => [
            'file' => [
                'storage_dir' => storage_path('app/mcp_sse'),
                'session_ttl' => 86400, // 24 hours in seconds
            ],

            'redis' => [
                'prefix' => 'sse',
                'session_ttl' => 86400, // 24 hours in seconds
                'connection' => 'default',
            ],
        ],
    ],
];
