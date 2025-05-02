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
];
