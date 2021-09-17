<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Searcher
{
    /**
     * Collection of models to search through.
     */
    protected Collection $modelsToSearchThrough;

    /**
     * Sort direction.
     */
    protected string $orderByDirection;

    /**
     * Begin the search term with a wildcard.
     */
    protected bool $beginWithWildcard = false;

    /**
     * End the search term with a wildcard.
     */
    protected bool $endWithWildcard = true;

    /**
     * Where operator.
     */
    protected string $whereOperator = 'like';

    /**
     * Use soundex to match the terms.
     */
    protected bool $soundsLike = false;

    /**
     * Collection of search terms.
     */
    protected Collection $terms;

    /**
     * Collection of search terms.
     */
    protected Collection $termsWithoutWildcards;

    /**
     * The number of items to be shown per page.
     */
    protected int $perPage = 15;

    /**
     * The query string variable used to store the page.
     */
    protected string $pageName = '';

    /**
     * Parse the search term into multiple terms.
     */
    protected bool $parseTerm = true;

    /**
     * Use simplePaginate() on Eloquent\Builder vs paginate()
     */
    protected bool $simplePaginate = false;

    /**
     * Current page.
     *
     * @var int|null
     */
    protected $page;

    /**
     * Initialises the instanace with a fresh Collection and default sort.
     */
    public function __construct()
    {
        $this->modelsToSearchThrough = new Collection;

        $this->orderByAsc();
    }

    /**
     * Sort the results in ascending order.
     *
     * @return self
     */
    public function orderByAsc(): self
    {
        $this->orderByDirection = 'asc';

        return $this;
    }

    /**
     * Sort the results in descending order.
     *
     * @return self
     */
    public function orderByDesc(): self
    {
        $this->orderByDirection = 'desc';

        return $this;
    }

    /**
     * Sort the results in relevance order.
     *
     * @return self
     */
    public function orderByRelevance(): self
    {
        $this->orderByDirection = 'relevance';

        return $this;
    }

    /**
     * Disable the parsing of the search term.
     */
    public function dontParseTerm(): self
    {
        $this->parseTerm = false;

        return $this;
    }

    /**
     * Add a model to search through.
     *
     * @param \Illuminate\Database\Eloquent\Builder|string $query
     * @param string|array|\Illuminate\Support\Collection $columns
     * @param string $orderByColumn
     * @return self
     */
    public function add($query, $columns = null, string $orderByColumn = null): self
    {
        $builder = is_string($query) ? $query::query() : $query;

        $modelToSearchThrough = new ModelToSearchThrough(
            $builder,
            Collection::wrap($columns),
            $orderByColumn ?: $builder->getModel()->getUpdatedAtColumn(),
            $this->modelsToSearchThrough->count()
        );

        $this->modelsToSearchThrough->push($modelToSearchThrough);

        return $this;
    }

    /**
     * Apply the model if the value is truthy.
     *
     * @param mixed $value
     * @param \Illuminate\Database\Eloquent\Builder|string $query
     * @param string|array|\Illuminate\Support\Collection $columns
     * @param string $orderByColumn
     * @return self
     */
    public function addWhen($value, $query, $columns = null, string $orderByColumn = null): self
    {
        if (!$value) {
            return $this;
        }

        return $this->add($query, $columns, $orderByColumn);
    }

    /**
     * Loop through the queries and add them.
     *
     * @param mixed $value
     * @return self
     */
    public function addMany($queries): self
    {
        Collection::make($queries)->each(function ($query) {
            $this->add(...$query);
        });

        return $this;
    }

    /**
     * Set the 'orderBy' column of the latest added model.
     *
     * @param string $orderByColumn
     * @return self
     */
    public function orderBy(string $orderByColumn): self
    {
        $this->modelsToSearchThrough->last()->orderByColumn($orderByColumn);

        return $this;
    }

    /**
     * Let's each search term begin with a wildcard.
     *
     * @param boolean $state
     * @return self
     */
    public function beginWithWildcard(bool $state = true): self
    {
        $this->beginWithWildcard = $state;

        return $this;
    }

    /**
     * Let's each search term end with a wildcard.
     *
     * @param boolean $state
     * @return self
     */
    public function endWithWildcard(bool $state = true): self
    {
        $this->endWithWildcard = $state;

        return $this;
    }

    /**
     * Use 'sounds like' operator instead of 'like'.
     *
     * @return self
     */
    public function soundsLike(bool $state = true): self
    {
        $this->soundsLike = $state;

        $this->whereOperator = $state ? 'sounds like' : 'like';

        return $this;
    }

    /**
     * Sets the pagination properties.
     *
     * @param integer $perPage
     * @param string $pageName
     * @param int|null $page
     * @return self
     */
    public function paginate($perPage = 15, $pageName = 'page', $page = null): self
    {
        $this->page           = $page ?: Paginator::resolveCurrentPage($pageName);
        $this->pageName       = $pageName;
        $this->perPage        = $perPage;
        $this->simplePaginate = false;

        return $this;
    }

    /**
     * Paginate using simple pagination.
     *
     * @param integer $perPage
     * @param string $pageName
     * @param int|null $page
     * @return self
     */
    public function simplePaginate($perPage = 15, $pageName = 'page', $page = null): self
    {
        $this->paginate($perPage, $pageName, $page);

        $this->simplePaginate = true;

        return $this;
    }

    /**
     * Parse the terms and loop through them with the optional callable.
     *
     * @param string $terms
     * @param callable $callback
     * @return \Illuminate\Support\Collection
     */
    public function parseTerms(string $terms, callable $callback = null): Collection
    {
        return Collection::make(str_getcsv($terms, ' ', '"'))
            ->filter()
            ->values()
            ->when($callback, function ($terms, $callback) {
                return $terms->each(fn ($value, $key) => $callback($value, $key));
            });
    }

    /**
     * Creates a collection out of the given search term.
     *
     * @param string $terms
     * @throws \ProtoneMedia\LaravelCrossEloquentSearch\EmptySearchQueryException
     * @return self
     */
    protected function initializeTerms(string $terms): self
    {
        $terms = $this->parseTerm ? $this->parseTerms($terms) : $terms;

        $this->termsWithoutWildcards = Collection::wrap($terms)->filter();

        $this->terms = Collection::make($this->termsWithoutWildcards)->unless($this->soundsLike, function ($terms) {
            return $terms->map(function ($term) {
                return implode([
                    $this->beginWithWildcard ? '%' : '',
                    $term,
                    $this->endWithWildcard ? '%' : '',
                ]);
            });
        });

        return $this;
    }

    /**
     * Adds a where clause to the builder, which encapsulates
     * a series 'orWhere' clauses for each column and for
     * each search term.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \ProtoneMedia\LaravelCrossEloquentSearch\ModelToSearchThrough $modelToSearchThrough
     * @return void
     */
    public function addSearchQueryToBuilder(Builder $builder, ModelToSearchThrough $modelToSearchThrough): void
    {
        $builder->where(function ($query) use ($modelToSearchThrough) {
            $modelToSearchThrough->getQualifiedColumns()->each(
                fn ($field) => $this->terms->each(fn ($term) => $query->orWhere($field, $this->whereOperator, $term))
            );
        });
    }

    /**
     * Adds a word count so we can order by relevance.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \ProtoneMedia\LaravelCrossEloquentSearch\ModelToSearchThrough $modelToSearchThrough
     * @return void
     */
    private function addRelevanceQueryToBuilder($builder, $modelToSearchThrough)
    {
        if ($this->orderByDirection !== 'relevance') {
            return;
        }

        $expressionsAndBindings = $modelToSearchThrough->getQualifiedColumns()->flatMap(function ($field) use ($builder) {
            return $this->termsWithoutWildcards->map(function ($term) use ($field) {
                return [
                    'expression' => "COALESCE(CHAR_LENGTH(LOWER({$field})) - CHAR_LENGTH(REPLACE(LOWER({$field}), ?, ?)), 0)",
                    'bindings'   => [strtolower($term), substr(strtolower($term), 1)],
                ];
            });
        });

        $selects  = $expressionsAndBindings->map->expression->implode(' + ');
        $bindings = $expressionsAndBindings->flatMap->bindings->all();

        $builder->selectRaw("{$selects} as terms_count", $bindings);
    }

    /**
     * Builds an array with all qualified columns for
     * both the ids and ordering.
     *
     * @param \ProtoneMedia\LaravelCrossEloquentSearch\ModelToSearchThrough $currentModel
     * @return array
     */
    protected function makeSelects(ModelToSearchThrough $currentModel): array
    {
        return $this->modelsToSearchThrough->flatMap(function (ModelToSearchThrough $modelToSearchThrough) use ($currentModel) {
            $qualifiedKeyName = $qualifiedOrderByColumnName = 'null';

            if ($modelToSearchThrough === $currentModel) {
                $prefix = $modelToSearchThrough->getModel()->getConnection()->getTablePrefix();

                $qualifiedKeyName = $prefix . $modelToSearchThrough->getQualifiedKeyName();
                $qualifiedOrderByColumnName = $prefix . $modelToSearchThrough->getQualifiedOrderByColumnName();
            }

            return [
                DB::raw("{$qualifiedKeyName} as {$modelToSearchThrough->getModelKey()}"),
                DB::raw("{$qualifiedOrderByColumnName} as {$modelToSearchThrough->getModelKey('order')}"),
            ];
        })->all();
    }

    /**
     * Implodes the qualified order keys with a comma and
     * wraps them in a COALESCE method.
     *
     * @return string
     */
    protected function makeOrderBy(): string
    {
        $modelOrderKeys = $this->modelsToSearchThrough->map->getModelKey('order')->implode(',');

        return "COALESCE({$modelOrderKeys})";
    }

    /**
     * Builds the search queries for each given pending model.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function buildQueries(): Collection
    {
        return $this->modelsToSearchThrough->map(function (ModelToSearchThrough $modelToSearchThrough) {
            return $modelToSearchThrough->getFreshBuilder()
                ->select($this->makeSelects($modelToSearchThrough))
                ->tap(function ($builder) use ($modelToSearchThrough) {
                    $this->addSearchQueryToBuilder($builder, $modelToSearchThrough);
                    $this->addRelevanceQueryToBuilder($builder, $modelToSearchThrough);
                });
        });
    }

    /**
      * Compiles all queries to one big one which binds everything together
      * using UNION statements.
      *
      * @return
      */
    protected function getCompiledQueryBuilder(): QueryBuilder
    {
        $queries = $this->buildQueries();

        // take the first query
        $firstQuery = $queries->shift()->toBase();

        // union the other queries together
        $queries->each(fn (Builder $query) => $firstQuery->union($query));

        if ($this->orderByDirection === 'relevance') {
            return $firstQuery->orderBy('terms_count', 'desc');
        }

        // sort by the given columns and direction
        return $firstQuery->orderBy(DB::raw($this->makeOrderBy()), $this->orderByDirection);
    }

    /**
     * Paginates the compiled query or fetches all results.
     *
     * @return \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function getIdAndOrderAttributes()
    {
        $query = $this->getCompiledQueryBuilder();

        // Determine the pagination method to call on Eloquent\Builder
        $paginateMethod = $this->simplePaginate ? 'simplePaginate' : 'paginate';

        // get all results or limit the results by pagination
        return $this->pageName
            ? $query->{$paginateMethod}($this->perPage, ['*'], $this->pageName, $this->page)
            : $query->get();

        // the collection will be something like:
        //
        // [
        //     [
        //         "0_post_key": null
        //         "0_post_order": null
        //         "1_video_key": 3
        //         "1_video_order": "2020-07-07 19:51:08"
        //     ],
        //     [
        //         "0_post_key": 1
        //         "0_post_order": "2020-07-08 19:51:08"
        //         "1_video_key": null
        //         "1_video_order": null
        //     ]
        // ]
    }

    /**
     * Get the models per type.
     *
     * @param \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator $results
     * @return \Illuminate\Support\Collection
     */
    protected function getModelsPerType($results)
    {
        return $this->modelsToSearchThrough
            ->keyBy->getModelKey()
            ->map(function (ModelToSearchThrough $modelToSearchThrough, $key) use ($results) {
                $ids = $results->pluck($key)->filter();

                return $ids->isNotEmpty()
                    ? $modelToSearchThrough->getFreshBuilder()->whereKey($ids)->get()->keyBy->getKey()
                    : null;
            });

        // the collection will be something like:
        //
        // [
        //     "0_post_key" => [
        //         1 => PostModel
        //     ],
        //     "1_video_key" => [
        //         3 => VideoModel
        //     ],
        // ]
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param string $terms
     * @return integer
     */
    public function count(string $terms = null): int
    {
        $this->initializeTerms($terms ?: '');

        return $this->getCompiledQueryBuilder()->count();
    }

    /**
     * Initialize the search terms, execute the search query and retrieve all
     * models per type. Map the results to a Eloquent collection and set
     * the collection on the paginator (whenever used).
     *
     * @param string $terms
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function get(string $terms = null)
    {
        $this->initializeTerms($terms ?: '');

        $results = $this->getIdAndOrderAttributes();

        $modelsPerType = $this->getModelsPerType($results);

        // loop over the results again and replace the object with the related model
        return $results->map(function ($item) use ($modelsPerType) {
            // from this set, pick '0_post_key'
            //
            // [
            //     "0_post_key": 1
            //     "0_post_order": "2020-07-08 19:51:08"
            //     "1_video_key": null
            //     "1_video_order": null
            // ]

            $modelKey = Collection::make($item)->search(function ($value, $key) {
                return $value && Str::endsWith($key, '_key');
            });

            return $modelsPerType->get($modelKey)->get($item->$modelKey);
        })
            ->pipe(fn (Collection $models) => new EloquentCollection($models))
            ->when($this->pageName, fn (EloquentCollection $models) => $results->setCollection($models));
    }
}
