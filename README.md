# Laravel Loop

Laravel Loop is an MCP (Model Context Protocol) Server for Laravel. You can use some pre-built tools like exposing your models, creating test data with Laravel Factories or create your own tools. Then, it's just integrate it with your favorite MCP client (Claude Code, Cursor, Windsurf, etc.).

## Installation

You can install the package via composer:

```bash
composer require kirschbaum/laravel-loop
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="loop-config"
```

## Usage

First, you must register your tools (If you don't know where to put, put in `app/Providers/AppServiceProvider`). The package provides some pre-built tools:

```php
use Illuminate\Support\ServiceProvider;
use Kirschbaum\Loop\Facades\Loop;
use Kirschbaum\Loop\Toolkits;
use Kirschbaum\Loop\Tools;

Loop::toolkit(Toolkits\FilamentToolkit::make());
Loop::toolkit(Toolkits\LaravelModelToolkit::make(
    models: [
        \App\Models\User::class,
        \App\Models\Subscription::class,
    ]
));
Loop::toolkit(Toolkits\LaravelFactoriesToolkit::make());
Loop::tool(Tools\StripeTool::make());
```

But, the power comes from your custom tools.

```php
use Kirschbaum\Loop\Facades\Loop;
use Kirschbaum\Loop\Tools\CustomTool;

Loop::tool(
    CustomTool::make(
        name: 'custom_tool',
        description: 'This is a custom tool',
        parameters: [
            'name' => ['type' => 'string', 'description' => 'The name of the user', 'required' => true],
            'age' => ['type' => 'integer', 'description' => 'The age of the user'],
        ],
        execute: function (string $name, ?int $age = null) {
            return sprintf('Hello, %s! You are %d years old.', $name, $age ?? 'unknown');
        },
    ),
);
```

You can also build your own tool classes by ...

```php
use Kirschbaum\Loop\Loop;

```

## MCP (Model Context Protocol) Server

This package provides an MCP Server with your tools which you can make available to your MCP clients (Claude Code, Cursor, etc.).

#### CLI/STDIO Interface

To connect Laravel Loop MCP server to Claude Code, for example, you can use the following command:

```bash
claude mcp add laravel-loop-mcp php /your/full/path/to/laravel/artisan loop:mcp:start

# with an authenticated user
claude mcp add laravel-loop-mcp php /your/full/path/to/laravel/artisan loop:mcp:start --user-id=1 --user-model=App\Models\User

# with debug mode
claude mcp add laravel-loop-mcp php /your/full/path/to/laravel/artisan loop:mcp:start --debug
```

To add to Cursor, or any (most?) MCP clients with a config file:

```bash
{
  "mcpServers": {
    "laravel-loop-mcp": {
      "command": "php",
      "args": [
        "/your/full/path/to/laravel/artisan",
        "loop:mcp:start",
        "--user-id=1"
      ]
    }
  }
}
```

### SSE

Coming soon.

## Roadmap

- [ ] Add a chat component to the package, so you can use the tools inside the application without an MCP client.
- [ ] Refine the existing tools
- [ ] Add write capabilities to the existing tools
- [ ] Add tests
=======
#### HTTP Server-Sent Events (SSE) Interface

For web-based clients or HTTP-native tools, you can use the SSE endpoint:

```
POST /mcp/sse
```

This endpoint accepts MCP requests via POST with `Content-Type: application/json` and streams back responses using the `text/event-stream` format (SSE).

##### Example using curl:

```bash
curl -N -X POST -H "Content-Type: application/json" \
     -H "Accept: text/event-stream" \
     -d '{"jsonrpc": "2.0", "method": "ping", "id": 1}' \
     http://your-app.test/mcp/sse
```

Expected output:

```
id: 1
event: mcp_response
data: {"jsonrpc":"2.0","id":1,"result":{}}

```

### Authentication

To generate an API key, you must create a new Laravel Sanctum API token. This API key can be used with both the CLI and HTTP SSE interfaces.
