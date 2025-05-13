<?php

namespace Kirschbaum\Loop\Tools\Filament;

use Exception;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Prism\Prism\Tool as PrismTool;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Column;
use Illuminate\Support\Facades\Log;
use Kirschbaum\Loop\Contracts\Tool;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Kirschbaum\Loop\Concerns\Makeable;
use Filament\Forms\Components\Fieldset;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\BaseFilter;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Livewire\Component as LivewireComponent;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Contracts\TranslatableContentDriver;
use Kirschbaum\Loop\Tools\Filament\Concerns\ProvidesFilamentResourceInstance;

class DescribeFilamentResourceTool implements Tool
{
    use Makeable;
    use ProvidesFilamentResourceInstance;

    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as($this->getName())
            ->for('Describes the structure, fields, columns, actions, and relationships for a given Filament resource')
            ->withStringParameter('resource', 'The class name of the resource to describe.', required: true)
            ->using(function (string $resource) {
                $resource = $this->getResourceInstance($resource);

                return json_encode([
                    'resource' => class_basename($resource),
                    'model' => $resource::getModel(),
                    // 'form' => $this->extractFormSchema($resource),
                    'table' => $this->extractTableSchema($resource),
                    'relationships' => $this->extractRelationshipsInfo($resource),
                ]);
            });
    }

    public function getName(): string
    {
        return 'describe_filament_resource';
    }

    protected function extractBasicInfo(Resource $resource): array
    {
        return [
            'resource' => class_basename($resource),
            'model' => $resource::getModel(),
        ];
    }

    protected function extractFormSchema(Resource $resource): array
    {
        $livewireComponent = new class extends LivewireComponent implements HasForms {
            use \Filament\Forms\Concerns\InteractsWithForms;
        };

        $form = $resource::form(new Form($livewireComponent));
        $fields = collect($form->getComponents(true))
            ->reject(fn (Component $component) => $component instanceof Grid || $component instanceof Fieldset)
            ->map(fn (Component $component) => $this->mapFormComponent($component, $resource))
            ->filter()
            ->values()
            ->all();

        return ['fields' => $fields];
    }

    protected function extractNavigationInfo(Resource $resource): array
    {
        return [
            'group' => $resource::getNavigationGroup(),
            'icon' => $resource::getNavigationIcon(),
            'label' => $resource::getNavigationLabel() ?: $resource::getPluralModelLabel(),
        ];
    }

    protected function extractRelationshipsInfo(Resource $resource): array
    {
        if (! method_exists($resource, 'getRelations')) {
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

    protected function extractTableSchema(Resource $resource): array
    {
        try {
            $livewireComponent = new class extends LivewireComponent implements HasTable {
                use \Filament\Tables\Concerns\InteractsWithTable;

                public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
                {
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

    protected function mapFilterType(BaseFilter $filter): string
    {
        return match (true) {
            $filter instanceof TernaryFilter => 'boolean',
            $filter instanceof SelectFilter => 'select',
            // Add more mappings as needed
            default => class_basename($filter), // Fallback to class name
        };
    }

    protected function mapFormComponent(Component $component, Resource $resource): ?array
    {
        $baseInfo = [
            'name' => $component->getName(),
            'type' => $this->mapComponentType($component),
            'label' => $component->getLabel(),
            'required' => method_exists($component, 'isRequired') ? $component->isRequired() : null,
            'disabled' => method_exists($component, 'isDisabled') ? $component->isDisabled() : null,
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

    protected function mapTableAction(Action|BulkAction $action): string
    {
        // Map common actions to simple strings, fallback to action name
        $name = $action->getName();

        return match ($name) {
            'view', 'edit', 'delete', 'forceDelete', 'restore', 'replicate' => $name,
            default => $name, // Return the action name itself
        };
        // Could potentially add more details like label, icon, color if needed
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
            'name' => $filter->getName(),
            'label' => $filter->getLabel(),
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
}
