<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register the package services.
     */
    public function register(): void
    {
        $this->app->singleton('laravel-cross-eloquent-search', SearchFactory::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'laravel-cross-eloquent-search',
        ];
    }
}
