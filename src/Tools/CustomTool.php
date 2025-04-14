<?php

namespace Kirschbaum\Loop\Tools;

use Closure;

class CustomTool implements Tool
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly Closure $handler,
    ) {
    }

    // TODO: Implement
}