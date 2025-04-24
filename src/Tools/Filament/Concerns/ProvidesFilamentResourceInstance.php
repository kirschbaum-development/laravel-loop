<?php

namespace Kirschbaum\Loop\Tools\Filament\Concerns;

use Exception;
use Filament\Resources\Resource;
use Kirschbaum\Loop\Exceptions\LoopMcpException;

trait ProvidesFilamentResourceInstance
{
    protected function getResourceInstance(string $resourceClass): Resource
    {
        try {
            $resource = app($resourceClass);

            if (! $resource instanceof Resource) {
                throw new LoopMcpException(sprintf('Could not find %s resource class', $resourceClass));
            }

            return $resource;
        } catch (Exception $e) {
            throw new LoopMcpException(sprintf('Could describe %s resource class. Error: %s', $resourceClass, $e->getMessage()));
        }
    }
}
