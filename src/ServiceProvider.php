<?php

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->app->singleton('laravel-cross-eloquent-search', function () {
            return new SearchFactory;
        });
    }
}
