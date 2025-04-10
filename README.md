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

## Dependencies for Stripe Tool

To use the Stripe API tool, ensure you have the following packages installed:

```bash
composer require stripe/stripe-php
composer require guzzlehttp/guzzle
```

Also, make sure to add your Stripe API key to your `.env` file:

```
STRIPE_KEY=your_stripe_publishable_key
STRIPE_SECRET=your_stripe_secret_key
```

And update your `config/services.php` to include:

```php
'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
],
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

This package also provides a Model Context Protocol server implementation. This allows you to interact with the AI assistant through REST API endpoints.

#### Configuration

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

#### API Endpoints

The following API endpoints are available:

##### Ask a question

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

##### Manage Conversation History

The MCP server also provides endpoints to manage conversation history:

- `POST /api/mcp/messages`: Store a new message
- `GET /api/mcp/messages`: Get all messages
- `DELETE /api/mcp/messages`: Clear all messages

For more detailed information on how to use these endpoints, see [README-MCP.md](README-MCP.md).

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

