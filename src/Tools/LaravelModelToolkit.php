<?php

namespace Kirschbaum\Loop\Tools;

use Exception;
use ReflectionClass;
use Kirschbaum\Loop\Enums\Mode;
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
        public readonly Mode $mode = Mode::ReadOnly,
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
            $this->createDescribeModelTool($aiResourceData->model, $aiResourceData->label, $aiResourceData->pluralLabel),
            $this->createListTool($aiResourceData->model, $aiResourceData->label, $aiResourceData->pluralLabel),
            $this->createFetchTool($aiResourceData->model, $aiResourceData->label),
        ]);
    }

    protected function createDescribeModelTool(string $modelClass, string $label, string $pluralLabel): object
    {
        $modelName = class_basename($modelClass);
        $toolName = strtolower($modelName) . '_describe';

        return PrismTool::as($toolName)
            ->for("Get detailed information about the {$pluralLabel} table, with all its fields and relationships.")
            ->using(function () use ($modelClass, $label): string {
                try {
                    $tableColumns = $this->getTableColumns($modelClass);
                    $relationships = $this->getDocblockRelationships($modelClass);

                    $model = new $modelClass();
                    $tableName = $model->getTable();
                    $primaryKey = $model->getKeyName();
                    $fillable = $model->getFillable();

                    $data = [
                        'label' => $label,
                        'basic_information' => [
                            'model_class' => $modelClass,
                            'table_name' => $tableName,
                            'primary_key' => $primaryKey,
                        ],
                        'database_fields' => [],
                        'relationships' => [],
                    ];

                    foreach ($tableColumns as $column) {
                        $data['database_fields'][] = [
                            'field' => $column->name,
                            'type' => $column->type,
                            'nullable' => $column->nullable,
                            'has_default' => $column->has_default,
                            'default_value' => $column->default,
                            'fillable' => in_array($column->name, $fillable),
                        ];
                    }

                    $data['relationships'] = $relationships;

                    return json_encode($data, JSON_PRETTY_PRINT);
                } catch (Exception $e) {
                    return json_encode(['error' => "Error retrieving model information: " . $e->getMessage()]);
                }
            });
    }

    protected function createListTool(string $modelClass, string $label, string $pluralLabel): object
    {
        $modelName = class_basename($modelClass);
        $toolName = strtolower($modelName) . '_list';

        return PrismTool::as($toolName)
            ->for("List all {$pluralLabel}. You can filter results by using the fields from the describe tool.")
            ->withObjectParameter(
                'data',
                "Object containing parameters for listing {$pluralLabel}",
                [
                    new NumberSchema('limit', "Maximum number of {$pluralLabel} to return"),
                    new StringSchema('order_by', "Field to order results by"),
                    new StringSchema('order_direction', "Order direction (asc or desc)"),
                    new StringSchema('filters', "Filters (JSON) to apply when listing {$pluralLabel}"),
                ],
                requiredFields:[],
                allowAdditionalProperties: true,
                required: true
            )
            ->using(function ($data) use ($modelClass, $pluralLabel): string {
                try {
                    if (is_object($data)) {
                        $data = json_decode(json_encode($data), true) ?? [];
                    }

                    $limit = $data['limit'] ?? 10;
                    $orderBy = $data['order_by'] ?? null;
                    $order_direction = $data['order_direction'] ?? 'asc';
                    $filters = json_decode($data['filters'] ?? null, true) ?? null;
                    $query = $modelClass::query();

                    foreach ((array) $filters as $field => $value) {
                        if ($value === null) {
                            continue;
                        }

                        if (is_array($value) && isset($value['value'])) {
                            $operator = $value['operator'] ?? '=';
                            $filterValue = $value['value'];

                            $validOperators = ['=', '>', '<', '>=', '<='];
                            if (! in_array($operator, $validOperators)) {
                                $operator = '=';
                            }

                            $query->where($field, $operator, $filterValue);
                        } else {
                            $query->where($field, $value);
                        }
                    }

                    if ($orderBy) {
                        $query->orderBy($orderBy, $order_direction ?: 'asc');
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
                } catch (Exception $e) {
                    return "Error listing {$pluralLabel}: " . $e->getMessage();
                }
            });
    }

    protected function createFetchTool(string $modelClass, string $label): object
    {
        $modelName = class_basename($modelClass);
        $toolName = strtolower($modelName) . '_fetch';

        return PrismTool::as($toolName)
            ->for("Fetch a specific {$label} by ID or its primary key.")
            ->withObjectParameter(
                'data',
                "Object containing parameters to fetch a {$label}",
                [
                    new NumberSchema('id', "The ID of the {$label} to fetch"),
                ],
                requiredFields: ['id'],
                allowAdditionalProperties: false,
                required: true
            )
            ->using(function ($data) use ($modelClass, $label): string {
                if (is_object($data)) {
                    $data = json_decode(json_encode($data), true) ?: [];
                }

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
            if (in_array($key, ['id', 'created_at', 'updated_at']) && ! $detailed) {
                continue;
            }

            if ($value instanceof \DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $formatted[] = "{$key}: {$value}";
        }

        return implode(", ", $formatted);
    }

    protected function getTableColumns(string $modelClass): array
    {
        $model = new $modelClass();
        $table = $model->getTable();

        $columns = DB::select("SHOW COLUMNS FROM {$table}");
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

    protected function mapDatabaseTypeToParameterType(string $dbType): string
    {
        $baseType = strtolower(preg_replace('/\(.*\)/', '', $dbType));

        if (in_array($baseType, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double'])) {
            if ($baseType === 'tinyint' && strpos($dbType, '(1)') !== false) {
                return 'boolean';
            }
            return 'number';
        }

        if (in_array($baseType, ['date', 'datetime', 'timestamp', 'time', 'year'])) {
            return 'date';
        }

        if (in_array($baseType, ['bool', 'boolean'])) {
            return 'boolean';
        }

        if (in_array($baseType, ['json'])) {
            return 'json';
        }

        return 'string';
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
                label: Pluralizer::singular(class_basename($modelClass)),
                pluralLabel: Pluralizer::plural(class_basename($modelClass)),
            );
        }

        throw new \Exception("Resource class {$modelClass} is not a valid resource or model class");
    }

    protected function getDocblockRelationships(string $modelClass): array
    {
        $reflection = new ReflectionClass($modelClass);
        $docComment = $reflection->getDocComment();

        if (!$docComment) {
            return [];
        }

        $properties = [];
        $lines = explode("\n", $docComment);

        foreach ($lines as $line) {
            $pattern = '/@property(?:-read|-write)?\s+([^\s$]+)\s+(\$\w+)\s*(.*)$/m';

            if (! preg_match($pattern, $line, $matches)) {
                continue;
            }

            try {
                $model = new $modelClass();
                $relatedModel = $matches[1];
                $property = ltrim($matches[2], '$');

                if (! method_exists($model, $property)) {
                    continue;
                }

                $returnType = $reflection->getMethod($property)->getReturnType();

                $relation = $model->$property();

                $properties[] = [
                    'relation' => $property,
                    'type' => $returnType?->getName(),
                    'related_model' => $relatedModel,
                    'foreign_key' => method_exists($relation, 'getForeignKeyName') ? $relation->getForeignKeyName() : null,
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        return $properties;
    }
}