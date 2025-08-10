<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Paginator;
use ProtoneMedia\LaravelCrossEloquentSearch\DatabaseGrammarFactory;
use ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SearchGrammarInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use ProtoneMedia\LaravelCrossEloquentSearch\Exceptions\OrderByRelevanceException;

class Searcher
{
    use Conditionable;

    /**
     * Collection of models to search through.
     */
    protected Collection $models;

    /**
     * Sort direction.
     */
    protected string $orderByDirection;

    /**
     * Sort by model.
     */
    protected ?array $orderByModel = null;

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
     * Database-specific grammar instance.
     */
    protected ?SearchGrammarInterface $searchGrammar = null;

    /**
     * Ignore case.
     */
    protected bool $ignoreCase = false;

    /**
     * Raw input.
     */
    protected ?string $rawTerms = null;

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
     * Include the model type in the search results.
     */
    protected ?string $includeModelTypeWithKey = null;

    /**
     * Initialize with a fresh Collection and default sort.
     */
    public function __construct()
    {
        $this->models = new Collection;

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
     * Sort the results in order of the given models.
     *
     * @return self
     */
    public function orderByModel($modelClasses): self
    {
        $this->orderByModel = Arr::wrap($modelClasses);

        return $this;
    }

    /**
     * Configure term parsing.
     */
    public function parseTerm(bool $state = true): self
    {
        $this->parseTerm = $state;

        return $this;
    }


    /**
     * Enable the inclusion of the model type in the search results.
     *
     * @param string $key
     * @return self
     */
    public function includeModelType(string $key = 'type'): self
    {
        $this->includeModelTypeWithKey = $key;

        return $this;
    }

    /**
     * Add a model to search through.
     *
     * @param \Illuminate\Database\Eloquent\Builder|string $query
     * @param string|array|\Illuminate\Support\Collection $columns
     * @param string $orderByColumn
     * @param bool $fullText
     * @return self
     */
    public function add($query, $columns = null, string $orderByColumn = null): self
    {
        /** @var Builder $builder */
        $builder = is_string($query) ? $query::query() : $query;

        if (is_null($orderByColumn)) {
            $model = $builder->getModel();

            $orderByColumn = $model->usesTimestamps()
                ? $model->getUpdatedAtColumn()
                : $model->getKeyName();
        }

        $modelToSearchThrough = new ModelToSearchThrough(
            $builder,
            Collection::wrap($columns),
            $orderByColumn,
            $this->models->count(),
        );

        $this->models->push($modelToSearchThrough);

        $this->getSearchGrammar($builder->getConnection());

        return $this;
    }

    public function addFullText($query, $columns = null, array $options = [], string $orderByColumn = null): self
    {
        $builder = is_string($query) ? $query::query() : $query;

        $modelToSearchThrough = new ModelToSearchThrough(
            $builder,
            Collection::wrap($columns),
            $orderByColumn ?: $builder->getModel()->getUpdatedAtColumn(),
            $this->models->count(),
            true,
            $options
        );

        $this->models->push($modelToSearchThrough);

        return $this;
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
        $this->models->last()->orderByColumn($orderByColumn);

        return $this;
    }

    /**
     * Ignore case of terms.
     *
     * @param boolean $state
     * @return self
     */
    public function ignoreCase(bool $state = true): self
    {
        $this->ignoreCase = $state;

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

        // Update operator if grammar is already initialized
        if ($this->searchGrammar) {
            $this->updateWhereOperator();
        }

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
    public static function parseTerms(string $terms, callable $callback = null): Collection
    {
        $callback = $callback ?: fn () => null;

        return Collection::make(str_getcsv($terms, ' ', '"'))
            ->filter()
            ->values()
            ->when($callback !== null, function ($terms) use ($callback) {
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
        $this->rawTerms = $terms;

        $terms = $this->parseTerm ? static::parseTerms($terms) : $terms;

        $this->termsWithoutWildcards = Collection::wrap($terms)->filter()->map(function ($term) {
            return $this->ignoreCase ? Str::lower($term) : $term;
        });

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
        if ($this->termsWithoutWildcards->isEmpty()) {
            return;
        }

        $builder->where(function (Builder $query) use ($modelToSearchThrough) {
            if (!$modelToSearchThrough->isFullTextSearch()) {
                return $modelToSearchThrough->getColumns()->each(function ($column) use ($query, $modelToSearchThrough) {
                    Str::contains($column, '.')
                        ? $this->addNestedRelationToQuery($query, $column)
                        : $this->addWhereTermsToQuery($query, $modelToSearchThrough->qualifyColumn($column));
                });
            }

            $modelToSearchThrough
                ->toGroupedCollection()
                ->each(function (ModelToSearchThrough $modelToSearchThrough) use ($query) {
                    if ($relation = $modelToSearchThrough->getFullTextRelation()) {
                        $query->orWhereHas($relation, function ($relationQuery) use ($modelToSearchThrough) {
                            $relationQuery->where(function ($query) use ($modelToSearchThrough) {
                                $query->orWhereFullText(
                                    $modelToSearchThrough->getColumns()->all(),
                                    $this->rawTerms,
                                    $modelToSearchThrough->getFullTextOptions()
                                );
                            });
                        });
                    } else {
                        $query->orWhereFullText(
                            $modelToSearchThrough->getColumns()->map(fn ($column) => $modelToSearchThrough->qualifyColumn($column))->all(),
                            $this->rawTerms,
                            $modelToSearchThrough->getFullTextOptions()
                        );
                    }
                });
        });
    }

    /**
     * Adds an 'orWhereHas' clause to the query to search through the given nested relation.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $column
     * @return void
     */
    private function addNestedRelationToQuery(Builder $query, string $nestedRelationAndColumn)
    {
        $segments = explode('.', $nestedRelationAndColumn);

        $column = array_pop($segments);

        $relation = implode('.', $segments);

        $query->orWhereHas($relation, function ($relationQuery) use ($column) {
            $relationQuery->where(
                fn ($query) => $this->addWhereTermsToQuery($query, $query->qualifyColumn($column))
            );
        });
    }

    /**
     * Adds an 'orWhere' clause to search for each term in the given column.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param array|string $columns
     * @return void
     */
    private function addWhereTermsToQuery(Builder $query, $column)
    {
        $grammar = $this->getSearchGrammar($query->getConnection());

        $column = $this->ignoreCase ? $grammar->wrap($column) : $column;

        $this->terms->each(function ($term) use ($query, $column, $grammar) {
            $this->ignoreCase
                ? $query->orWhereRaw($grammar->caseInsensitive($column) . " {$this->whereOperator} ?", [$term])
                : $query->orWhere($column, $this->whereOperator, $term);
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
        if (!$this->isOrderingByRelevance() || $this->termsWithoutWildcards->isEmpty()) {
            return;
        }

        if (Str::contains($modelToSearchThrough->getColumns()->implode(''), '.')) {
            throw OrderByRelevanceException::relationColumnsNotSupported();
        }

        $expressionsAndBindings = $modelToSearchThrough->getQualifiedColumns()->flatMap(function ($field) use ($modelToSearchThrough) {
            $connection = $modelToSearchThrough->getModel()->getConnection();
            $grammar = $this->getSearchGrammar($connection);
            $prefix = $connection->getTablePrefix();
            $field = $grammar->wrap($prefix . $field);

            return $this->termsWithoutWildcards->map(function ($term) use ($field, $grammar) {
                $lowerField = $grammar->lower($field);
                $charLength = $grammar->charLength($lowerField);
                $replace = $grammar->replace($lowerField, '?', '?');
                $replacedCharLength = $grammar->charLength($replace);

                return [
                    'expression' => $grammar->coalesce(["{$charLength} - {$replacedCharLength}", '0']),
                    'bindings'   => [Str::lower($term), Str::substr(Str::lower($term), 1)],
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
        $grammar = $this->getSearchGrammar();
        
        $selects = $this->models->flatMap(function (ModelToSearchThrough $modelToSearchThrough) use ($currentModel, $grammar) {
            $qualifiedKeyName = $qualifiedOrderByColumnName = $modelOrderKey = 'null';

            if ($modelToSearchThrough === $currentModel) {
                $prefix = $modelToSearchThrough->getModel()->getConnection()->getTablePrefix();

                $qualifiedKeyName = $prefix . $modelToSearchThrough->getQualifiedKeyName();
                $qualifiedOrderByColumnName = $prefix . $modelToSearchThrough->getQualifiedOrderByColumnName();

                if ($this->orderByModel) {
                    $modelOrderKey = array_search(
                        get_class($modelToSearchThrough->getModel()),
                        $this->orderByModel ?: []
                    );

                    if ($modelOrderKey === false) {
                        $modelOrderKey = count($this->orderByModel);
                    }
                }
            }

            return array_filter([
                DB::raw("{$qualifiedKeyName} as {$grammar->wrap($modelToSearchThrough->getModelKey())}"),
                DB::raw("{$qualifiedOrderByColumnName} as {$grammar->wrap($modelToSearchThrough->getModelKey('order'))}"),
                $this->orderByModel ? DB::raw("{$modelOrderKey} as {$grammar->wrap($modelToSearchThrough->getModelKey('model_order'))}") : null,
            ]);
        })->all();

        return $selects;
    }

    /**
     * Implodes the qualified order keys with a comma and
     * wraps them in a COALESCE method.
     *
     * @return string
     */
    protected function makeOrderBy(): string
    {
        $grammar = $this->getSearchGrammar();
        $modelOrderKeys = $this->models->map(function($modelToSearchThrough) use ($grammar) {
            return $grammar->wrap($modelToSearchThrough->getModelKey('order'));
        })->toArray();

        return $grammar->coalesce($modelOrderKeys);
    }

    /**
     * Implodes the qualified orderByModel keys with a comma and
     * wraps them in a COALESCE method.
     *
     * @return string
     */
    protected function makeOrderByModel(): string
    {
        $grammar = $this->getSearchGrammar();
        $modelOrderKeys = $this->models->map(function($modelToSearchThrough) use ($grammar) {
            return $grammar->wrap($modelToSearchThrough->getModelKey('model_order'));
        })->toArray();

        return $grammar->coalesce($modelOrderKeys);
    }

    /**
     * Builds the search queries for each given pending model.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function buildQueries(): Collection
    {
        return $this->models->map(function (ModelToSearchThrough $modelToSearchThrough) {
            return $modelToSearchThrough->getFreshBuilder()
                ->select($this->makeSelects($modelToSearchThrough))
                ->tap(function ($builder) use ($modelToSearchThrough) {
                    $this->addSearchQueryToBuilder($builder, $modelToSearchThrough);
                    $this->addRelevanceQueryToBuilder($builder, $modelToSearchThrough);
                });
        });
    }

    /**
     * Returns a boolean wether the ordering is set to 'relevance'.
     *
     * @return boolean
     */
    private function isOrderingByRelevance(): bool
    {
        return $this->orderByDirection === 'relevance';
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

        /** @var BaseBuilder $firstQuery */
        $firstQuery = $queries->shift()->toBase();

        // Union the other queries together
        $queries->each(fn (Builder $query) => $firstQuery->union($query));

        // SQLite needs wrapped unions for proper ordering
        if ($this->models->count() > 1 && !$this->getSearchGrammar()->supportsUnionOrdering()) {
            return $this->wrapUnionForOrdering($firstQuery);
        }

        // Apply ordering directly to the union query
        return $this->applyOrdering($firstQuery);
    }

    /**
     * Wrap a UNION query for databases that don't support direct UNION ordering.
     */
    protected function wrapUnionForOrdering($firstQuery): QueryBuilder
    {
        $grammar = $this->getSearchGrammar();
        $wrappedQuery = $grammar->wrapUnionQuery($firstQuery->toSql(), $firstQuery->getBindings());

        $query = DB::table(DB::raw($wrappedQuery['sql']))
            ->setBindings($wrappedQuery['bindings'])
            ->select('*');

        return $this->applyOrdering($query);
    }

    /**
     * Apply ordering to the query based on configuration.
     */
    protected function applyOrdering($query): QueryBuilder
    {
        $grammar = $this->getSearchGrammar();

        // Model type ordering takes precedence
        if ($this->orderByModel) {
            $modelOrderKeys = $this->models->map(fn($model) => 
                $grammar->wrap($model->getModelKey('model_order'))
            )->toArray();
            
            $direction = $this->isOrderingByRelevance() ? 'asc' : $this->orderByDirection;
            $query->orderByRaw($grammar->coalesce($modelOrderKeys) . ' ' . $direction);
        }

        // Then relevance ordering or standard column ordering
        if ($this->isOrderingByRelevance() && $this->termsWithoutWildcards->isNotEmpty()) {
            $query->orderBy('terms_count', 'desc');
        } else {
            // Always add the standard column ordering (even with model ordering as secondary sort)
            $orderKeys = $this->models->map(fn($model) => 
                $grammar->wrap($model->getModelKey('order'))
            )->toArray();
            
            $direction = $this->isOrderingByRelevance() ? 'asc' : $this->orderByDirection;
            $query->orderByRaw($grammar->coalesce($orderKeys) . ' ' . $direction);
        }

        return $query;
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
        return $this->models
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
    public function search(string $terms = null)
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

            /** @var Model $model */
            $model = $modelsPerType->get($modelKey)->get($item->$modelKey);

            if ($this->includeModelTypeWithKey) {
                $searchType = method_exists($model, 'searchType') ? $model->searchType() : class_basename($model);

                $model->setAttribute($this->includeModelTypeWithKey, $searchType);
            }

            return $model;
        })
            ->pipe(fn (Collection $models) => new EloquentCollection($models))
            ->when($this->pageName, fn (EloquentCollection $models) => $results->setCollection($models));
    }

    /**
     * Gets the search grammar, initializing it lazily on first access.
     *
     * @param \Illuminate\Database\Connection|null $connection Optional database connection
     * @return \ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SearchGrammarInterface
     */
    protected function getSearchGrammar($connection = null): SearchGrammarInterface
    {
        if ($this->searchGrammar) {
            return $this->searchGrammar;
        }

        // Use provided connection or get from first model
        $connection = $connection ?: $this->getFirstModelConnection();

        $this->searchGrammar = DatabaseGrammarFactory::make($connection);
        $this->updateWhereOperator();

        return $this->searchGrammar;
    }

    /**
     * Gets the database connection from the first available model.
     *
     * @return \Illuminate\Database\Connection
     * @throws \RuntimeException When no models have been added
     */
    protected function getFirstModelConnection()
    {
        $firstModel = $this->models->first();

        if (!$firstModel) {
            throw new \RuntimeException('No models have been added to search through.');
        }

        return $firstModel->getModel()->getConnection();
    }

    /**
     * Updates the where operator based on the current grammar capabilities.
     */
    protected function updateWhereOperator(): void
    {
        if ($this->soundsLike && $this->searchGrammar->supportsSoundsLike()) {
            $this->whereOperator = $this->searchGrammar->soundsLikeOperator();
        } else {
            $this->whereOperator = 'like';
            $this->soundsLike = false;
        }
    }
}
