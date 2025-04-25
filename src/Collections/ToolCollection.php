<?php

namespace Kirschbaum\Loop\Collections;

use Illuminate\Support\Collection;
use Kirschbaum\Loop\Contracts\Tool;

/** @extends Collection<array-key, Tool> */
class ToolCollection extends Collection
{
    public function getTool(string $name): ?Tool
    {
        return $this->first(fn (Tool $tool) => $tool->getName() === $name);
    }
}
