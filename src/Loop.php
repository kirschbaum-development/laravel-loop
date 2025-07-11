<?php

namespace Kirschbaum\Loop;

use Illuminate\Support\Collection;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Contracts\Toolkit;
use Prism\Prism\Tool as PrismTool;

class Loop
{
    protected string $context = '';

    public function __construct(protected LoopTools $loopTools) {}

    public function setup(): void {}

    public function context(string $context): static
    {
        $this->context .= "\n\n".$context;

        return $this;
    }

    /**
     * Add a tool dynamically to the registry
     */
    public function addTool(Tool $tool): static
    {
        $this->loopTools->addTool($tool);

        return $this;
    }

    /**
     * Remove a tool dynamically from the registry
     */
    public function removeTool(string $name): static
    {
        $this->loopTools->removeTool($name);

        return $this;
    }

    /**
     * Clear all registered tools and toolkits
     */
    public function clear(): static
    {
        $this->loopTools->clear();

        return $this;
    }

    public function tool(Tool $tool): static
    {
        $this->loopTools->registerTool($tool);

        return $this;
    }

    public function toolkit(Toolkit $toolkit): static
    {
        $this->loopTools->registerToolkit($toolkit);

        return $this;
    }

    public function getPrismTools(): Collection
    {
        return $this->loopTools
            ->getTools()
            ->toBase()
            ->map(fn (Tool $tool) => $tool->build());
    }

    public function getPrismTool(string $name): PrismTool
    {
        return $this->loopTools
            ->getTools()
            ->getTool($name)
            ->build();
    }
}
