<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

interface SearchResultContract
{
    /**
     * Get the collection of search results.
     */
    public function items(): EloquentCollection;

    /**
     * Check if the results are paginated.
     */
    public function isPaginated(): bool;

    /**
     * Get the paginator instance if results are paginated.
     */
    public function paginator(): ?LengthAwarePaginator;

    /**
     * Get the total number of results.
     */
    public function total(): int;

    /**
     * Check if there are any results.
     */
    public function isEmpty(): bool;

    /**
     * Check if there are results.
     */
    public function isNotEmpty(): bool;

    /**
     * Get the number of items in the current page/collection.
     */
    public function count(): int;
}