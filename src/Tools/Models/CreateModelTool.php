<?php

namespace Kirschbaum\Loop\Tools\Models;

use Illuminate\Database\Eloquent\Model;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Tools\Models\Concerns\MapsDatabaseTypeToParameterType;
use Kirschbaum\Loop\Tools\Models\Concerns\ProvidesModelColumns;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool as PrismTool;

/**
 * @method static self make(string $modelClass, string $label)
 */
class CreateModelTool implements Tool
{
    use Makeable;
    use MapsDatabaseTypeToParameterType;
    use ProvidesModelColumns;

    public function __construct(
        /** @param  class-string<Model> $modelClass */
        private string $modelClass,
        private string $label,
    ) {}

    public function build(): PrismTool
    {
        // Get a model instance to access its methods
        $modelInstance = new $this->modelClass;
        $fillableAttributes = $modelInstance->getFillable();

        // Get database column information
        $tableColumns = $this->getTableColumns($this->modelClass);

        // Prepare properties and required fields
        $properties = [];
        $requiredFields = [];

        foreach ($tableColumns as $attribute => $column) {
            if (in_array($attribute, ['id', 'created_at', 'updated_at'])) {
                continue;
            }

            $type = $this->mapDatabaseTypeToParameterType($column->type);
            $isRequired = ! $column->nullable && ! $column->has_default;

            // Add to required fields if necessary
            if ($isRequired) {
                $requiredFields[] = $attribute;
            }

            // Add the property based on its type
            $properties[] = match ($type) {
                'number' => new NumberSchema($attribute, "The {$attribute} of the {$this->label}"),
                'boolean' => new BooleanSchema($attribute, "The {$attribute} of the {$this->label}"),
                default => new StringSchema($attribute, "The {$attribute} of the {$this->label}"),
            };
        }

        // Start building the tool
        return app(PrismTool::class)
            ->as($this->getName())
            ->for("Create a new {$this->label}.")
            ->withObjectParameter(
                'data',
                "Object containing attributes to create a new {$this->label}",
                $properties,
                $requiredFields,
                true, // Allow additional properties
                true // Required parameter
            )
            ->using(function ($data) use ($fillableAttributes): string {
                try {
                    // Convert the object to array if it's not already
                    if (is_object($data)) {
                        $data = json_decode(json_encode($data), true) ?: [];
                    }

                    // Filter to only include fillable attributes
                    $filteredData = array_intersect_key($data, array_flip($fillableAttributes));

                    // Create the record
                    $record = $this->modelClass::create($filteredData);

                    return "Successfully created new {$this->label} with ID: {$record->id}";
                } catch (\Exception $e) {
                    return "Failed to create {$this->label}: ".$e->getMessage();
                }
            });
    }

    public function getName(): string
    {
        $modelName = class_basename($this->modelClass);

        return strtolower($modelName).'_create_model';
    }
}
