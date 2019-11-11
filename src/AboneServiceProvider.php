<?php

namespace Zek\Abone;

use Illuminate\Support\ServiceProvider;

class AboneServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {

        $this->loadMigrationsFrom(__DIR__.'/migrations');

    }
}
