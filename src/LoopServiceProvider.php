<?php

namespace Kirschbaum\Loop;

use Kirschbaum\Loop\Commands\LoopMcpCallCommand;
use Kirschbaum\Loop\Commands\LoopMcpConfigCommand;
use Kirschbaum\Loop\Commands\LoopMcpServerStartCommand;
use Kirschbaum\Loop\Services\SseDriverManager;
use Kirschbaum\Loop\Services\SseService;
use Kirschbaum\Loop\Services\SseSessionManager;
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
                LoopMcpConfigCommand::class,
                LoopMcpCallCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        $this->app->scoped(Loop::class, function ($app) {
            $loop = new Loop;
            $loop->setup();

            return $loop;
        });

        $this->app->bind(SseService::class, function ($app) {
            return new SseService;
        });

        $this->app->bind(SseSessionManager::class, function ($app) {
            return new SseSessionManager($app->make(SseDriverManager::class));
        });

        $this->app->bind(SseDriverManager::class, function ($app) {
            return new SseDriverManager($app);
        });
    }
}
