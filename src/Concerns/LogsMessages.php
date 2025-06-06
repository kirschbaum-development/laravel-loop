<?php

namespace Kirschbaum\Loop\Concerns;

use Illuminate\Support\Facades\Log;

trait LogsMessages
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $message, array $context = [], string $level = 'info'): void
    {
        if ($level !== 'error' && ! config()->boolean('loop.debug', false)) {
            return;
        }

        Log::{$level}(sprintf('[Laravel Loop] %s', $message), $context);
    }
}
