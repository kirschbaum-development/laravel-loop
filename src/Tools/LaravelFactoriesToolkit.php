<?php

namespace Kirschbaum\Loop\Tools;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Prism\Prism\Facades\Tool as PrismTool;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Prism\Prism\Schema\StringSchema;
use Throwable;

class LaravelFactoriesToolkit implements Toolkit
{
    protected string $name = "laravel_factories_toolkit";
    public string $description = "Provides tools to interact with Laravel factories for creating test data.";

    public function __construct(
        ?string $description = null,
    ) {
        $this->description = $description ?? $this->description;
    }

    public static function make(...$args): static
    {
        return new self(...$args);
    }

    public function getTools(): Collection
    {
        return collect([
            $this->getFactoriesDescribeTool(),
            $this->getFactoriesCreateTool(),
        ]);
    }

    public function getTool(string $name): ?\Prism\Prism\Tool
    {
        return match ($name) {
            'laravel_factories_describe' => $this->getFactoriesDescribeTool(),
            'laravel_factories_create' => $this->getFactoriesCreateTool(),
            default => null,
        };
    }

    protected function getFactoryPath(): string
    {
        // Allow overriding via config or constructor later if needed
        return database_path('factories');
    }

    protected function getFactoryNamespace(): string
    {
        return 'Database\Factories\\';
    }

    public function getFactoriesDescribeTool(): \Prism\Prism\Tool
    {
        return PrismTool::as('laravel_factories_describe')
            ->for('Lists all available Laravel factories, their models, and public methods (states) to be able to create test data in the application.')
            ->using(function (): string {
                $factoryPath = $this->getFactoryPath();
                $namespace = $this->getFactoryNamespace();

                if (!File::isDirectory($factoryPath)) {
                    return "Factory directory not found at {$factoryPath}.";
                }

                $factoriesInfo = [];
                $files = File::files($factoryPath);

                foreach ($files as $file) {
                    $className = $namespace . $file->getBasename('.php');

                    if (!class_exists($className)) {
                        continue;
                    }

                    try {
                        $reflection = new ReflectionClass($className);
                        if (!$reflection->isSubclassOf(Factory::class) || $reflection->isAbstract()) {
                            continue;
                        }

                        $factoryInstance = app($className); // Resolve instance to get model
                        $model = $factoryInstance->modelName();
                        $methods = collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
                            ->map(fn (ReflectionMethod $method) => $method->getName())
                            // Filter out common base/magic methods
                            ->reject(function (string $name) {
                                $baseMethods = [
                                    '__construct', 'new', 'times', 'create', 'make',
                                    'configure', 'modelName', 'definition', 'state', 'afterMaking', 'afterCreating',
                                    'createMany', 'makeMany', 'factoryForModel', 'guessModelNamesUsing',
                                    'guessFactoryNamesUsing', 'useNamespace', 'lazy', 'count',
                                    'connection', 'recycle', 'sequence', 'has', 'for',
                                ];
                                return in_array($name, $baseMethods, true) || Str::startsWith($name, '__');
                            })
                            ->sort()
                            ->values()
                            ->all();

                        $factoriesInfo[$className] = [
                            'model' => $model,
                            'methods' => $methods,
                        ];
                    } catch (BindingResolutionException|\ReflectionException $e) {
                        // Could not reflect or instantiate, log and skip
                        Log::warning("Could not reflect or instantiate factory {$className}: {$e->getMessage()}");
                        continue;
                    }
                }

                if (empty($factoriesInfo)) {
                    return "No factories found in {$factoryPath}.";
                }

                // Format the output
                $output = "Available Laravel Factories:\n\n";
                foreach ($factoriesInfo as $factoryClass => $info) {
                    $shortName = Arr::last(explode('\\', $factoryClass));
                    $output .= "- Factory: {$shortName} ({$factoryClass})\n";
                    $output .= "  Model: {$info['model']}\n";
                    if (!empty($info['methods'])) {
                        $output .= "  Available States/Methods: \n    - " . implode("\n    - ", $info['methods']) . "\n";
                    } else {
                        $output .= "  Available States/Methods: None\n";
                    }
                    $output .= "\n";
                }

                return trim($output);
            });
    }

    // Placeholder for the next tool
    public function getFactoriesCreateTool(): \Prism\Prism\Tool
    {
        return PrismTool::as('laravel_factories_create')
            ->for('Creates one or more model instances of test data using a specified Laravel factory, optionally applying states and overriding attributes.')
            ->withStringParameter('factoryName', 'The short name (e.g., UserFactory) or fully qualified class name of the factory to use.')
            ->withNumberParameter('count', 'The number of models to create.', required: true)
            ->withArrayParameter('states', 'An array of state names (methods) to apply to the factory.', new StringSchema('state', 'The name of the state to apply to the factory.'), required: true)
            ->withObjectParameter(
                'attributes',
                'An object of attribute key-value pairs to override the default factory attributes.',
                [
                    new StringSchema('attribute', 'The name of the attribute to override.'),
                    new StringSchema('value', 'The value to set for the attribute.'),
                ],
                required: true
            )
            ->using(function (string $factoryName, int $count, array $states, array $attributes): string {
                $factoryIdentifier = $factoryName;
                $count = max(1, $count);
                $states = (array) $states;
                $attributes = (array) $attributes;

                $factoryClass = $this->findFactoryClass($factoryIdentifier);

                if (!$factoryClass) {
                    return "Error: Factory '{$factoryIdentifier}' not found.";
                }

                try {
                    $factory = $factoryClass::new();

                    foreach ($states as $state) {
                        if (method_exists($factory, $state)) {
                            $factory = $factory->$state();
                        } else {
                            return "Error: State '{$state}' not found on factory {$factoryClass}.";
                        }
                    }

                    // Apply count
                    if ($count > 1) {
                        $factory = $factory->count($count);
                    }

                    // Create or Make models
                    $action = 'create';
                    $models = $factory->$action($attributes);

                    $modelClass = $factory->modelName();
                    $resultCount = $models instanceof Collection ? $models->count() : 1;
                    $ids = $models instanceof Collection ? $models->pluck('id')->implode(', ') : ($models->id ?? 'N/A');
                    $persistenceMessage = 'created and saved';

                    return sprintf(
                        "Successfully %s %d instance(s) of %s using %s. %s",
                        $persistenceMessage,
                        $resultCount,
                        $modelClass,
                        basename(str_replace('\\', '/', $factoryClass)),
                        "IDs: [{$ids}]"
                    );

                } catch (InvalidArgumentException $e) {
                    Log::error("Factory creation error for {$factoryClass}: " . $e->getMessage());
                    return "Error: Invalid arguments provided for factory {$factoryClass}. Check attribute names and types. Details: " . $e->getMessage();
                } catch (Throwable $e) {
                    Log::error("Factory creation failed for {$factoryClass}: " . $e->getMessage(), ['exception' => $e]);
                    return "Error: Failed to {$action} models using factory {$factoryClass}. Details: " . $e->getMessage();
                }
            });
    }

    protected function findFactoryClass(string $identifier): ?string
    {
        $namespace = $this->getFactoryNamespace();

        // Check if it's already a fully qualified class name
        if (class_exists($identifier) && is_subclass_of($identifier, Factory::class)) {
            return $identifier;
        }

        // Assume it's a short name and prepend namespace
        $potentialClass = $namespace . Str::finish($identifier, 'Factory');
        if (class_exists($potentialClass) && is_subclass_of($potentialClass, Factory::class)) {
            return $potentialClass;
        }

        // Try without appending Factory suffix if already present
        $potentialClass = $namespace . $identifier;
        if (class_exists($potentialClass) && is_subclass_of($potentialClass, Factory::class)) {
            return $potentialClass;
        }

        // Last attempt: search all loaded classes (less efficient)
        // This might be needed if factories are in a non-standard namespace
        // Note: This is basic; a more robust solution might scan composer classmap
        /* // Consider adding this back if needed, but it can be slow
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, Factory::class) && Str::endsWith($class, '\\' . $identifier)) {
                return $class;
            }
        }
        */

        return null; // Not found
    }
}
