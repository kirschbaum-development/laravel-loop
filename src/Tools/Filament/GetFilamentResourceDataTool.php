<?php

namespace Kirschbaum\Loop\Tools\Filament;

use Exception;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use JsonException;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Exceptions\LoopMcpException;
use Kirschbaum\Loop\Tools\Filament\Concerns\ProvidesFilamentResourceInstance;
use Prism\Prism\Tool as PrismTool;

class GetFilamentResourceDataTool implements Tool
{
    use Makeable;
    use ProvidesFilamentResourceInstance;

    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as($this->getName())
            ->for('Gets the data for a given Filament resource, applying optional filters provided in the describe_filament_resource tool.')
            ->withStringParameter('resource', 'The resource class name of the resource to get data for, from the list_filament_resources tool.', required: true)
            ->withStringParameter('filters', 'JSON string of filters to apply (e.g., \'{"status": "published", "author_id": [1, 2]}\').', required: false)
            ->using(function (string $resource, ?string $filters = null) {
                $resource = $this->getResourceInstance($resource);
                $filters = $this->parseFilters($filters);

                try {
                    $listPageClass = $resource::getPages()['index'];
                    $component = $listPageClass->getPage();
                    $listPage = new $component;
                    $listPage->bootedInteractsWithTable();
                    $table = $listPage->getTable();
                    $tableColumns = $table->getColumns();

                    collect($tableColumns)
                        ->filter(fn (Column $column) => $column->isSearchable() && ! str_contains($column->getName(), '.')) // Only direct model attributes for now
                        ->filter(fn (Column $column) => isset($filters[$column->getName()]))
                        ->each(function (Column $column) use (&$listPage, $filters) {
                            $listPage->tableSearch = $filters[$column->getName()];
                        });

                    foreach ($listPage->getTable()->getFilters() as $filter) {
                        if (method_exists($filter, 'isMultiple') && $filter->isMultiple()) {
                            $listPage->tableFilters[$filter->getName()] = [
                                'values' => isset($filters[$filter->getName()])
                                    ? (array) $filters[$filter->getName()]
                                    : null,
                            ];
                        } else {
                            $listPage->tableFilters[$filter->getName()] = [
                                'value' => $filters[$filter->getName()] ?? null,
                            ];
                        }
                    }

                    // TODO: Allow the tool to specify the number of results to return with a max
                    $results = $listPage->getFilteredTableQuery()->take(10)->get();

                    $outputData = $results->map(function (Model $model) use ($tableColumns) {
                        $rowData = [
                            $model->getKeyName() => $model->getKey(),
                        ];

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
                                        Log::warning("Relation '{$relationName}' not found on model for column '{$columnName}'.");
                                    }
                                } else {
                                    $value = $model->getAttribute($columnName);
                                }

                                $rowData[$columnName] = $value;
                            } catch (Exception $e) {
                                $rowData[$columnName] = null;
                                Log::error("Could not retrieve value for column '{$columnName}' on model ID {$model->getKey()}': {$e->getMessage()}");
                            }
                        }

                        return $rowData;
                    });

                    return json_encode($outputData);
                } catch (Exception $e) {
                    Log::error("[Laravel Loop] Error processing resource data: {$e->getMessage()}");
                    Log::debug('[Laravel Loop] Error trace: '.$e->getTraceAsString());

                    return sprintf('Error processing data for resource %s: %s', get_class($resource), $e->getMessage());
                }
            });
    }

    public function getName(): string
    {
        return 'get_filament_resource_data';
    }

    protected function parseFilters(?string $filtersJson = null): array
    {
        $filters = [];

        if (! $filtersJson) {
            return $filters;
        }

        try {
            $decodedFilters = json_decode($filtersJson, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($decodedFilters)) {
                $filters = $decodedFilters;
            } else {
                throw new LoopMcpException('Error: Invalid JSON provided for filters.');
            }
        } catch (JsonException $e) {
            logger()->error($e);
            throw new LoopMcpException(sprintf('Error decoding filters JSON: %s', $e->getMessage()));
        }

        return $filters;
    }
}
