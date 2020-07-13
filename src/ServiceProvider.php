<?php

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register the shared binding.
     */
    public function register()
    {
        $this->app->singleton('laravel-cross-eloquent-search', function () {
            return new SearchFactory;
        });
    }
}
