<?php

namespace Kirschbaum\Loop\Tools\Filament;

use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Kirschbaum\Loop\Contracts\Tool;
use Prism\Prism\Tool as PrismTool;

class ListFilamentResourcesTool implements Tool
{
    /**
     * @param Resource[] $resources
     */
    public function __construct(private array $resources = []) {}

    /**
     * @param Resource[] $resources
     */
    public static function make(array $resources = []): static
    {
        return new self($resources);
    }

    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as('list_filament_resources')
            ->for('Lists all available Filament resources. Filament resources are used to list, fetch and manage data for a given data resource (database table, model, etc.)')
            ->using(function () {
                return collect($this->getResources())->map(
                    fn (string $resource) => $resource
                )->implode(', ');
            });
    }

    public function getName(): string
    {
        return 'list_filament_resources';
    }

    private function getResources(): Collection
    {
        $resources = $this->resources;

        if (empty($resources)) {
            $resources = Filament::getResources();
        }

        return collect($resources);
    }
}