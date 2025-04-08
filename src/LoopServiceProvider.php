<?php

namespace Kirschbaum\Loop;

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
            ->name(static::$name);
    }

    public function packageBooted(): void
    {

    }
}
