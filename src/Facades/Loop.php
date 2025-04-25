<?php

namespace Kirschbaum\Loop\Facades;

use Illuminate\Support\Facades\Facade;

/** @mixin \Kirschbaum\Loop\Loop */
class Loop extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Kirschbaum\Loop\Loop::class;
    }
}
