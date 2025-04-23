<?php

namespace Kirschbaum\Loop\Contracts;

use Illuminate\Support\Collection;
use Kirschbaum\Loop\Collections\ToolCollection;

interface Toolkit
{
    public static function make(...$args): static;

    public function getTools(): ToolCollection;
}