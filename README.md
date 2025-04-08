# Laravel Loop

Laravel Loop is a package that allows you to talk to your Laravel application and data, using AI. It also exposes an MCP Server to allow you to connect to your LLM or tool of preference.

## Installation

```bash
composer require kirschbaum-development/laravel-loop
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

```php
use Kirschbaum\Loop\Loop;
use Kirschbaum\Loop\Mode;
use Kirschbaum\Loop\Tools\StripeTool;
use Kirschbaum\Loop\Tools\LaravelModelTool;
use Kirschbaum\Loop\Tools\FilamentResourceTool;

Loop::registerToolTool(
    tool: LaravelModelTool::make(
        mode: Mode::READ_ONLY,
        models: [User::class, Project::class, Allocation::class],
        context: 'Users have many projects and allocations. Projects have many allocations and time entries. Allocations have many time entries.',
    ),
);

// Registering Laravel models as tools
Loop::registerTool(
    tool: LaravelModelTool::make(
        mode: Mode::READ_WRITE,
        models: [TimeEntry::class],
    ),
);

// Registering Filament resources as tools
Loop::registerTool(
    tool: FilamentResourceTool::make(
        mode: Mode::READ_ONLY,
        models: [AllocationResource::class],
    ),
);

// Registering the built-in Stripe tool
Loop::registerTool(
    tool: StripeTool::make(
        mode: Mode::READ_ONLY,
    ),
);
```

**Custom Tools**

You can also create your own tools by extending the `Tool` class and registering it with the `Loop` class.

```php
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

