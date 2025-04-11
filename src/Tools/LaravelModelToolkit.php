<?php

namespace Kirschbaum\Loop\Tools;

use Filament\Resources\Resource;
use Kirschbaum\Loop\ResourceData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Pluralizer;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\BooleanSchema;
use Illuminate\Database\Eloquent\Model;
use Prism\Prism\Facades\Tool as PrismTool;

class LaravelModelToolkit implements Toolkit
{
    public function __construct(
        public readonly array $models = [],
    ) {
    }

    public static function make(...$args): static
    {
        return new self(...$args);
    }

    public function getTools(): Collection
    {
        return collect($this->models)->map(
            fn (string $model) => $this->buildModelTools($model)
        )->flatten();
    }

    public function getTool(string $name): ?\Prism\Prism\Tool
    {
        // TODO: Avoid building all tools if we only need one
        return $this->getTools()->first(
            fn ($tool) => $tool->name() === $name
        );
    }

    protected function buildModelTools(string $model): Collection
    {
        $aiResourceData = $this->getAiResourceData($model);

        return collect([
            $this->createDescribeModelTool($aiResourceData->model, $aiResourceData->label),
            $this->createListTool($aiResourceData->model, $aiResourceData->label, $aiResourceData->pluralLabel),
            $this->createFetchTool($aiResourceData->model, $aiResourceData->label),
        ]);
    }

    protected function createDescribeModelTool(string $modelClass, string $label): object
    {
        $modelName = class_basename($modelClass);
        $toolName = strtolower($modelName) . '_describe';

        return PrismTool::as($toolName)
            ->for("Get detailed information about {$label} table and model structure, including all fields and relationships.")
            ->using(function () use ($modelClass, $label): string {
                dump('describe tool called', $modelClass, $label);
                try {
                    // Get table columns
                    $tableColumns = $this->getTableColumns($modelClass);

                    // Get relationships
                    $relationships = $this->getDocblockRelationships($modelClass);

                    // Initialize model instance
                    $model = new $modelClass();
                    $tableName = $model->getTable();
                    $primaryKey = $model->getKeyName();
                    $fillable = $model->getFillable();

                    // Start building the response
                    $response = "# {$label} Model Information\n\n";

                    // Basic model information
                    $response .= "## Basic Information\n";
                    $response .= "- **Model Class**: `{$modelClass}`\n";
                    $response .= "- **Table Name**: `{$tableName}`\n";
                    $response .= "- **Primary Key**: `{$primaryKey}`\n\n";

                    // Fields/columns information
                    $response .= "## Database Fields\n\n";
                    $response .= "| Field | Type | Nullable | Has Default | Default Value | Fillable |\n";
                    $response .= "|-------|------|----------|-------------|--------------|---------|\n";

                    foreach ($tableColumns as $column) {
                        $isFillable = in_array($column->name, $fillable) ? '✓' : '✗';
                        $isNullable = $column->nullable ? '✓' : '✗';
                        $hasDefault = $column->has_default ? '✓' : '✗';
                        $defaultValue = $column->default === null ? 'NULL' : $column->default;

                        $response .= "| `{$column->name}` | {$column->type} | {$isNullable} | {$hasDefault} | {$defaultValue} | {$isFillable} |\n";
                    }

                    // Relationships information
                    if (!empty($relationships)) {
                        $response .= "\n## Relationships\n\n";
                        $response .= "| Relation Type | Related Model | Foreign Key |\n";
                        $response .= "|--------------|--------------|------------|\n";

                        foreach ($relationships as $relationship) {
                            $relationType = $relationship['relation'];
                            $relatedModel = $relationship['related_model'];
                            $foreignKey = $relationship['foreign_key'] ?? 'N/A';

                            $response .= "| {$relationType} | {$relatedModel} | {$foreignKey} |\n";
                        }
                    }

                    dump('describe tool response', $response);
                    return $response;
                } catch (\Exception $e) {
                    return "Error retrieving model information: " . $e->getMessage();
                }
            });
    }

    protected function createListTool(string $modelClass, string $label, string $pluralLabel): object
    {
        $modelName = class_basename($modelClass);
        $toolName = strtolower($modelName) . '_list';

        $modelInstance = new $modelClass();
        $fillableAttributes = $modelInstance->getFillable();
        $tableColumns = $this->getTableColumns($modelClass);
        $filterProperties = [];

        foreach ($tableColumns as $attribute => $column) {
            $type = $this->mapDatabaseTypeToParameterType($column->type);

            switch ($type) {
                case 'number':
                    $filterProperties[] = new ObjectSchema($attribute, "Filter by {$attribute}", [
                        new NumberSchema('value', "Value to filter {$attribute} by"),
                        new StringSchema('operator', "Comparison operator: =, >, <, >=, <= (default: =)")
                    ], ['value']);
                    break;
                case 'boolean':
                    $filterProperties[] = new BooleanSchema($attribute, "Filter by {$attribute}");
                    break;
                default:
                    $filterProperties[] = new ObjectSchema($attribute, "Filter by {$attribute}", [
                        new StringSchema('value', "Value to filter {$attribute} by"),
                        new StringSchema('operator', "Comparison operator: =, >, <, >=, <= (default: =)")
                    ], ['value']);
                    break;
            }
        }

        return PrismTool::as($toolName)
            ->for("List all {$pluralLabel} or perform aggregations (sum, count, avg, min, max) on {$pluralLabel} data. You can filter results by using the fields in the filters parameter.")
            ->withObjectParameter(
                'data',
                "Object containing parameters for listing {$pluralLabel}",
                [
                    new NumberSchema('limit', "Maximum number of {$pluralLabel} to return"),
                    new StringSchema('order_by', "Field to order results by"),
                    new StringSchema('order_direction', "Order direction (asc or desc)"),
                    new ObjectSchema('filters', "Filters to apply when listing {$pluralLabel}", $filterProperties, [], true),
                    new ObjectSchema('aggregation', "Aggregation to perform on {$pluralLabel}", [
                        new StringSchema('function', "Aggregation function: sum, count, avg, min, max"),
                        new StringSchema('field', "Field to aggregate"),
                        new StringSchema('group_by', "Field to group by (optional)"),
                    ], [], true),
                ],
                [], // No required fields
                true, // Allow additional properties
                true // Required parameter
            )
            ->using(function ($data) use ($modelClass, $pluralLabel): string {
                // Convert the object to array if it's not already
                if (is_object($data)) {
                    $data = json_decode(json_encode($data), true) ?: [];
                }

                // Extract parameters with defaults
                $limit = $data['limit'] ?? 10;
                $order_by = $data['order_by'] ?? null;
                $order_direction = $data['order_direction'] ?? 'asc';
                $filters = $data['filters'] ?? null;
                $aggregation = $data['aggregation'] ?? null;

                $query = $modelClass::query();

                // Apply filters if provided
                if ($filters && is_array($filters)) {
                    foreach ($filters as $field => $value) {
                        if ($value !== null) {
                            // Handle both simple value and object with value+operator
                            if (is_array($value) && isset($value['value'])) {
                                $operator = $value['operator'] ?? '=';
                                $filterValue = $value['value'];

                                // Validate operator
                                $validOperators = ['=', '>', '<', '>=', '<='];
                                if (!in_array($operator, $validOperators)) {
                                    $operator = '='; // Default to equality if invalid
                                }

                                $query->where($field, $operator, $filterValue);
                            } else {
                                $query->where($field, $value);
                            }
                        }
                    }
                }

                // Handle aggregation if provided
                if ($aggregation && is_array($aggregation) && !empty($aggregation['function']) && !empty($aggregation['field'])) {
                    $function = strtolower($aggregation['function']);
                    $field = $aggregation['field'];
                    $group_by = $aggregation['group_by'] ?? null;

                    // Validate the aggregation function
                    $validFunctions = ['sum', 'count', 'avg', 'min', 'max'];
                    if (!in_array($function, $validFunctions)) {
                        return "Invalid aggregation function. Valid options are: " . implode(', ', $validFunctions);
                    }

                    // Apply grouping if specified
                    if ($group_by) {
                        $query->groupBy($group_by);

                        // For count with grouping, we need to use countBy
                        if ($function === 'count') {
                            $results = $query->get()->countBy($group_by);

                            $result = "Aggregation results for count grouped by {$group_by}:\n\n";
                            foreach ($results as $groupValue => $count) {
                                $result .= "{$group_by}: {$groupValue}, Count: {$count}\n";
                            }
                            return $result;
                        }

                        // For other aggregations with grouping
                        $results = $query->select($group_by, DB::raw("{$function}({$field}) as aggregate_value"))->get();

                        $result = "Aggregation results for {$function} of {$field} grouped by {$group_by}:\n\n";
                        foreach ($results as $row) {
                            $result .= "{$group_by}: {$row->$group_by}, {$function}({$field}): {$row->aggregate_value}\n";
                        }
                        return $result;
                    } else {
                        // Simple aggregation without grouping
                        $result = $query->$function(str_contains($field, '(') ? DB::raw($field) : $field);
                        return "Aggregation result: {$function}({$field}) = {$result}";
                    }
                }

                // Regular listing (no aggregation)
                if ($order_by) {
                    $query->orderBy($order_by, $order_direction ?: 'asc');
                }

                $records = $query->limit($limit ?: 10)->get();

                if ($records->isEmpty()) {
                    return "No {$pluralLabel} found.";
                }

                $result = "Found " . $records->count() . " {$pluralLabel}:\n\n";

                foreach ($records as $record) {
                    $result .= "ID: {$record->id}, " . $this->formatModelAttributes($record) . "\n";
                }

                return $result;
            });
    }

    protected function createFetchTool(string $modelClass, string $label): object
    {
        $modelName = class_basename($modelClass);
        $toolName = strtolower($modelName) . '_fetch';

        return PrismTool::as($toolName)
            ->for("Fetch a specific {$label} by ID.")
            ->withObjectParameter(
                'data',
                "Object containing parameters to fetch a {$label}",
                [
                    new NumberSchema('id', "The ID of the {$label} to fetch"),
                ],
                ['id'], // Required fields
                false, // Don't allow additional properties
                true // Required parameter
            )
            ->using(function ($data) use ($modelClass, $label): string {
                // Convert the object to array if it's not already
                if (is_object($data)) {
                    $data = json_decode(json_encode($data), true) ?: [];
                }

                // Extract id parameter (should always be present due to required fields)
                $id = $data['id'];
                $record = $modelClass::find($id);

                if (!$record) {
                    return "{$label} with ID {$id} not found.";
                }

                $result = "{$label} details:\n\n";
                $result .= $this->formatModelAttributes($record, true);

                return $result;
            });
    }

    protected function createCreateTool(string $modelClass, string $label): object
    {
        $modelName = class_basename($modelClass);
        $toolName = strtolower($modelName) . '_create_tool';

        // Get a model instance to access its methods
        $modelInstance = new $modelClass();
        $fillableAttributes = $modelInstance->getFillable();

        // Get database column information
        $tableColumns = $this->getTableColumns($modelClass);

        // Prepare properties and required fields
        $properties = [];
        $requiredFields = [];

        foreach ($tableColumns as $attribute => $column) {
            if (in_array($attribute, ['id', 'created_at', 'updated_at'])) {
                continue;
            }

            $type = $this->mapDatabaseTypeToParameterType($column->type);
            $isRequired = !$column->nullable && !$column->has_default;

            // Add to required fields if necessary
            if ($isRequired) {
                $requiredFields[] = $attribute;
            }

            // Add the property based on its type
            switch ($type) {
                case 'number':
                    $properties[] = new NumberSchema($attribute, "The {$attribute} of the {$label}");
                    break;
                case 'boolean':
                    $properties[] = new BooleanSchema($attribute, "The {$attribute} of the {$label}");
                    break;
                default:
                    $properties[] = new StringSchema($attribute, "The {$attribute} of the {$label}");
                    break;
            }
        }

        // Start building the tool
        return PrismTool::as($toolName)
            ->for("Create a new {$label}.")
            ->withObjectParameter(
                'data',
                "Object containing attributes to create a new {$label}",
                $properties,
                $requiredFields,
                true, // Allow additional properties
                true // Required parameter
            )
            ->using(function ($data) use ($modelClass, $label, $fillableAttributes): string {
                try {
                    // Convert the object to array if it's not already
                    if (is_object($data)) {
                        $data = json_decode(json_encode($data), true) ?: [];
                    }

                    // Filter to only include fillable attributes
                    $filteredData = array_intersect_key($data, array_flip($fillableAttributes));

                    // Create the record
                    $record = $modelClass::create($filteredData);
                    return "Successfully created new {$label} with ID: {$record->id}";
                } catch (\Exception $e) {
                    return "Failed to create {$label}: " . $e->getMessage();
                }
            });
    }

    protected function createUpdateTool(string $modelClass, string $label): object
    {
        $modelName = class_basename($modelClass);
        $toolName = strtolower($modelName) . '_update';

        // Get a model instance to access its methods
        $modelInstance = new $modelClass();
        $fillableAttributes = $modelInstance->getFillable();

        // Prepare properties including the ID and all fillable attributes
        $properties = [
            new NumberSchema('id', "The ID of the {$label} to update"),
        ];

        // Get database column information for proper typing
        $tableColumns = $this->getTableColumns($modelClass);

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
                        $properties[] = new NumberSchema($attribute, "The {$attribute} of the {$label}");
                        break;
                    case 'boolean':
                        $properties[] = new BooleanSchema($attribute, "The {$attribute} of the {$label}");
                        break;
                    default:
                        $properties[] = new StringSchema($attribute, "The {$attribute} of the {$label}");
                        break;
                }
            } else {
                // Fallback to string parameter if not in schema
                $properties[] = new StringSchema($attribute, "The {$attribute} of the {$label}");
            }
        }

        // Start building the tool
        return PrismTool::as($toolName)
            ->for("Update an existing {$label}.")
            ->withObjectParameter(
                'data',
                "Object containing attributes to update a {$label}",
                $properties,
                ['id'], // Only ID is required
                true, // Allow additional properties
                true // Required parameter
            )
            ->using(function ($data) use ($modelClass, $label, $fillableAttributes): string {
                try {
                    // Convert the object to array if it's not already
                    if (is_object($data)) {
                        $data = json_decode(json_encode($data), true) ?: [];
                    }

                    // Extract id parameter (should always be present due to required fields)
                    $id = $data['id'];

                    $record = $modelClass::find($id);

                    if (!$record) {
                        return "{$label} with ID {$id} not found.";
                    }

                    // Remove id from data and filter to only include fillable attributes
                    unset($data['id']);
                    $filteredData = array_intersect_key($data, array_flip($fillableAttributes));

                    // Only update if there's data to update
                    if (!empty($filteredData)) {
                        $record->update($filteredData);
                        return "Successfully updated {$label} with ID: {$id}";
                    } else {
                        return "No valid fields provided to update {$label} with ID: {$id}";
                    }
                } catch (\Exception $e) {
                    return "Failed to update {$label}: " . $e->getMessage();
                }
            });
    }

    protected function createDeleteTool(string $modelClass, string $label): object
    {
        $modelName = class_basename($modelClass);
        $toolName = strtolower($modelName) . '_delete';

        return PrismTool::as($toolName)
            ->for("Delete a {$label}.")
            ->withObjectParameter(
                'data',
                "Object containing parameters to delete a {$label}",
                [
                    new NumberSchema('id', "The ID of the {$label} to delete"),
                ],
                ['id'], // Required fields
                false, // Don't allow additional properties
                true // Required parameter
            )
            ->using(function ($data) use ($modelClass, $label): string {
                // Convert the object to array if it's not already
                if (is_object($data)) {
                    $data = json_decode(json_encode($data), true) ?: [];
                }

                // Extract id parameter (should always be present due to required fields)
                $id = $data['id'];

                $record = $modelClass::find($id);

                if (!$record) {
                    return "{$label} with ID {$id} not found.";
                }

                try {
                    $record->delete();
                    return "Successfully deleted {$label} with ID: {$id}";
                } catch (\Exception $e) {
                    return "Failed to delete {$label}: " . $e->getMessage();
                }
            });
    }

    protected function formatModelAttributes(Model $model, bool $detailed = false): string
    {
        $attributes = $model->getAttributes();
        $formatted = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, ['id', 'created_at', 'updated_at']) && !$detailed) {
                continue;
            }

            if ($value instanceof \DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $formatted[] = "{$key}: {$value}";
        }

        return implode(", ", $formatted);
    }

    /**
     * Get the database columns for a model class
     *
     * @param string $modelClass
     * @return array
     */
    protected function getTableColumns(string $modelClass): array
    {
        $model = new $modelClass();
        $table = $model->getTable();

        // Get all columns from the table
        $columns = DB::select("SHOW COLUMNS FROM {$table}");

        // Convert to associative array
        $columnsArray = [];
        foreach ($columns as $column) {
            $columnsArray[$column->Field] = (object) [
                'name' => $column->Field,
                'type' => $column->Type,
                'nullable' => $column->Null === 'YES',
                'has_default' => $column->Default !== null || $column->Extra === 'auto_increment',
                'default' => $column->Default,
                'extra' => $column->Extra,
            ];
        }

        return $columnsArray;
    }

    /**
     * Map database column type to parameter type
     *
     * @param string $dbType
     * @return string
     */
    protected function mapDatabaseTypeToParameterType(string $dbType): string
    {
        // Extract the base type without length specifications
        $baseType = strtolower(preg_replace('/\(.*\)/', '', $dbType));

        // Number types
        if (in_array($baseType, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double'])) {
            // Special case for boolean represented as tinyint(1)
            if ($baseType === 'tinyint' && strpos($dbType, '(1)') !== false) {
                return 'boolean';
            }
            return 'number';
        }

        // Date types
        if (in_array($baseType, ['date', 'datetime', 'timestamp', 'time', 'year'])) {
            return 'date';
        }

        // Boolean types
        if (in_array($baseType, ['bool', 'boolean'])) {
            return 'boolean';
        }

        // JSON types
        if (in_array($baseType, ['json'])) {
            return 'json';
        }

        // Default to string for all other types
        return 'string';
    }

    /**
     * Add parameter to tool based on type
     *
     * @param object $tool
     * @param string $type
     * @param string $name
     * @param string $description
     * @param bool $required
     * @return void
     */
    protected function addParameterByType(object $tool, string $type, string $name, string $description, bool $required): void
    {
        switch ($type) {
            case 'number':
                $tool->withNumberParameter($name, $description, required: $required);
                break;
            case 'boolean':
                $tool->withBooleanParameter($name, $description, required: $required);
                break;
            case 'date':
                $tool->withStringParameter($name, $description . ' (format: YYYY-MM-DD)', required: $required);
                break;
            case 'json':
                $tool->withStringParameter($name, $description . ' (JSON format)', required: $required);
                break;
            default:
                $tool->withStringParameter($name, $description, required: $required);
                break;
        }
    }

    protected function getAiResourceData(string $modelClass): ResourceData
    {
        if (is_subclass_of($modelClass, Resource::class)) {
            return new ResourceData(
                model: $modelClass::getModel(),
                label: $modelClass::getModelLabel(),
                pluralLabel: $modelClass::getPluralModelLabel(),
            );
        }

        if (is_subclass_of($modelClass, Model::class)) {
            return new ResourceData(
                model: $modelClass,
                label: Pluralizer::singular($modelClass),
                pluralLabel: Pluralizer::plural($modelClass),
            );
        }

        throw new \Exception("Resource class {$modelClass} is not a valid resource or model class");
    }

    /**
     * Extract properties defined in the model's docblock
     *
     * @param string $modelClass
     * @return array
     */
    protected function getDocblockRelationships(string $modelClass): array
    {
        $reflection = new \ReflectionClass($modelClass);
        $docComment = $reflection->getDocComment();

        if (!$docComment) {
            return [];
        }

        $properties = [];
        $lines = explode("\n", $docComment);

        foreach ($lines as $line) {
            // Match property definitions like "@property AnyType<related> $name description"
            if (! preg_match('/@property(-read|-write)?\s+([^\s<]+)<([^>]+)>\s+\$([^\s]+)(?:\s+(.*))?/', $line, $matches)) {
                continue;
            }

            $relatedModel = $matches[3];
            $relationName = $matches[4];
            $relationObject = (new $modelClass)->$relationName();
            // dd($modelClass, $relationName, (new $modelClass)->$relationName()->getForeignKeyName(), (new $modelClass)->$relationName()->getLocalKeyName());

            $properties[] = [
                'relation' => $matches[2],
                'related_model' => $relatedModel,
                'foreign_key' => method_exists($relationObject, 'getForeignKeyName') ? $relationObject->getForeignKeyName() : null,
            ];
        }

        return $properties;
    }

    /**
     * Format docblock properties for inclusion in tool descriptions
     *
     * @param array $properties
     * @return string
     */
    protected function formatDocblockPropertiesForDescription(string $modelClass, array $properties): string
    {
        if (empty($properties)) {
            return '';
        }

        $description = "\n\nThe {$modelClass} resource also includes the following relationships:";

        foreach ($properties as $property) {
            $description .= sprintf(
                "\n- %s %s via the %s foreign key",
                $modelClass,
                $property['relation'],
                $property['related_model'],
                $property['foreign_key']
            );
        }

        return $description;
    }
}