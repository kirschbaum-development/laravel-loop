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
        | Default Model
        |--------------------------------------------------------------------------
        |
        | The default AI model to use for MCP requests.
        |
        */
        'default_model' => env('LOOP_DEFAULT_MODEL', 'gpt-4o-mini'),

        /*
        |--------------------------------------------------------------------------
        | Enable API Authentication
        |--------------------------------------------------------------------------
        |
        | Whether to enable API key authentication for MCP requests.
        |
        */
        'enable_auth' => env('LOOP_ENABLE_AUTH', true),

        /*
        |--------------------------------------------------------------------------
        | Server-Sent Events (SSE) Configuration
        |--------------------------------------------------------------------------
        |
        | Configuration for the HTTP SSE endpoint.
        |
        */
        'sse' => [
            /*
            |--------------------------------------------------------------------------
            | Enable SSE Endpoint
            |--------------------------------------------------------------------------
            |
            | Whether to enable the SSE endpoint for MCP requests.
            |
            */
            'enabled' => env('LOOP_SSE_ENABLED', true),

            /*
            |--------------------------------------------------------------------------
            | SSE-specific Middleware
            |--------------------------------------------------------------------------
            |
            | Additional middleware to apply only to the SSE endpoint.
            | This can be useful for specific CORS or rate-limiting settings.
            |
            */
            'middleware' => [],

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
