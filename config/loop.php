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

    /*
    |--------------------------------------------------------------------------
    | Authentication Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware used to authenticate MCP requests.
    | We recommend using something like Sanctum here.
    */
    'middleware' => [],

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
];
