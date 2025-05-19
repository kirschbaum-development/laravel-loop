<?php

namespace Kirschbaum\Loop\Tools\Filament;

use Filament\Actions\Action as PageAction;
use Filament\Resources\Pages\Page;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Log;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Tools\Filament\Concerns\ProvidesFilamentResourceInstance;
use Livewire\Component as LivewireComponent;
use Prism\Prism\Tool as PrismTool;
use ReflectionClass;

class ExecuteResourceActionTool implements Tool
{
    use Makeable;
    use ProvidesFilamentResourceInstance;

    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as($this->getName())
            ->for('Executes a specified action on a Filament resource. Always double check with the user before executing any action.')
            ->withStringParameter('resource', 'The class name of the resource to execute action on.', required: true)
            ->withStringParameter('action', 'The name of the action to execute.', required: true)
            ->withStringParameter('actionType', 'The type of action: "bulk".', required: false)
            ->withStringParameter('recordIds', 'JSON array of record IDs for bulk actions.', required: false)
            ->using(function (
                string $resource,
                string $action,
                ?string $actionType = 'bulk',
                ?string $recordIds = null,
            ) {
                $resourceInstance = $this->getResourceInstance($resource);

                try {
                    $livewireComponent = new class extends LivewireComponent implements HasTable
                    {
                        use \Filament\Tables\Concerns\InteractsWithTable;

                        public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
                        {
                            return null;
                        }
                    };

                    $table = $resourceInstance::table(new \Filament\Tables\Table($livewireComponent));

                    if ($actionType === 'bulk') {
                        $recordIds = (array) ($recordIds ? json_decode($recordIds, true) : []);
                        $records = $resource::getModel()::find($recordIds);
                        $actions = $table->getBulkActions();

                        $targetAction = collect($actions)
                            ->first(fn (BulkAction $actionObj) => $actionObj->getName() === $action);

                        if (! $targetAction) {
                            return json_encode([
                                'success' => false,
                                'message' => "Bulk action '{$action}' not found on resource.",
                            ]);
                        }

                        $targetAction->records($records);
                        $result = $targetAction->call();
                    }

                    return json_encode([
                        'success' => true,
                        'result' => $result,
                    ]);
                } catch (\Throwable $e) {
                    Log::error($e);
                    Log::error("Error executing {$actionType} action {$action} on resource {$resource}: {$e->getMessage()}");

                    return json_encode([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ]);
                }
            });
    }

    protected function getPageInstance(string $pageClass, ?string $recordId, $resourceInstance): Page
    {
        $reflection = new ReflectionClass($pageClass);

        if ($recordId) {
            $modelClass = $resourceInstance::getModel();
            $record = app($modelClass)->find($recordId);

            return $reflection->newInstance($resourceInstance, $record);
        }

        return $reflection->newInstance($resourceInstance);
    }

    public function getName(): string
    {
        return 'execute_filament_resource_action';
    }
}