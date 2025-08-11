<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Support\Traits\ForwardsCalls;

class SearchFactory
{
    use ForwardsCalls;

    /**
     * Returns a new Searcher instance.
     */
    public function new(): Searcher
    {
        return new Searcher;
    }

    /**
     * Handle dynamic method calls into a new Searcher instance.
     *
     * @param  array<int, mixed>  $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forwardCallTo(
            $this->new(),
            $method,
            $parameters
        );
    }
}
