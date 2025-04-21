<?php

namespace Kirschbaum\Loop;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Kirschbaum\Loop\Commands\LoopMcpServerStartCommand;

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
            ])
        ;
    }

    public function packageBooted(): void
    {
        $this->app->singleton(Loop::class, function ($app) {
            $loop = new Loop();
            $loop->setup();

            return $loop;
        });
    }
}
