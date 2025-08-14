<?php

namespace AlysonTrizotto\DdlCrud;

use Illuminate\Support\ServiceProvider;

class DdlCrudServiceProvider extends ServiceProvider
{
    public function register()
    {
        // ...
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AlysonTrizotto\DdlCrud\Console\Commands\MakeCrudFromDdl::class,
            ]);
        }
        // Publicação dos stubs customizados
        $this->publishes([
            __DIR__.'/../stubs/cascade' => base_path('stubs/cascade'),
        ], 'stubs');
    }
}
