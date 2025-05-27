# Laravel Loop HTTP Streaming

Laravel Loop provides a streamable HTTP transport for the Model Context Protocol (MCP) that supports client-initiated requests (POST). The MCP HTTP transport is able to process JSON and SSE streaming responses on a single endpoint.

## Configuration

To enable and configure the HTTP MCP endpoint, update your `.env` file and/or `config/loop.php`:

```php
# Enable the HTTP MCP endpoint
LOOP_STREAMABLE_HTTP_ENABLED=true

# Set the endpoint path (default: /mcp)
LOOP_STREAMABLE_HTTP_PATH=/mcp
```

### Security Considerations

The MCP HTTP endpoint should be protected with proper authentication. By default, it uses Laravel Sanctum, but you can configure the middleware stack in `config/loop.php`:

```php
'middleware' => ['auth:sanctum'],
```

## Client-Initiated Messages (POST)

Clients can send JSON-RPC 2.0 messages to the MCP server by making HTTP POST requests to the configured endpoint.

### Single Request

```bash
curl -X POST http://your-app.test/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"clientInfo":{"name":"Test Client"},"capabilities":{},"protocolVersion":"2024-11-05"}}'
```

### Batch Requests

```bash
curl -X POST http://your-app.test/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '[{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"clientInfo":{"name":"Test Client"},"capabilities":{},"protocolVersion":"2024-11-05"}},{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}]'
```

### SSE Streaming Responses

Clients can request SSE responses for batch processing by setting the `Accept` header to include `text/event-stream`:

```bash
curl -X POST http://your-app.test/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: text/event-stream" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '[{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"clientInfo":{"name":"Test Client"},"capabilities":{},"protocolVersion":"2024-11-05"}},{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}]'
```

The server will respond with an SSE stream containing each response as a separate event.
