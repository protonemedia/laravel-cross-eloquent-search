<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Support\Traits\ForwardsCalls;

class SearchFactory
{
    use ForwardsCalls;

    /**
     * Returns a new Searcher instance.
     *
     * @return \ProtoneMedia\LaravelCrossEloquentSearch\Searcher
     */
    public function new(): Searcher
    {
        return new Searcher;
    }

    /**
    * Handle dynamic method calls into a new Searcher instance.
    *
    * @param  string  $method
    * @param  array  $parameters
    * @return mixed
    */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo(
            $this->new(),
            $method,
            $parameters
        );
    }
}
