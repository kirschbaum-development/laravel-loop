# MCP HTTP+SSE Transport

## Overview

This implementation provides the HTTP+SSE transport option according to the MCP specification.

## How it Works

The SSE transport works as follows:

1. A client establishes a connection to the SSE endpoint (`GET /mcp/sse`)
2. The server responds with an `endpoint` event containing a URI for the client to use for sending messages
3. The client sends JSON-RPC messages as HTTP POST requests to this endpoint
4. The server processes these messages and sends responses back through the SSE connection

## Configuration

To enable the MCP SSE transport in your Laravel application, add the following to your `.env` file:

```
LOOP_SSE_ENABLED=true
LOOP_SSE_PATH=/mcp/sse  # This is the default, can be changed if needed
LOOP_SSE_DRIVER=file    # Default driver, options: "file" or "redis"
```

## SSE Drivers

Laravel Loop supports two storage drivers for maintaining SSE connections:

### File Driver (Default)

The file driver stores session and message data in the local filesystem.

Configuration in `config/loop.php`:

```php
'drivers' => [
    'file' => [
        'storage_dir' => storage_path('app/mcp_sse'),
        'session_ttl' => 86400, // 24 hours in seconds
    ],
],
```

### Redis Driver

The Redis driver stores session and message data in Redis.

To use the Redis driver:

1. Set in your `.env` file:
```
LOOP_SSE_DRIVER=redis
```

2. Configure Redis options in `config/loop.php`:
```php
'drivers' => [
    'redis' => [
        'prefix' => 'sse',               // Redis key prefix for SSE data
        'session_ttl' => 86400,          // Session TTL in seconds (24 hours)
        'connection' => 'default',       // Redis connection from database config
    ],
],
```

3. Ensure Redis is properly configured in your Laravel application's `config/database.php`.

Benefits of using the Redis driver:
- **Performance**: In-memory storage provides faster access than filesystem
- **Reliability**: Redis offers persistence options to prevent data loss
- **Monitoring**: Redis provides tools to monitor usage and performance

## Security Considerations

By default, the SSE endpoint is protected with Laravel Sanctum authentication middleware. You can configure this in the `config/loop.php` file:

```php
'sse' => [
    // ...
    'middleware' => ['auth:sanctum'], // Change this to your preferred authentication middleware
],
```

**WARNING:** Do not leave the SSE endpoint enabled and unprotected in production environments.

## Additional Resources

For more information on the MCP SSE transport specification, see the [official documentation](https://modelcontextprotocol.io/specification/2024-11-05/basic/transports#http-with-sse).
