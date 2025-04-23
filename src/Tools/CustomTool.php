<?php

namespace Kirschbaum\Loop\Tools;

use Closure;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Prism\Prism\Tool as PrismTool;

class CustomTool implements Tool
{
    use Makeable;

    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly Closure $handler,
    ) {
    }

    public function build(): PrismTool
    {
        return app(PrismTool::class)
            ->as($this->getName())
            ->for($this->description)
            ->withParameter($this->parameters)
            ->using($this->handler);
    }

    public function getName(): string
    {
        return $this->name;
    }
}