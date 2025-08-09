<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use ProtoneMedia\LaravelCrossEloquentSearch\Contracts\SearchResultContract;

class SearchResult implements SearchResultContract
{
    private readonly EloquentCollection $items;
    private readonly ?LengthAwarePaginator $paginator;

    public function __construct(
        EloquentCollection|LengthAwarePaginator $results
    ) {
        if ($results instanceof LengthAwarePaginator) {
            $this->items = new EloquentCollection($results->items());
            $this->paginator = $results;
        } else {
            $this->items = $results;
            $this->paginator = null;
        }
    }

    public function items(): EloquentCollection
    {
        return $this->items;
    }

    public function isPaginated(): bool
    {
        return $this->paginator !== null;
    }

    public function paginator(): ?LengthAwarePaginator
    {
        return $this->paginator;
    }

    public function total(): int
    {
        return $this->isPaginated() 
            ? $this->paginator->total() 
            : $this->items->count();
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function isNotEmpty(): bool
    {
        return $this->items->isNotEmpty();
    }

    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * Get the first item from the results.
     */
    public function first(): mixed
    {
        return $this->items->first();
    }

    /**
     * Get the last item from the results.
     */
    public function last(): mixed
    {
        return $this->items->last();
    }

    /**
     * Apply a callback to each item.
     */
    public function each(callable $callback): self
    {
        $this->items->each($callback);
        
        return $this;
    }

    /**
     * Transform the items using a callback.
     */
    public function map(callable $callback): Collection
    {
        return $this->items->map($callback);
    }

    /**
     * Return the underlying paginator or collection for backward compatibility.
     */
    public function unwrap(): EloquentCollection|LengthAwarePaginator
    {
        return $this->isPaginated() ? $this->paginator : $this->items;
    }
}