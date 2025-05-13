<?php

namespace Kirschbaum\Loop;

use Kirschbaum\Loop\Commands\LoopMcpServerStartCommand;
use Kirschbaum\Loop\Services\SseService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LoopServiceProvider extends PackageServiceProvider
{
    public static string $name = 'laravel-loop';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name(static::$name)
            ->hasRoutes(['api'])
            ->hasConfigFile()
            ->hasCommands([
                LoopMcpServerStartCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        $this->app->scoped(Loop::class, function ($app) {
            $loop = new Loop;
            $loop->setup();

            return $loop;
        });

        $this->app->singleton(SseService::class, function ($app) {
            return new SseService;
        });
    }
}
