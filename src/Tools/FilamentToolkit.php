<?php

namespace Kirschbaum\Loop\Tools;

use Exception;
use ReflectionClass;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Kirschbaum\Loop\Enums\Mode;
use Filament\Resources\Resource;
use Kirschbaum\Loop\ResourceData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Pluralizer;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Filament\Forms\Components\Select;
use Prism\Prism\Schema\BooleanSchema;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Prism\Prism\Facades\Tool as PrismTool;
use Filament\Tables\Columns\CheckboxColumn;
use Livewire\Component as LivewireComponent;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Support\Contracts\TranslatableContentDriver;

class FilamentToolkit implements Toolkit
{
    /**
     * @param Resource[] $resources
     */
    public function __construct(
        public readonly array $resources = [],
        public readonly Mode $mode = Mode::ReadOnly,
    ) {
    }

    public static function make(...$args): static
    {
        return new self(...$args);
    }

    public function getResources(): Collection
    {
        $resources = $this->resources;

        if (empty($resources)) {
            $resources = Filament::getResources();
        }

        return collect($resources);
    }

    public function getTools(): Collection
    {
        return collect($this->getResources())->map(
            fn (string $resource) => collect([
                $this->listAvailableResourcesTool($resource),
                $this->describeResourceTool($resource),
                $this->getResourceDataTool($resource),
                // $this->executeResourceActionTool($resource),
            ]),
        )->flatten();
    }

    protected function listAvailableResourcesTool(): ?\Prism\Prism\Tool
    {
        return PrismTool::as('list_filament_resources')
            ->for('Lists all available Filament resources')
            ->using(function () {
                return collect($this->getResources())->map(fn (string $resource) => $resource)->implode(', ');
            });
    }

    protected function describeResourceTool(): ?\Prism\Prism\Tool
    {
        return PrismTool::as('describe_filament_resource')
            ->for('Describes the structure, fields, columns, actions, and relationships for a given Filament resource')
            ->withStringParameter('resourceClass', 'The class name of the resource to describe.', required: true)
            ->using(function (string $resourceClass) {
                try {
                    $resource = app($resourceClass);

                    if (! $resource instanceof Resource) {
                        return sprintf('Could not find %s resource class', $resourceClass);
                    }
                } catch (Exception $e) {
                    return sprintf('Could describe %s resource class. Error: %s', $resourceClass, $e->getMessage());
                }

                return json_encode([
                    'resource' => class_basename($resource),
                    'model' => $resource::getModel(),
                    // 'navigation' => $this->extractNavigationInfo($resource),
                    // 'permissions' => $this->extractPermissionsInfo($resource),
                    'form' => $this->extractFormSchema($resource),
                    'table' => $this->extractTableSchema($resource),
                    'relationships' => $this->extractRelationshipsInfo($resource),
                    'pages' => $this->extractPagesInfo($resource),
                ]);
            });
    }

    protected function getResourceDataTool(): ?\Prism\Prism\Tool
    {
        return PrismTool::as('get_filament_resource_data')
            ->for('Gets the data for a given Filament resource, applying optional filters.')
            ->withStringParameter('resourceClass', 'The class name of the resource to get data for.', required: true)
            ->withStringParameter('filtersJson', 'JSON string of filters to apply (e.g., \'{"status": "published", "author_id": [1, 2]}\').', required: false)
            ->using(function (string $resourceClass, ?string $filtersJson = null) {
                try {
                    $resource = app($resourceClass);

                    if (! $resource instanceof Resource) {
                        return sprintf('Error: %s is not a valid Filament resource class.', $resourceClass);
                    }
                } catch (Exception $e) {
                    Log::error("Error loading resource class {$resourceClass}: {$e->getMessage()}");
                    return sprintf('Error loading resource class %s: %s', $resourceClass, $e->getMessage());
                }

                $filters = [];
                if ($filtersJson) {
                    try {
                        $decodedFilters = json_decode($filtersJson, true, 512, JSON_THROW_ON_ERROR);
                        if (is_array($decodedFilters)) {
                            $filters = $decodedFilters;
                        } else {
                            return 'Error: Invalid JSON provided for filters.';
                        }
                    } catch (\JsonException $e) {
                        Log::error("Error decoding filters JSON for resource {$resourceClass}: {$e->getMessage()}");
                        return sprintf('Error decoding filters JSON: %s', $e->getMessage());
                    }
                }

                try {
                    $livewireComponent = new class extends LivewireComponent implements HasTable {
                        use \Filament\Tables\Concerns\InteractsWithTable;
                        public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver { return null; }
                    };

                    $table = $resource::table(new Table($livewireComponent));
                    $tableFilters = collect($table->getFilters())->keyBy(fn (BaseFilter $filter) => $filter->getName());
                    $query = $resource::getModel()::query();

                    foreach ($filters as $filterName => $value) {
                        if (! $tableFilter = $tableFilters->get($filterName)) {
                            Log::warning("[Laravel Loop] Filter '{$filterName}' not found for resource '{$resourceClass}' in get_filament_resource_data tool.");

                            continue;
                        }

                        if ($tableFilter instanceof SelectFilter) {
                            $attribute = $tableFilter->getAttribute() ?? $filterName; // Use attribute if defined, else assume name matches column

                            if (! str_contains($attribute, '.')) {
                                if (is_array($value)) {
                                    $query->whereIn($attribute, $value);
                                } elseif ($value !== null) {
                                    $query->where($attribute, $value);
                                } else {
                                    $query->whereNull($attribute);
                                }
                            } else {
                                // TODO: Add support for relationship filters if needed. Could involve whereHas.
                                Log::warning("Skipping relationship filter '{$filterName}' in get_filament_resource_data tool - not yet supported.");
                            }
                        } elseif ($tableFilter instanceof TernaryFilter) {
                            // TODO: Implement TernaryFilter logic if possible/needed. Requires accessing query modification logic.
                            Log::warning("Skipping TernaryFilter '{$filterName}' in get_filament_resource_data tool - not yet supported.");
                        } else {
                            // TODO: Handle other filter types (e.g., custom Filter) if needed.
                            Log::warning("Skipping unsupported filter type for '{$filterName}' in get_filament_resource_data tool.");
                        }
                    }

                    $tableColumns = $table->getColumns();
                    $results = $query->get();

                    $outputData = $results->map(function (Model $model) use ($tableColumns, $resourceClass) {
                        $rowData = [];

                        foreach ($tableColumns as $column) {
                            /** @var Column $column */
                            $columnName = $column->getName();

                            try {
                                if (str_contains($columnName, '.')) {
                                    $relationName = strtok($columnName, '.');
                                    if (method_exists($model, $relationName)) {
                                        $model->loadMissing($relationName);
                                        $value = data_get($model, $columnName);
                                    } else {
                                        $value = null;
                                        Log::warning("Relation '{$relationName}' not found on model for column '{$columnName}' in resource '{$resourceClass}'.");
                                    }
                                } else {
                                    $value = $model->getAttribute($columnName);
                                }
                                 $rowData[$columnName] = $value;
                             } catch (\Exception $e) {
                                 $rowData[$columnName] = null;
                                 Log::error("Could not retrieve value for column '{$columnName}' on model ID {$model->getKey()} for resource '{$resourceClass}': {$e->getMessage()}");
                             }
                        }

                        return $rowData;
                    });

                    return json_encode($outputData);

                } catch (Exception $e) {
                    Log::error("Error processing resource data for {$resourceClass}: {$e->getMessage()}");
                    return sprintf('Error processing data for resource %s: %s', $resourceClass, $e->getMessage());
                }
            });
    }

    protected function executeResourceActionTool(): ?\Prism\Prism\Tool
    {
        return PrismTool::as('execute_filament_resource_action')
            ->for('Executes an action on a given Filament resource.')
            ->withStringParameter('resourceClass', 'The class name of the resource to execute the action on.', required: true)
            ->withStringParameter('actionName', 'The name of the action to execute.', required: true)
            ->withStringParameter('recordId', 'The ID of the record to execute the action on.', required: false)
            ->using(function (string $resourceClass, string $actionName, ?string $recordId = null) {
                $resource = app($resourceClass);
                $action = $resource::getAction($actionName);

            });
    }

    protected function extractBasicInfo(Resource $resource): array
    {
        return [
            'resource' => class_basename($resource),
            'model' => $resource::getModel(),
        ];
    }

    protected function extractNavigationInfo(Resource $resource): array
    {
        return [
            'group' => $resource::getNavigationGroup(),
            'icon' => $resource::getNavigationIcon(),
            'label' => $resource::getNavigationLabel() ?: $resource::getPluralModelLabel(),
        ];
    }

    protected function extractFormSchema(Resource $resource): array
    {
        $livewireComponent = new class extends LivewireComponent implements HasForms {
            use \Filament\Forms\Concerns\InteractsWithForms;
        };

        $form = $resource::form(new Form($livewireComponent));
        $fields = collect($form->getComponents(true))
            ->reject(fn (Component $component) => $component instanceof Grid)
            ->map(fn (Component $component) => $this->mapFormComponent($component, $resource))
            ->filter()
            ->values()
            ->all();

        return ['fields' => $fields];
    }

    protected function mapFormComponent(Component $component, Resource $resource): ?array
    {
        $baseInfo = [
            'name' => $component->getName(),
            'type' => $this->mapComponentType($component),
            'label' => $component->getLabel(),
            'required' => $component->isRequired(),
            'disabled' => $component->isDisabled(),
            // 'nullable' => method_exists($component, 'isNullable') ? $component->isNullable() : null, // Needs checking validation rules
        ];

        if ($component instanceof TextInput) {
            $baseInfo['maxLength'] = $component->getMaxLength();
        }

        if ($component instanceof Select && $component->getRelationshipName()) {
            $modelClass = $resource::getModel();
            $modelInstance = app($modelClass);
            $relationshipDefinition = $modelInstance->{$component->getRelationshipName()}();

            $baseInfo['relationship'] = [
                'type' => class_basename($relationshipDefinition), // e.g., BelongsTo
                'model' => get_class($relationshipDefinition->getRelated()),
                'displayColumn' => $component->getRelationshipTitleAttribute(),
                'foreignKey' => $relationshipDefinition->getForeignKeyName(), // Might need adjustment based on relationship type
            ];
        }

        // Add more specific component type mappings here if needed

        return $baseInfo;
    }

    protected function mapComponentType(Component $component): string
    {
        return match (true) {
            $component instanceof TextInput => 'text',
            $component instanceof Select => 'select',
            $component instanceof DateTimePicker => 'datetime',
            $component instanceof \Filament\Forms\Components\RichEditor => 'richEditor',
            $component instanceof \Filament\Forms\Components\Textarea => 'textarea',
            $component instanceof \Filament\Forms\Components\Checkbox => 'checkbox',
            $component instanceof \Filament\Forms\Components\Toggle => 'toggle',
            // Add more mappings as needed
            default => class_basename($component), // Fallback to class name
        };
    }

    protected function extractTableSchema(Resource $resource): array
    {
        try {
            $livewireComponent = new class extends LivewireComponent implements HasTable {
                use \Filament\Tables\Concerns\InteractsWithTable;

                public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver {
                    return null;
                }
            };

            $table = $resource::table(new Table($livewireComponent));

            $columns = collect($table->getColumns())
                ->map(fn (Column $column) => $this->mapTableColumn($column))
                ->all();

            $existingFilters = collect($table->getFilters())
                ->map(fn (BaseFilter $filter) => $this->mapTableFilter($filter))
                ->all();

            $searchableColumnFilters = collect($columns)
                ->filter(fn (array $column) => $column['searchable'] ?? false)
                ->map(fn (array $column) => [
                    'name' => $column['name'],
                    'label' => $column['label'],
                    'type' => 'searchable_column', // Indicate this is derived from a searchable column
                ])
                ->keyBy('name') // Key by name to potentially merge/override later if needed
                ->all();

            $filters = array_merge($searchableColumnFilters, $existingFilters); // Merge, giving priority to existing explicit filters if names collide

            $rowActions = collect($table->getActions()) // Actions column actions
                ->map(fn (Action $action) => $this->mapTableAction($action))
                ->all();

            $bulkActions = collect($table->getBulkActions()) // Bulk actions
                ->map(fn (BulkAction $action) => $this->mapTableAction($action))
                ->all();

            return [
                'columns' => $columns,
                'filters' => array_values($filters), // Re-index the array
                'actions' => [
                    'row' => $rowActions,
                    'bulk' => $bulkActions,
                ],
            ];
        } catch (Exception $e) {
            Log::error("Error extracting table schema for resource {$resource}: {$e->getMessage()}");

            return [];
        }
    }

    protected function mapTableColumn(Column $column): array
    {
        $baseInfo = [
            'name' => $column->getName(),
            'label' => $column->getLabel(),
            'searchable' => $column->isSearchable(),
            'sortable' => $column->isSortable(),
            'hidden' => $column->isHidden(),
        ];

        return $baseInfo;
    }

    protected function mapTableFilter(BaseFilter $filter): array
    {
        $baseInfo = [
            'name' => $filter->getLabel() ?: $filter->getName(), // Prefer label, fallback to name
            'type' => $this->mapFilterType($filter),
        ];

        if ($filter instanceof TernaryFilter) {
            // Condition is implicit (true/false/all)
        } elseif ($filter instanceof SelectFilter) {
            $baseInfo['optionsSource'] = 'Dynamic/Callable'; // Getting exact source is complex
            // Try to get options if they are simple array
            if (method_exists($filter, 'getOptions') && is_array($options = $filter->getOptions())) {
                $baseInfo['optionsSource'] = $options;
            }
        }
        // Add more specific filter type mappings here if needed

        return $baseInfo;
    }

    protected function mapFilterType(BaseFilter $filter): string
    {
        return match (true) {
            $filter instanceof TernaryFilter => 'boolean',
            $filter instanceof SelectFilter => 'select',
            // Add more mappings as needed
            default => class_basename($filter), // Fallback to class name
        };
    }

    protected function mapTableAction(Action | BulkAction $action): string
    {
        // Map common actions to simple strings, fallback to action name
        $name = $action->getName();
        return match ($name) {
            'view', 'edit', 'delete', 'forceDelete', 'restore', 'replicate' => $name,
            default => $name, // Return the action name itself
        };
        // Could potentially add more details like label, icon, color if needed
    }

    protected function extractRelationshipsInfo(Resource $resource): array
    {
        if (!method_exists($resource, 'getRelations')) {
            return [];
        }

        $relationshipManagers = $resource::getRelations();
        $relationships = [];

        foreach ($relationshipManagers as $managerClass) {
            try {
                $manager = app($managerClass);
                // Relationship details are often defined within the manager or inferred by naming.
                // This requires more specific introspection or assumptions based on conventions.
                // Placeholder: Use manager class name as key.
                $relationName = $manager->getRelationshipName(); // Assuming this method exists or convention

                $relationships[$relationName] = [
                    'type' => 'hasMany', // Placeholder - determining type requires deeper inspection
                    'manager' => $managerClass,
                    // 'model' => $manager->getRelatedModel(), // Requires standard method
                    // 'foreignKey' => $manager->getForeignKey(), // Requires standard method
                ];
            } catch (\Throwable $e) {
                // Log error if manager instantiation fails
            }
        }

        return $relationships;
    }

    protected function extractPagesInfo(Resource $resource): array
    {
        $pages = [];
        foreach ($resource::getPages() as $pageName => $pageConfig) {
            // Attempt to get URL, handle potential errors if parameters are needed
            try {
                // Provide dummy params for routes needing them (like 'edit')
                $params = [];
                if (in_array($pageName, ['edit', 'view'])) {
                    // Need a record identifier - using a placeholder
                    $modelClass = $resource::getModel();
                    $dummyId = $modelClass::query()->value('id') ?? '{record}'; // Get first ID or use placeholder
                    $params['record'] = $dummyId;
                }
                $pages[$pageName] = $resource::getUrl($pageName, $params);
            } catch (\Exception $e) {
                // Could not generate URL (e.g., missing required parameters)
                $pages[$pageName] = "Error generating URL for '{$pageName}': " . $e->getMessage();
            }
        }
        return $pages;
    }

    public function getTool(string $name): ?\Prism\Prism\Tool
    {
        // TODO: Avoid building all tools if we only need one
        return $this->getTools()->first(
            fn ($tool) => $tool->name() === $name
        );
    }
}