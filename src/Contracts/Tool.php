<?php

namespace Kirschbaum\Loop\Contracts;

use Prism\Prism\Tool as PrismTool;

interface Tool
{
    public static function make(): static;

    public function build(): PrismTool;

    public function getName(): string;
}
