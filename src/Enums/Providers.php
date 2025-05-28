<?php

namespace Kirschbaum\Loop\Enums;

enum Providers: string
{
    case ClaudeCode = 'Claude Code';
    case ClaudeDesktop = 'Claude Desktop';
    case Cursor = 'Cursor';
    case Others = 'Others (JSON config)';
}
