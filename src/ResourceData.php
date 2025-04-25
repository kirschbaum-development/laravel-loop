<?php

namespace Kirschbaum\Loop;

class ResourceData
{
    public function __construct(
        public string $model,
        public string $label,
        public string $pluralLabel,
    ) {}
}
