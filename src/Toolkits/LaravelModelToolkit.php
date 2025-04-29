<?php

namespace Kirschbaum\Loop\Toolkits;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Pluralizer;
use Kirschbaum\Loop\Collections\ToolCollection;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Toolkit;
use Kirschbaum\Loop\Enums\Mode;
use Kirschbaum\Loop\ResourceData;
use Kirschbaum\Loop\Tools\Models\CreateModelTool;
use Kirschbaum\Loop\Tools\Models\DeleteModelTool;
use Kirschbaum\Loop\Tools\Models\DescribeModelTool;
use Kirschbaum\Loop\Tools\Models\FindModelTool;
use Kirschbaum\Loop\Tools\Models\ListModelsTool;
use Kirschbaum\Loop\Tools\Models\UpdateModelTool;

/**
 * @method static self make(Model[] $models, Mode $mode = Mode::ReadOnly)
 */
class LaravelModelToolkit implements Toolkit
{
    use Makeable;

    /**
     * @param  list<Model>  $models
     */
    public function __construct(
        public readonly array $models = [],
        public readonly Mode $mode = Mode::ReadOnly,
    ) {}

    public function getTools(): ToolCollection
    {
        $tools = new ToolCollection;

        foreach ($this->models as $model) {
            $tools->merge($this->buildModelTools($model));
        }

        return $tools;
    }

    protected function buildModelTools(string $model): ToolCollection
    {
        $aiResourceData = $this->getAiResourceData($model);

        return new ToolCollection([
            DescribeModelTool::make($aiResourceData->model, $aiResourceData->label, $aiResourceData->pluralLabel),
            ListModelsTool::make($aiResourceData->model, $aiResourceData->pluralLabel),
            FindModelTool::make($aiResourceData->model, $aiResourceData->label),
            // ...$this->mode === Mode::ReadWrite ? [
            //     CreateModelTool::make($aiResourceData->model, $aiResourceData->label),
            //     UpdateModelTool::make($aiResourceData->model, $aiResourceData->label),
            //     DeleteModelTool::make($aiResourceData->model, $aiResourceData->label),
            // ] : [],
        ]);
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
}
