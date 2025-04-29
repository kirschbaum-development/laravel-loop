<?php

declare(strict_types=1);

namespace Tests;

use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

#[WithMigration]
class TestCase extends Orchestra
{
    use LazilyRefreshDatabase;
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventingStrayRequests();
    }

    /**
     * Use this when you are using app() to create an instance of a class with parameters.
     * The normal mock() method does not correctly mock when using the container in this way.
     * e.g. app(Example::class, ['argument' => 'foo'])
     */
    protected function mockBind(string $abstract, ?Closure $callback = null): MockInterface
    {
        $mock = $this->mock($abstract, $callback);

        app()->offsetSet($abstract, $mock);

        return $mock;
    }
}
