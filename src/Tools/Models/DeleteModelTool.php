<?php

namespace Kirschbaum\Loop\Tools\Models;

use Illuminate\Database\Eloquent\Model;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Tool as PrismTool;

/**
 * @method static self make(string $modelClass, string $label)
 */
class DeleteModelTool implements Tool
{
    use Makeable;

    public function __construct(
        /** @param  class-string<Model> $modelClass */
        private string $modelClass,
        private string $label,
    ) {}

    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as($this->getName())
            ->for("Delete a {$this->label}.")
            ->withObjectParameter(
                'data',
                "Object containing parameters to delete a {$this->label}",
                [
                    new NumberSchema('id', "The ID of the {$this->label} to delete"),
                ],
                ['id'], // Required fields
                false, // Don't allow additional properties
                true // Required parameter
            )
            ->using(function ($data): string {
                // Convert the object to array if it's not already
                if (is_object($data)) {
                    $data = json_decode((string) json_encode($data), true) ?: [];
                }

                // Extract id parameter (should always be present due to required fields)
                $id = $data['id'];

                $record = $this->modelClass::find($id);

                if (! $record) {
                    return "{$this->label} with ID {$id} not found.";
                }

                try {
                    $record->delete();

                    return "Successfully deleted {$this->label} with ID: {$id}";
                } catch (\Exception $e) {
                    return "Failed to delete {$this->label}: ".$e->getMessage();
                }
            });
    }

    public function getName(): string
    {
        $modelName = class_basename($this->modelClass);

        return strtolower($modelName).'_delete_model';
    }
}
