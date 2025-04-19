# Laravel Loop

This package provides AI-powered functionality for Laravel applications, including a Model Context Protocol (MCP) server implementation.

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

First, you must register your tools:

```php
use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\Mode;

Loop::toolkit(StripeToolkit::make());
Loop::toolkit(LaravelModelToolkit::make(
    models: [
        \App\Models\User::class,
        \App\Models\Subscription::class,
    ]
));

// Register your custom tool
Loop::registerTool(
    tool: CustomTool::make(
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
claude mcp add laravel-loop-mcp npx opencontrol https://your-url.com api-key
```

To generate an API key, you must create a new Laravel Sanctum API token.