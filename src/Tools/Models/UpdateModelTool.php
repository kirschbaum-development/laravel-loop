<?php

namespace Kirschbaum\Loop\Tools\Models;

use Prism\Prism\Tool as PrismTool;
use Kirschbaum\Loop\Contracts\Tool;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\BooleanSchema;
use Kirschbaum\Loop\Concerns\Makeable;
use Illuminate\Database\Eloquent\Model;
use Kirschbaum\Loop\Tools\Models\Concerns\ProvidesModelColumns;
use Kirschbaum\Loop\Tools\Models\Concerns\MapsDatabaseTypeToParameterType;

/**
 * @method static self make(string $modelClass, string $label)
 */
class UpdateModelTool implements Tool
{
    use Makeable;
    use MapsDatabaseTypeToParameterType;
    use ProvidesModelColumns;

    public function __construct(
        /** @param  class-string<Model> $modelClass */
        private string $modelClass,
        private string $label,
    ) {
    }

    public function build(): PrismTool
    {
        /** @var Model $modelInstance */
        $modelInstance = new $this->modelClass;
        $fillableAttributes = $modelInstance->getFillable();

        // Prepare properties including the ID and all fillable attributes
        $properties = [
            new NumberSchema('id', "The ID of the {$this->label} to update"),
        ];

        // Get database column information for proper typing
        $tableColumns = $this->getTableColumns($this->modelClass);

        foreach ($fillableAttributes as $attribute) {
            if ($attribute === 'id') {
                continue;
            }

            if (isset($tableColumns[$attribute])) {
                $column = $tableColumns[$attribute];
                $type = $this->mapDatabaseTypeToParameterType($column->type);

                // Add the property based on its type
                switch ($type) {
                    case 'number':
                        $properties[] = new NumberSchema($attribute, "The {$attribute} of the {$this->label}");

                        break;

                    case 'boolean':
                        $properties[] = new BooleanSchema($attribute, "The {$attribute} of the {$this->label}");

                        break;

                    default:
                        $properties[] = new StringSchema($attribute, "The {$attribute} of the {$this->label}");

                        break;
                }
            } else {
                // Fallback to string parameter if not in schema
                $properties[] = new StringSchema($attribute, "The {$attribute} of the {$this->label}");
            }
        }

        // Start building the tool
        return app(PrismTool::class)
            ->as($this->getName())
            ->for("Update an existing {$this->label}.")
            ->withObjectParameter(
                'data',
                "Object containing attributes to update a {$this->label}",
                $properties,
                ['id'], // Only ID is required
                true, // Allow additional properties
                true // Required parameter
            )
            ->using(function ($data) use ($fillableAttributes): string {
                try {
                    // Convert the object to array if it's not already
                    if (is_object($data)) {
                        $data = json_decode((string) json_encode($data), true) ?: [];
                    }

                    // Extract id parameter (should always be present due to required fields)
                    $id = $data['id'];

                    $record = $this->modelClass::find($id);

                    if (! $record) {
                        return "{$this->label} with ID {$id} not found.";
                    }

                    // Remove id from data and filter to only include fillable attributes
                    unset($data['id']);
                    $filteredData = array_intersect_key($data, array_flip($fillableAttributes));

                    // Only update if there's data to update
                    if (! empty($filteredData)) {
                        $record->update($filteredData);

                        return "Successfully updated {$this->label} with ID: {$id}";
                    } else {
                        return "No valid fields provided to update {$this->label} with ID: {$id}";
                    }
                } catch (\Exception $e) {
                    return "Failed to update {$this->label}: " . $e->getMessage();
                }
            });
    }

    public function getName(): string
    {
        $modelName = class_basename($this->modelClass);

        return strtolower($modelName) . '_update_model';
    }
}
