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
    ],
];
