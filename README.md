# Laravel Loop

Laravel Loop is a powerful Model Context Protocol (MCP) server designed specifically for Laravel applications. It connects your Laravel application with AI assistants using MCP.

Laravel Loop uses [Prism](https://github.com/prism-ai/prism) behind the scenes to build the tools.

> [!IMPORTANT]
> Laravel Loop is still in development and this is a beta version.

## What It Does

Laravel Loop allows you to:

- Create and expose your own tools directly integrated with your Laravel application
- Connect with MCP clients like Claude Code, Cursor, Windsurf, and more

It also ships with some pre-built tools (we are planning to add more and refine the existing ones):

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

You can also build your own tool classes. Each tool must implement the `Tool` contract, and return a `Prism\Prism\Tool` instance in the `build` method.

```php
use Kirschbaum\Loop\Contracts\Tool;

class HelloTool implements Tool
{
    use \Kirschbaum\Loop\Concerns\Makeable;

    public function build(): \Prism\Prism\Tool
    {
        return app(\Prism\Prism\Tool::class)
            ->as($this->getName())
            ->for('Says hello to the user')
            ->withStringParameter('name', 'The name of the user to say hello to.', required: true)
            ->using(fn (string $name) => "Hello, $name!");
    }

    public function getName(): string
    {
        return 'hello';
    }
}
```

If you want to provide multiple similar tools, you can build a toolkit which returns a collection of tools.

```php
use Kirschbaum\Loop\Collections\ToolCollection;
use Kirschbaum\Loop\Contracts\Toolkit;

class LaravelFactoriesToolkit implements Toolkit
{
    use \Kirschbaum\Loop\Concerns\Makeable;

    public function getTools(): ToolCollection
    {
        return new ToolCollection([
            HelloTool::make(),
            GoodbyeTool::make(),
        ]);
    }
}
```

***

## Connecting to the MCP server

The MCP protocol has two main ways to connect: STDIO and Streamable HTTP.

### STDIO

To run the MCP server using STDIO, you must run the following command:

```bash
php artisan loop:mcp:start
```

To connect Laravel Loop MCP server to Claude Code, for example, you can use the following command:

```bash
claude mcp add laravel-loop-mcp php /your/full/path/to/laravel/artisan loop:mcp:start

# with an authenticated user
claude mcp add laravel-loop-mcp php /your/full/path/to/laravel/artisan loop:mcp:start --user-id=1 --user-model=App\Models\User

# with debug mode
claude mcp add laravel-loop-mcp php /your/full/path/to/laravel/artisan loop:mcp:start --debug
```

To add to Cursor, or any MCP clients with a JSON config file:

```json
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

### Streamable HTTP Transport with SSE

Laravel Loop supports the [streamable HTTP transport](https://modelcontextprotocol.io/specification/2025-03-26/basic/transports) for the MCP protocol, which includes SSE capabilities for client-initiated requests (POST).

> [!IMPORTANT]
> NOTE: The Streamable HTTP transport is new and not yet supported by all MCP clients.

To enable the Streamable HTTP transport, update your `.env` file:

```bash
LOOP_STREAMABLE_HTTP_ENABLED=true
```

This will expose an MCP endpoint at `/mcp` that supports both JSON-RPC and Server-Sent Events. The endpoint is protected by Laravel Sanctum by default.

See [HTTP Streaming Documentation](docs/http-streaming.md) for more details on configuration, and usage.

## Roadmap

- [ ] Add a chat component to the package, so you can use the tools inside the application without an MCP client.
- [ ] Refine the existing tools
- [ ] Add write capabilities to the existing tools
- [ ] Add tests

## Security

If you discover any security related issues, please email security@kirschbaumdevelopment.com instead of using the issue tracker.

## Sponsorship

Development of this package is sponsored by Kirschbaum Development Group, a developer driven company focused on problem solving, team building, and community. Learn more [about us](https://kirschbaumdevelopment.com?utm_source=github) or [join us](https://careers.kirschbaumdevelopment.com?utm_source=github)!

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.