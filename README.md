# Laravel Loop

Laravel Loop is a powerful Model Context Protocol (MCP) server designed specifically for Laravel applications. It connects your Laravel application with AI assistants using MCP.

## What It Does

Laravel Loop allows you to:

- Create and expose your own tools directly integrated with your Laravel application
- Connect with MCP clients like Claude Code, Cursor, Windsurf, and more

It also ships with some pre-built tools:

- Expose your data through Laravel Models using our pre-built toolkit (`LaravelModelToolkit`)
- Expose your Filament Resources (`FilamentToolkit`)
- Generate test data using Laravel Factories (`LaravelFactoriesToolkit`)
- Talk with the Stripe API (`StripeTool`)

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
        handler: function (string $name, ?int $age = null) {
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

### STDIO

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