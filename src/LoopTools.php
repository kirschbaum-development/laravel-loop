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

    /**
     * Add a tool to the registry if not already present (prevents duplicates)
     */
    public function addTool(Tool $tool): void
    {
        $toolName = $tool->getName();

        if (! $this->tools->contains(function ($existingTool) use ($toolName) {
            return $existingTool->getName() === $toolName;
        })) {
            $this->tools->push($tool);
        }
    }

    /**
     * Remove a tool by name and return success status
     */
    public function removeTool(string $name): bool
    {
        $initialCount = $this->tools->count();

        $this->tools = $this->tools->reject(function ($tool) use ($name) {
            return $tool->getName() === $name;
        });

        return $initialCount > $this->tools->count();
    }

    /**
     * Register a tool (alias for addTool)
     */
    public function registerTool(Tool $tool): void
    {
        $this->addTool($tool);
    }

    public function registerToolkit(Toolkit $toolkit): void
    {
        $this->toolkits[] = $toolkit;

        foreach ($toolkit->getTools() as $tool) {
            $this->addTool($tool);
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
