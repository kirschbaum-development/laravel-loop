<?php

namespace Kirschbaum\Loop;

use Prism\Prism\Prism;
use App\Models\TimeEntry;
use Illuminate\Support\Str;
use App\Models\BudgetPeriod;
use Prism\Prism\Facades\Tool;
use Filament\Facades\Filament;
use Prism\Prism\Text\Response;
use Prism\Prism\Enums\Provider;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Pluralizer;
use Illuminate\Support\Facades\Auth;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\BooleanSchema;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\BudgetResource;
use App\Filament\Resources\HolidayResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\AllocationResource;
use App\Filament\Resources\TeamMemberResource;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Stripe\Exception\ApiErrorException;

class Loop
{
    protected Collection $tools;
    protected static string $context = "";

    public static function additionalContext(string $context): void
    {
        static::$context .= "\n\n" . $context;
    }

    public function setup(): void
    {
        $this->tools = collect();
        $resources = [
            AllocationResource::class,
            ProjectResource::class,
            TeamMemberResource::class,
            UserResource::class,
            // HolidayResource::class,
            BudgetResource::class,
            BudgetPeriod::class,
            InvoiceResource::class,
            TimeEntry::class,
        ];

        foreach ($resources as $resourceClass) {
            $aiResourceData = $this->getAiResourceData($resourceClass);

            // static::additionalContext(
            //     $this->formatDocblockPropertiesForDescription(
            //         $aiResourceData->model,
            //         $this->getDocblockRelationships($aiResourceData->model)
            //     )
            // );

            // $this->tools->push($this->createDescribeModelTool($aiResourceData->model, $aiResourceData->label));
            $this->tools->push($this->createListTool($aiResourceData->model, $aiResourceData->label, $aiResourceData->pluralLabel));
            $this->tools->push($this->createFetchTool($aiResourceData->model, $aiResourceData->label));
            // $this->tools[] = $this->createCreateTool($aiResourceData->model, $aiResourceData->label);
            // $this->tools[] = $this->createUpdateTool($aiResourceData->model, $aiResourceData->label);
            // $this->tools[] = $this->createDeleteTool($aiResourceData->model, $aiResourceData->label);
        }

        $this->setupStripeTool();
    }

    protected function setupStripeTool(): void
    {
        $this->tools->push(
            Tool::as('stripe')
                ->for("make a call to the stripe api. You can use this tool to fetch any stripe related data.")
                ->withStringParameter('method', "HTTP method to use (GET, POST, PUT, DELETE, etc.)", required: true)
                ->withStringParameter('path', "Path to call (e.g. /v1/customers)", required: true)
                // ->withObjectParameter(
                //     name: 'query',
                //     description: "Query parameters to include in the request as a JSON object",
                //     properties: [], // No specific properties needed here, allows any structure
                //     requiredFields: [],
                //     allowAdditionalProperties: true,
                //     required: false // Make query optional
                // )
                ->withStringParameter('body', "HTTP body to use if it is not GET (can be JSON string or other format)", required: false)
                ->withStringParameter('contentType', "HTTP content type to use (default: application/json)", required: false)
                ->using(function (string $method, string $path, ?object $query = null, ?string $body = null, ?string $contentType = null): string {
                    dump([
                        'method' => $method,
                        'path' => $path,
                        'query' => $query,
                        'body' => $body,
                        'contentType' => $contentType
                    ]);
                    try {
                        // Convert query object to array if provided
                        $queryData = $query ? json_decode(json_encode($query), true) : [];
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            // Handle potential JSON decode error for query if needed
                            // For now, we'll default to an empty array
                            $queryData = [];
                        }

                        // Use the provided method directly
                        $method = strtoupper($method);
                        $bodyData = null;

                        // Parse body if provided and method is not GET
                        if ($body !== null && !empty($body) && $method !== 'GET') {
                            // Determine content type, default to application/json
                            $effectiveContentType = $contentType ?: 'application/json';

                            // If content type is JSON, try to parse it
                            if (stripos($effectiveContentType, 'application/json') !== false) {
                                if (is_string($body) && str_starts_with(trim($body), '{')) {
                                    $bodyData = json_decode($body, true);
                                    // If JSON decoding fails, pass the raw string body
                                    if (json_last_error() !== JSON_ERROR_NONE) {
                                        $bodyData = $body;
                                    }
                                } else {
                                    $bodyData = $body; // Pass as-is if not a JSON-like string
                                }
                            } else {
                                // For other content types, pass the body as is
                                $bodyData = $body;
                            }
                        }

                        // Initialize Stripe client with API key
                        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

                        // Extract resource name and ID from path
                        $path = ltrim($path, '/');

                        // If the path starts with v1/, remove it as Stripe SDK handles API versions
                        if (str_starts_with($path, 'v1/')) {
                            $path = substr($path, 3);
                        }

                        $pathParts = explode('/', $path);
                        $resourceName = $pathParts[0] ?? null;

                        // No resource name found, can't proceed
                        if (!$resourceName) {
                            return "Error: Invalid Stripe API path";
                        }

                        // Handle the request based on the method and path structure
                        switch ($method) {
                            case 'GET':
                                if (count($pathParts) === 1) {
                                    // List resource (e.g., /customers)
                                    $response = $stripe->{$resourceName}->all($queryData);
                                } else {
                                    // Get specific resource (e.g., /customers/{id})
                                    $id = $pathParts[1] ?? null;
                                    if (!$id) {
                                        return "Error: Resource ID required for GET request";
                                    }

                                    // Check if this is a nested resource
                                    if (count($pathParts) > 2) {
                                        $nestedResource = $pathParts[2] ?? null;
                                        if ($nestedResource) {
                                            // e.g., /customers/{id}/sources
                                            $resource = $stripe->{$resourceName}->retrieve($id);
                                            if (method_exists($resource, $nestedResource)) {
                                                $response = $resource->{$nestedResource}->all($queryData);
                                            } else {
                                                return "Error: Nested resource '$nestedResource' not found for resource '$resourceName'";
                                            }
                                        } else {
                                            $response = $stripe->{$resourceName}->retrieve($id, $queryData);
                                        }
                                    } else {
                                        $response = $stripe->{$resourceName}->retrieve($id, $queryData);
                                    }
                                }
                                break;

                            case 'POST':
                                if (count($pathParts) === 1) {
                                    // Create resource
                                    $response = $stripe->{$resourceName}->create($bodyData ?? []);
                                } else {
                                    // Update specific resource or call a resource method
                                    $id = $pathParts[1] ?? null;
                                    if (!$id) {
                                        return "Error: Resource ID required for POST request";
                                    }

                                    // Check if this is a nested resource or action
                                    if (count($pathParts) > 2) {
                                        $action = $pathParts[2] ?? null;
                                        if ($action) {
                                            // Try to call the action method on the resource's API
                                            if (method_exists($stripe->{$resourceName}, $action)) {
                                                $response = $stripe->{$resourceName}->{$action}($id, $bodyData ?? []);
                                            } else {
                                                // Retrieve the resource and try to call the action on the object
                                                $resource = $stripe->{$resourceName}->retrieve($id);
                                                if (method_exists($resource, $action)) {
                                                    $response = $resource->{$action}($bodyData ?? []);
                                                } else {
                                                    return "Error: Action '$action' not found for resource '$resourceName'";
                                                }
                                            }
                                        } else {
                                            $response = $stripe->{$resourceName}->update($id, $bodyData ?? []);
                                        }
                                    } else {
                                        $response = $stripe->{$resourceName}->update($id, $bodyData ?? []);
                                    }
                                }
                                break;

                            case 'DELETE':
                                if (count($pathParts) < 2) {
                                    return "Error: Resource ID required for DELETE request";
                                }

                                $id = $pathParts[1] ?? null;
                                if (!$id) {
                                    return "Error: Resource ID required for DELETE request";
                                }

                                $response = $stripe->{$resourceName}->delete($id, $queryData);
                                break;

                            default:
                                return "Error: Unsupported HTTP method: $method";
                        }

                        // Convert response to JSON string
                        return json_encode($response->toArray());
                    } catch (\Exception $e) {
                        // Include more details in the error message if possible
                        $errorDetails = $e->getMessage();
                        if ($e instanceof ApiErrorException && method_exists($e, 'getJsonBody') && $e->getJsonBody()) {
                            $errorDetails = json_encode($e->getJsonBody());
                        }
                        return "Error making Stripe API call to '$method $path': " . $errorDetails;
                    }
                })
        );
    }

    public function ask(string $question, Collection $messages): Response
    {
        $prompt = sprintf(
            "
                You are a helpful assistant. You will have many tools available to you. You need to give informations about the data and ask the user what you need to give him what he needs. \n\n
                Today is %s. Current month is %s. Current day is %s. Database being used is %s. \n\n
                When using the tools, always pass all the parameters listed in the tool. If you don't have all the information, ask the user for it. If it's optional, pass null. \n
                When a field is tagged with a access_type read, it means that the field is automatically calculated and is not stored in the database. \n
                When referencing an ID, try to fetch the resource of that ID from the database and give additional informations about it. \n\n
                When giving the final output, please compress the information to the minimum needed to answer the question. No need to explain what math you did unless explicitly asked. \n\n
                Parameter names in tools never include the $ symbol. \n\n
                %s \n\n
                You are logged in as %s (User ID: %s)
            ",
            now()->format('Y-m-d'),
            now()->format('F'),
            now()->format('d'),
            config('database.default'),
            static::$context,
            Auth::user()->name,
            Auth::user()->id,
        );
        dump($prompt);

        $messages = $messages
            ->reject(fn ($message) => empty($message['message']))
            ->map(function ($message) {
                return $message['user'] === 'AI'
                    ? new AssistantMessage($message['message'])
                    : new UserMessage($message['message']);
            })->toArray();

        $messages[] = new UserMessage($question);

        return Prism::text()
            // ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
            ->using(Provider::OpenAI, 'gpt-4o-mini')
            ->withMaxSteps(10)
            ->withMessages($messages)
            ->withSystemPrompt($prompt)
            ->withTools($this->tools->toArray())
            ->asText();
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

        return Tool::as($toolName)
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

        return Tool::as($toolName)
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
        return Tool::as($toolName)
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
        return Tool::as($toolName)
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

        return Tool::as($toolName)
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

    protected function getAiResourceData(string $resourceClass): ResourceData
    {
        if (is_subclass_of($resourceClass, Resource::class)) {
            return new ResourceData(
                model: $resourceClass::getModel(),
                label: $resourceClass::getModelLabel(),
                pluralLabel: $resourceClass::getPluralModelLabel(),
            );
        }

        if (is_subclass_of($resourceClass, Model::class)) {
            return new ResourceData(
                model: $resourceClass,
                label: Pluralizer::singular($resourceClass),
                pluralLabel: Pluralizer::plural($resourceClass),
            );
        }

        throw new \Exception("Resource class {$resourceClass} is not a valid resource or model class");
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

    protected function createDescribeModelTool(string $modelClass, string $label): object
    {
        $modelName = class_basename($modelClass);
        $toolName = strtolower($modelName) . '_describe';

        return Tool::as($toolName)
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

    public function getTools(): Collection
    {
        return collect($this->tools);
    }

    public function getTool(string $name): object
    {
        return $this->tools->first(fn ($tool) => $tool->name() === $name);
    }
}
