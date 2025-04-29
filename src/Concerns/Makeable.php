<?php

namespace Kirschbaum\Loop\Concerns;

trait Makeable
{
    /** @phpstan-ignore-next-line */
    public static function make(...$args): static
    {
        /** @phpstan-ignore-next-line */
        return new static(...$args);
    }
}
