<?php

namespace Kirschbaum\Loop\Toolkits;

use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Toolkit;
use Kirschbaum\Loop\Collections\ToolCollection;
use Kirschbaum\Loop\Tools\Models\CreateModelFactoryTool;
use Kirschbaum\Loop\Tools\Models\DescribeModelFactoryTool;

class LaravelFactoriesToolkit implements Toolkit
{
    use Makeable;

    public function getTools(): ToolCollection
    {
        return new ToolCollection([
            DescribeModelFactoryTool::make(),
            CreateModelFactoryTool::make(),
        ]);
    }
}
