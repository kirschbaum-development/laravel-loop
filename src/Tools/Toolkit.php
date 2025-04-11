<?php

namespace Kirschbaum\Loop\Tools;

use Prism\Prism\Tool;
use Illuminate\Support\Collection;

interface Toolkit
{
    public static function make(...$args): static;

    /**
     * @return Collection<Tool>
     */
    public function getTools(): Collection;

    public function getTool(string $name): ?Tool;
}