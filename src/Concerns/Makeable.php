<?php

namespace Kirschbaum\Loop\Concerns;

trait Makeable
{
    public static function make(...$args): static
    {
        return new self(...$args);
    }
}
