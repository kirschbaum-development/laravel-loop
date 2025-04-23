<?php

namespace Kirschbaum\Loop\Tools\Models;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Contracts\Toolkit;
use Prism\Prism\Tool as PrismTool;
use Prism\Prism\Schema\StringSchema;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class CreateModelFactoryTool implements Tool
{
    use Makeable;

    protected function getFactoryPath(): string
    {
        // Allow overriding via config or constructor later if needed
        return database_path('factories');
    }

    protected function getFactoryNamespace(): string
    {
        return 'Database\Factories\\';
    }

    // Placeholder for the next tool
    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as($this->getName())
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

    public function getName(): string
    {
        return 'laravel_factories_create';
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
