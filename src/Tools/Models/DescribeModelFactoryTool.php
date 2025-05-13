<?php

namespace Kirschbaum\Loop\Tools\Models;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Prism\Prism\Tool as PrismTool;
use ReflectionClass;
use ReflectionMethod;

class DescribeModelFactoryTool implements Tool
{
    use Makeable;

    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as($this->getName())
            ->for('Lists all available Laravel factories, their models, and public methods (states) to be able to create test data in the application.')
            ->using(function (): string {
                $factoryPath = $this->getFactoryPath();
                $namespace = $this->getFactoryNamespace();

                if (! File::isDirectory($factoryPath)) {
                    return "Factory directory not found at {$factoryPath}.";
                }

                $factoriesInfo = [];
                $files = File::files($factoryPath);

                foreach ($files as $file) {
                    $className = $namespace.$file->getBasename('.php');

                    if (! class_exists($className)) {
                        continue;
                    }

                    try {
                        $reflection = new ReflectionClass($className);

                        if (! $reflection->isSubclassOf(Factory::class) || $reflection->isAbstract()) {
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

                    if (! empty($info['methods'])) {
                        $output .= "  Available States/Methods: \n    - ".implode("\n    - ", $info['methods'])."\n";
                    } else {
                        $output .= "  Available States/Methods: None\n";
                    }
                    $output .= "\n";
                }

                return trim($output);
            });
    }

    public function getName(): string
    {
        return 'laravel_factories_describe';
    }

    protected function getFactoryPath(): string
    {
        // TODO: Allow overriding via config or constructor later if needed
        return database_path('factories');
    }

    protected function getFactoryNamespace(): string
    {
        return 'Database\Factories\\';
    }
}
