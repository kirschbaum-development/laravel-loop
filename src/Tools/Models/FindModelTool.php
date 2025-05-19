<?php

namespace Kirschbaum\Loop\Tools\Models;

use Illuminate\Database\Eloquent\Model;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Tools\Models\Concerns\FormatsModelAttributes;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Tool as PrismTool;

/**
 * @method static self make(string $modelClass, string $label)
 */
class FindModelTool implements Tool
{
    use FormatsModelAttributes;
    use Makeable;

    public function __construct(
        /** @param  class-string<Model> $modelClass */
        private string $modelClass,
        private string $label,
    ) {}

    public function getName(): string
    {
        $modelName = class_basename($this->modelClass);

        return strtolower($modelName).'_find_model';
    }

    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as($this->getName())
            ->for("Fetch a specific {$this->label} by ID or its primary key.")
            ->withObjectParameter(
                'data',
                "Object containing parameters to fetch a {$this->label}",
                [
                    new NumberSchema('id', "The ID of the {$this->label} to fetch"),
                ],
                requiredFields: ['id'],
                allowAdditionalProperties: false,
                required: true
            )
            ->using(function ($data): string {
                if (is_object($data)) {
                    $data = json_decode((string) json_encode($data), true) ?: [];
                }

                $id = $data['id'];
                $record = $this->modelClass::find($id);

                if (! $record) {
                    return "{$this->label} with ID {$id} not found.";
                }

                $result = "{$this->label} details:\n\n";
                $result .= $this->formatModelAttributes($record, true);

                return $result;
            });
    }
}
