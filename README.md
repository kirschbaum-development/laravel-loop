# Laravel Loop

Laravel Loop is an MCP (Model Context Protocol) Server for Laravel. You can use some pre-built tools like exposing your models, creating test data with Laravel Factories or create your own tools. Then, it's just integrate it with your favorite MCP client (Claude Code, Cursor, Windsurf, etc.).

## Installation

You can install the package via composer:

```bash
composer require kirschbaum/laravel-loop
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Kirschbaum\Loop\LoopServiceProvider" --tag="config"
```

## Usage

First, you must register your tools (If you don't know where to put, put in `app/Providers/AppServiceProvider`):

```php
use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\Mode;
use Kirschbaum\Loop\Tools;

Loop::register(Tools\LaravelModelToolkit::make(
    models: [
        \App\Models\User::class,
        \App\Models\Subscription::class,
    ]
));
Loop::register(Tools\StripeToolkit::make());
Loop::register(Tools\LaravelFactoriesToolkit::make());

// Register your custom tool
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

### Custom Tools

You can also create your own tools by extending the `Tool` class and registering it with the `Loop` class.

```php
use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\Mode;
use Kirschbaum\Loop\Tools\StripeTool;
use Kirschbaum\Loop\Tools\LaravelModelTool;
use Kirschbaum\Loop\Tools\FilamentResourceTool;

Loop::registerTool(
    tool: CustomTool::make(
        name: 'custom_tool',
        description: 'This is a custom tool',
        parameters: [
            'name' => 'string',
            'age' => 'integer',
        ],
        execute: function (array $parameters) {
            return sprintf('Hello, %s! You are %d years old.', $parameters['name'], $parameters['age']);
        },
    ),
);
```

### MCP (Model Context Protocol) Server

This package also provides an MCP Server with your tools which you can make it available to your MCP clients (Claude Code, Cursor, etc.).

To add the MCP server to Claude Code, for example, you can use the following command:

```bash
claude mcp add laravel-loop-mcp php /your/full/path/to/laravel/artisan loop:mcp:start
```

To generate an API key, you must create a new Laravel Sanctum API token.