<?php

namespace Kirschbaum\Loop\Tools\Models;

use Exception;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Pluralizer;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Contracts\Toolkit;
use Kirschbaum\Loop\Enums\Mode;
use Kirschbaum\Loop\ResourceData;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool as PrismTool;
use ReflectionClass;

/**
 * @method static self make(string $modelClass, string $label, string $pluralLabel)
 */
class DescribeModelTool implements Tool
{
    use Makeable;

    public function __construct(
        private string $modelClass,
        private string $label,
        private string $pluralLabel
    ) {
    }

    public function build(): PrismTool
    {
        return PrismTool::as($this->getName())
            ->for("Get detailed information about the {$this->pluralLabel} table, with all its fields and relationships.")
            ->using(function (): string {
                try {
                    $tableColumns = $this->getTableColumns($this->modelClass);
                    $relationships = $this->getDocblockRelationships($this->modelClass);

                    $model = new $this->modelClass();
                    $tableName = $model->getTable();
                    $primaryKey = $model->getKeyName();
                    $fillable = $model->getFillable();

                    $data = [
                        'label' => $this->label,
                        'basic_information' => [
                            'model_class' => $this->modelClass,
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

    public function getName(): string
    {
        $modelName = class_basename($this->modelClass);

        return strtolower($modelName) . '_describe';
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
}