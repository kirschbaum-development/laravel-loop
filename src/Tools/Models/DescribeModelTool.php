<?php

namespace Kirschbaum\Loop\Tools\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Tools\Models\Concerns\ProvidesModelColumns;
use Prism\Prism\Tool as PrismTool;
use ReflectionClass;

/**
 * @method static self make(string $modelClass, string $label, string $pluralLabel)
 */
class DescribeModelTool implements Tool
{
    use Makeable;
    use ProvidesModelColumns;

    public function __construct(
        /** @var class-string<Model> */
        private string $modelClass,
        private string $label,
        private string $pluralLabel
    ) {}

    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as($this->getName())
            ->for("Get detailed information about the {$this->pluralLabel} table, with all its fields and relationships.")
            ->using(function (): string {
                try {
                    $tableColumns = $this->getTableColumns($this->modelClass);
                    $relationships = $this->getDocblockRelationships($this->modelClass);

                    /** @var Model $model */
                    $model = new $this->modelClass;
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

                    return (string) json_encode($data, JSON_PRETTY_PRINT);
                } catch (Exception $e) {
                    return (string) json_encode(['error' => 'Error retrieving model information: '.$e->getMessage()]);
                }
            });
    }

    public function getName(): string
    {
        $modelName = class_basename($this->modelClass);

        return strtolower($modelName).'_describe_model';
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<array-key, array<array-key, mixed>>
     */
    protected function getDocblockRelationships(string $modelClass): array
    {
        $reflection = new ReflectionClass($modelClass);
        $docComment = $reflection->getDocComment();

        if (! $docComment) {
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
                $model = new $modelClass;
                $relatedModel = $matches[1];
                $property = ltrim($matches[2], '$');

                if (! method_exists($model, $property)) {
                    continue;
                }

                /** @var \ReflectionNamedType|null $returnType */
                $returnType = $reflection->getMethod($property)->getReturnType();

                /** @var object $relation */
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
