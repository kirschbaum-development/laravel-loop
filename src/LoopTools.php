<?php

namespace Kirschbaum\Loop;

use Kirschbaum\Loop\Collections\ToolCollection;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Contracts\Toolkit;

class LoopTools
{
    protected ToolCollection $tools;

    /** @var array<int, Toolkit> */
    protected array $toolkits = [];

    public function __construct()
    {
        $this->tools = new ToolCollection;
    }

    public function registerTool(Tool $tool): void
    {
        $this->tools->push($tool);
    }

    public function registerToolkit(Toolkit $toolkit): void
    {
        $this->toolkits[] = $toolkit;

        foreach ($toolkit->getTools() as $tool) {
            $this->registerTool($tool);
        }
    }

    /**
     * Get all registered tools
     */
    public function getTools(): ToolCollection
    {
        return $this->tools;
    }

    /**
     * Get all registered toolkits
     *
     * @return array<int, Toolkit>
     */
    public function getToolkits(): array
    {
        return $this->toolkits;
    }

    /**
     * Clear all registrations
     */
    public function clear(): void
    {
        $this->tools = new ToolCollection;
        $this->toolkits = [];
    }
}
