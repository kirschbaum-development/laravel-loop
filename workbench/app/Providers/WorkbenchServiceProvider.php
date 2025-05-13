<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Kirschbaum\Loop\Facades\Loop;
use Kirschbaum\Loop\Toolkits;
use Workbench\App\Models\User;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        Loop::toolkit(Toolkits\LaravelModelToolkit::make(
            models: [
                User::class,
            ]
        ));
        // Loop::toolkit(Toolkits\LaravelFactoriesToolkit::make());
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
