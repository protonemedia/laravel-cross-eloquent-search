<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use ProtoneMedia\LaravelCrossEloquentSearch\Contracts\SearcherContract;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register the package services.
     */
    public function register(): void
    {
        $this->app->singleton(SearcherContract::class, function ($app) {
            return new SearchFactory;
        });

        $this->app->singleton('laravel-cross-eloquent-search', function ($app) {
            return $app[SearcherContract::class];
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            SearcherContract::class,
            'laravel-cross-eloquent-search',
        ];
    }
}
