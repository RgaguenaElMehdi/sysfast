<?php

namespace Sysfast;

use Illuminate\Support\ServiceProvider;

class CrudServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.sysfast.generator' ,function($app){
            return $app['Sysfast\Commands\generateCrud'];
        });
        $this->commands('command.sysfast.generator');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
