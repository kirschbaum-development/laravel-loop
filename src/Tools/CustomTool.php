<?php

namespace Kirschbaum\Loop\Tools;

use Closure;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool as PrismTool;

class CustomTool implements Tool
{
    use Makeable;

    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly Closure $handler,
    ) {}

    public function build(): PrismTool
    {
        $tool = app(PrismTool::class)
            ->as($this->getName())
            ->for($this->description);

        $this->buildParameters($tool);

        return $tool->using($this->handler);
    }

    private function buildParameters(PrismTool $tool): void
    {
        foreach ($this->parameters as $name => $config) {
            $type = $config['type'] ?? 'string';
            $description = $config['description'] ?? '';
            $required = $config['required'] ?? false;

            match ($type) {
                'string' => $tool->withStringParameter($name, $description, required: $required),
                'integer', 'number' => $tool->withNumberParameter($name, $description, required: $required),
                'boolean' => $tool->withBooleanParameter($name, $description, required: $required),
                'object' => $this->buildObjectParameter($tool, $name, $config),
                default => $tool->withStringParameter($name, $description, required: $required),
            };
        }
    }

    private function buildObjectParameter(PrismTool $tool, string $name, array $config): void
    {
        $description = $config['description'] ?? '';
        $required = $config['required'] ?? false;
        $properties = $config['properties'] ?? [];
        $requiredFields = $config['required_fields'] ?? [];
        $allowAdditional = $config['allow_additional_properties'] ?? false;

        $schemaProperties = $this->buildSchemaArray($properties);

        $tool->withObjectParameter(
            name: $name,
            description: $description,
            properties: $schemaProperties,
            requiredFields: $requiredFields,
            allowAdditionalProperties: $allowAdditional,
            required: $required
        );
    }

    /**
     * @param array<string, array> $parameters
     * @return array<\Prism\Prism\Contracts\Schema>
     */
    private function buildSchemaArray(array $parameters): array
    {
        $schemaArray = [];

        foreach ($parameters as $name => $config) {
            $type = $config['type'] ?? 'string';
            $description = $config['description'] ?? '';

            $schemaArray[] = match ($type) {
                'string' => new StringSchema($name, $description),
                'integer', 'number' => new NumberSchema($name, $description),
                'boolean' => new BooleanSchema($name, $description),
                'object' => new ObjectSchema(
                    $name,
                    $description,
                    $this->buildSchemaArray($config['properties'] ?? []),
                    $config['required_fields'] ?? [],
                    $config['allow_additional_properties'] ?? false
                ),
                default => new StringSchema($name, $description),
            };
        }

        return $schemaArray;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
