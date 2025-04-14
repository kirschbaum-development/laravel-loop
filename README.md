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

### Loop AI Assistant

You can use the Loop AI assistant in your application code:

```php
use Kirschbaum\Loop\Loop;

public function __construct(Loop $loop)
{
    $this->loop = $loop;
}

public function askAi(string $question)
{
    $messages = collect([
        ['user' => 'User', 'message' => 'What is Laravel?'],
        ['user' => 'AI', 'message' => 'Laravel is a PHP web framework...'],
    ]);
    
    $response = $this->loop->ask($question, $messages);
    
    return (string) $response;
}
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

To run the MCP server, you can use the following command:

```bash
npx opencontrol https://your-url.com api-key
```

To generate an API key, you must create a new Laravel Sanctum API token.