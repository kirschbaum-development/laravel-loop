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

    /** @var callable|null */
    protected $changeCallback = null;

    protected ?string $toolsHash = null;

    public function __construct()
    {
        $this->tools = new ToolCollection;
    }

    /**
     * Register a tool if not already present (prevents duplicates)
     */
    public function registerTool(Tool $tool): void
    {
        $toolName = $tool->getName();

        if (! $this->tools->contains(function ($existingTool) use ($toolName) {
            return $existingTool->getName() === $toolName;
        })) {
            $this->tools->push($tool);
        }

        $this->notifyIfChanged();
    }

    /**
     * Remove a tool by name
     */
    public function removeTool(string $name): void
    {
        $originalCount = $this->tools->count();

        $this->tools = $this->tools->reject(function ($tool) use ($name) {
            return $tool->getName() === $name;
        });

        if ($this->tools->count() !== $originalCount) {
            $this->notifyIfChanged();
        }
    }

    /**
     * Register a callback to be called when tools change
     */
    public function onToolsChanged(callable $callback): void
    {
        $this->changeCallback = $callback;
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
        $this->notifyIfChanged();
    }

    /**
     * Check if tools have changed and notify callback if so
     */
    protected function notifyIfChanged(): void
    {
        $currentHash = $this->computeToolsHash();

        if ($currentHash !== $this->toolsHash) {
            $this->toolsHash = $currentHash;

            if ($this->changeCallback) {
                ($this->changeCallback)();
            }
        }
    }

    /**
     * Compute a hash of the current tools for change detection
     */
    protected function computeToolsHash(): string
    {
        $toolNames = $this->tools
            ->map(fn ($tool) => $tool->getName())
            ->sort()
            ->values()
            ->toArray();

        $json = json_encode($toolNames);

        return md5($json !== false ? $json : '[]');
    }
}
