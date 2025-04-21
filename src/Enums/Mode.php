<?php

namespace Kirschbaum\Loop\Enums;

enum Mode: string
{
    case ReadOnly = 'read-only';
    case ReadWrite = 'read-write';
}
