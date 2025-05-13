<?php

namespace Kirschbaum\Loop\Toolkits;

use Filament\Resources\Resource;
use Kirschbaum\Loop\Collections\ToolCollection;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Toolkit;
use Kirschbaum\Loop\Enums\Mode;
use Kirschbaum\Loop\Tools\Filament\DescribeFilamentResourceTool;
use Kirschbaum\Loop\Tools\Filament\GetFilamentResourceDataTool;
use Kirschbaum\Loop\Tools\Filament\ListFilamentResourcesTool;

/**
 * @method static self make(Resource[] $resources, Mode $mode = Mode::ReadOnly)
 */
class FilamentToolkit implements Toolkit
{
    use Makeable;

    /**
     * @param  resource[]  $resources
     */
    public function __construct(
        public readonly array $resources = [],
        public readonly Mode $mode = Mode::ReadOnly,
    ) {}

    public function getTools(): ToolCollection
    {
        return new ToolCollection([
            ListFilamentResourcesTool::make(
                resources: $this->resources
            ),
            DescribeFilamentResourceTool::make(),
            GetFilamentResourceDataTool::make(),
        ]);
    }
}
