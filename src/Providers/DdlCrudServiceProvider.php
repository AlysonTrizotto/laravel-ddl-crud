<?php

namespace AlysonTrizotto\DdlCrud\Providers;

use Illuminate\Support\ServiceProvider;

class DdlCrudServiceProvider extends ServiceProvider
{
    public function register()
    {
        // ... bind or merge configs if needed
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AlysonTrizotto\DdlCrud\Console\Commands\MakeCrudFromDdl::class,
                \AlysonTrizotto\DdlCrud\Console\Commands\RemoveCrudRoutes::class,
            ]);
        }

        // Publish custom stubs
        $this->publishes([
            // Stubs live under src/stubs/cascade
            dirname(__DIR__).'/stubs/cascade' => base_path('stubs/cascade'),
        ], 'stubs');
    }
}
