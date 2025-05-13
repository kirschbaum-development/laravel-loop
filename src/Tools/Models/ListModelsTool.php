<?php

namespace Kirschbaum\Loop\Tools\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Tools\Models\Concerns\FormatsModelAttributes;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool as PrismTool;

/**
 * @method static self make(string $modelClass, string $pluralLabel)
 */
class ListModelsTool implements Tool
{
    use FormatsModelAttributes;
    use Makeable;

    public function __construct(
        /** @param  class-string<Model> $modelClass */
        private string $modelClass,
        private string $pluralLabel
    ) {}

    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as($this->getName())
            ->for("List all {$this->pluralLabel}. You can filter results by using the fields from the describe tool.")

            ->withObjectParameter(
                'data',
                "Object containing parameters for listing {$this->pluralLabel}",
                [
                    new NumberSchema('limit', "Maximum number of {$this->pluralLabel} to return"),
                    new StringSchema('order_by', 'Field to order results by'),
                    new StringSchema('order_direction', 'Order direction (asc or desc)'),
                    new StringSchema('filters', "Filters (JSON) to apply when listing {$this->pluralLabel}"),
                ],
                requiredFields: [],
                allowAdditionalProperties: true,
                required: true
            )
            ->using(function ($data): string {
                try {
                    if (is_object($data)) {
                        $data = json_decode((string) json_encode($data), true) ?? [];
                    }

                    $limit = $data['limit'] ?? 10;
                    $orderBy = $data['order_by'] ?? null;
                    $order_direction = $data['order_direction'] ?? 'asc';
                    $filters = json_decode($data['filters'] ?? null, true) ?? null;
                    $query = $this->modelClass::query();

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
                        return "No {$this->pluralLabel} found.";
                    }

                    $result = 'Found '.$records->count()." {$this->pluralLabel}:\n\n";

                    foreach ($records as $record) {
                        $result .= "ID: {$record->id}, ".$this->formatModelAttributes($record)."\n";
                    }

                    return $result;
                } catch (Exception $e) {
                    return "Error listing {$this->pluralLabel}: ".$e->getMessage();
                }
            });
    }

    public function getName(): string
    {
        $modelName = class_basename($this->modelClass);

        return strtolower($modelName).'_list_models';
    }
}
