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
use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\Mode;
use Kirschbaum\Loop\Tools;

Loop::register(Tools\FilamentToolkit::make());
Loop::register(Tools\LaravelModelToolkit::make(
    models: [
        \App\Models\User::class,
        \App\Models\Subscription::class,
    ]
));
Loop::register(Tools\StripeToolkit::make());
Loop::register(Tools\LaravelFactoriesToolkit::make());
```

But, the power comes from your custom tools.

```php
Loop::register(
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