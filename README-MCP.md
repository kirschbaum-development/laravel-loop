# Laravel Loop - MCP Server

This package provides a Model Context Protocol (MCP) server implementation for the Laravel Loop package.

## Installation

1. Install the package via composer:

```bash
composer require kirschbaum/laravel-loop
```

2. Publish the configuration file:

```bash
php artisan vendor:publish --provider="Kirschbaum\Loop\LoopServiceProvider" --tag="config"
```

## Configuration

After publishing the configuration file, you can edit `config/loop.php` to customize the MCP server:

```php
return [
    // API key for authentication (if empty, no authentication will be required)
    'api_key' => env('LOOP_API_KEY', ''),
    
    // Default AI model to use
    'default_model' => env('LOOP_DEFAULT_MODEL', 'gpt-4o-mini'),
    
    // Whether to enable API key authentication
    'enable_auth' => env('LOOP_ENABLE_AUTH', true),
];
```

## MCP API Endpoints

### Ask a question

```
POST /api/mcp/ask
```

Headers:
```
X-MCP-API-KEY: your-api-key  (if authentication is enabled)
```

Request body:
```json
{
  "message": "Your question here",
  "messages": [
    {
      "user": "User",
      "message": "Previous message from user"
    },
    {
      "user": "AI",
      "message": "Previous response from AI"
    }
  ]
}
```

Response:
```json
{
  "id": 1681234567,
  "created_at": "2023-04-11T12:34:56Z",
  "message": {
    "role": "assistant",
    "content": "AI response here"
  },
  "model": "gpt-4o-mini",
  "usage": {
    "prompt_tokens": 100,
    "completion_tokens": 50,
    "total_tokens": 150
  }
}
```

### Store a message

```
POST /api/mcp/messages
```

Headers:
```
X-MCP-API-KEY: your-api-key  (if authentication is enabled)
```

Request body:
```json
{
  "message": "Your message here",
  "role": "user" // Can be "user", "assistant", or "system"
}
```

Response:
```json
{
  "success": true,
  "message": "Message stored successfully"
}
```

### Get all messages

```
GET /api/mcp/messages
```

Headers:
```
X-MCP-API-KEY: your-api-key  (if authentication is enabled)
```

Response:
```json
{
  "messages": [
    {
      "role": "user",
      "content": "User message"
    },
    {
      "role": "assistant",
      "content": "AI response"
    }
  ]
}
```

### Clear all messages

```
DELETE /api/mcp/messages
```

Headers:
```
X-MCP-API-KEY: your-api-key  (if authentication is enabled)
```

Response:
```json
{
  "success": true,
  "message": "Messages cleared successfully"
}
```

## Using the MCP Client

To connect to this MCP server from a client application, send HTTP requests to the endpoints above with the appropriate headers and request body. 
