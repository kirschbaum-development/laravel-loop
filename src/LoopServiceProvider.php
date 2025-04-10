<?php

namespace Kirschbaum\Loop;

use Illuminate\Routing\Router;
use Kirschbaum\Loop\Http\Middleware\CorsMiddleware;
use Kirschbaum\Loop\Http\Middleware\McpAuthMiddleware;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LoopServiceProvider extends PackageServiceProvider
{
    public static string $name = 'loop';

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
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        // Register the singleton for Loop
        $this->app->singleton(Loop::class, function ($app) {
            $loop = new Loop();
            $loop->setup();
            return $loop;
        });

        // Register middleware
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('loop.cors', CorsMiddleware::class);
        $router->aliasMiddleware('loop.auth', McpAuthMiddleware::class);

        // Apply middleware to route group
        $router->middlewareGroup('loop.api', [
            'loop.cors',
        ]);

        // Add auth middleware if enabled in config
        if (config('loop.enable_auth', true)) {
            $router->middlewareGroup('loop.api', [
                'loop.auth',
            ]);
        }
    }
}
