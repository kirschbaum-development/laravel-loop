<?php

namespace Workbench\App\Providers;

use Kirschbaum\Loop\Toolkits;
use Workbench\App\Models\User;
use Kirschbaum\Loop\Facades\Loop;
use Illuminate\Support\ServiceProvider;

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
    public function boot(): void
    {
    }
}
