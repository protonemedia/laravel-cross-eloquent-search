<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use ProtoneMedia\LaravelCrossEloquentSearch\Exceptions\OrderByRelevanceException;
use ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SearchGrammarInterface;

class Searcher
{
    use Conditionable;

    /**
     * Collection of models to search through.
     *
     * @var Collection<int, ModelToSearchThrough>
     */
    protected Collection $models;

    /**
     * Sort direction.
     */
    protected string $orderByDirection;

    /**
     * Sort by model.
     *
     * @var array<int, string>|null
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
     *
     * @var Collection<int, string>
     */
    protected Collection $terms;

    /**
     * Collection of search terms without wildcards.
     *
     * @var Collection<int, string>
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
     * Limit the number of results.
     */
    protected ?int $limit = null;

    /**
     * Offset for the results.
     */
    protected ?int $offset = null;

    /**
     * Whether to preserve query string parameters in pagination.
     */
    protected bool $preserveQueryString = false;

    /**
     * The key to use for making results distinct/unique.
     */
    protected ?string $distinctKey = null;

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
     */
    public function orderByAsc(): self
    {
        $this->orderByDirection = 'asc';

        return $this;
    }

    /**
     * Sort the results in descending order.
     */
    public function orderByDesc(): self
    {
        $this->orderByDirection = 'desc';

        return $this;
    }

    /**
     * Sort the results in relevance order.
     */
    public function orderByRelevance(): self
    {
        $this->orderByDirection = 'relevance';

        return $this;
    }

    /**
     * Sort the results in order of the given models.
     *
     * @param  string|array<int, string>  $modelClasses
     */
    public function orderByModel($modelClasses): self
    {
        /** @var array<int, string> $wrappedClasses */
        $wrappedClasses = Arr::wrap($modelClasses);
        $this->orderByModel = $wrappedClasses;

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
     */
    public function includeModelType(string $key = 'type'): self
    {
        $this->includeModelTypeWithKey = $key;

        return $this;
    }

    /**
     * Add a model to search through.
     *
     * @param  string|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  mixed  $columns
     */
    public function add($query, $columns = null, ?string $orderByColumn = null): self
    {
        /** @var Builder<Model> $builder */
        $builder = is_string($query) ? $query::query() : $query;

        if (is_null($orderByColumn)) {
            $model = $builder->getModel();

            $orderByColumn = $model->usesTimestamps()
                ? $model->getUpdatedAtColumn()
                : $model->getKeyName();
        }

        /** @var Collection<int, string|Expression> $wrappedColumns */
        $wrappedColumns = Collection::wrap($columns);
        $modelToSearchThrough = new ModelToSearchThrough(
            $builder,
            $wrappedColumns,
            $orderByColumn ?? '',
            $this->models->count(),
        );

        $this->models->push($modelToSearchThrough);

        $connection = $builder->getConnection();
        if ($connection instanceof \Illuminate\Database\Connection) {
            $this->getSearchGrammar($connection);
        }

        return $this;
    }

    /**
     * Add a full text model to search through.
     *
     * @param  string|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  mixed  $columns
     * @param  array<string, mixed>  $options
     */
    public function addFullText($query, $columns = null, array $options = [], ?string $orderByColumn = null): self
    {
        /** @var Builder<Model> $builder */
        $builder = is_string($query) ? $query::query() : $query;

        $defaultOrderByColumn = $orderByColumn ?: $builder->getModel()->getUpdatedAtColumn();

        /** @var Collection<int, string|Expression> $wrappedColumns */
        $wrappedColumns = Collection::wrap($columns);
        $modelToSearchThrough = new ModelToSearchThrough(
            $builder,
            $wrappedColumns,
            $defaultOrderByColumn ?? '',
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
     * @param  iterable<int, array{0: string|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>, 1?: mixed, 2?: string|null}>  $queries
     */
    public function addMany($queries): self
    {
        Collection::make($queries)->each(function (array $query): void {
            // Ensure proper types for the add method parameters
            $builder = $query[0];
            $columns = $query[1] ?? null;
            $orderByColumn = array_key_exists(2, $query) ? (is_string($query[2]) ? $query[2] : null) : null;

            $this->add($builder, $columns, $orderByColumn);
        });

        return $this;
    }

    /**
     * Set the 'orderBy' column of the latest added model.
     */
    public function orderBy(string $orderByColumn): self
    {
        /** @var ModelToSearchThrough|null $lastModel */
        $lastModel = $this->models->last();
        if ($lastModel) {
            $lastModel->orderByColumn($orderByColumn);
        }

        return $this;
    }

    /**
     * Ignore case of terms.
     */
    public function ignoreCase(bool $state = true): self
    {
        $this->ignoreCase = $state;

        return $this;
    }

    /**
     * Let's each search term begin with a wildcard.
     */
    public function beginWithWildcard(bool $state = true): self
    {
        $this->beginWithWildcard = $state;

        return $this;
    }

    /**
     * Let's each search term end with a wildcard.
     */
    public function endWithWildcard(bool $state = true): self
    {
        $this->endWithWildcard = $state;

        return $this;
    }

    /**
     * Use 'sounds like' operator instead of 'like'.
     */
    public function soundsLike(bool $state = true): self
    {
        $this->soundsLike = $state;

        // Update operator if grammar is already initialized
        if ($this->searchGrammar instanceof \ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SearchGrammarInterface) {
            $this->updateWhereOperator();
        }

        return $this;
    }

    /**
     * Sets the pagination properties.
     *
     * @param  string  $pageName
     * @param  int|null  $page
     */
    public function paginate(int $perPage = 15, $pageName = 'page', $page = null): self
    {
        $this->page = $page ?: Paginator::resolveCurrentPage($pageName);
        $this->pageName = $pageName;
        $this->perPage = $perPage;
        $this->simplePaginate = false;

        return $this;
    }

    /**
     * Paginate using simple pagination.
     *
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int|null  $page
     */
    public function simplePaginate($perPage = 15, $pageName = 'page', $page = null): self
    {
        $this->paginate($perPage, $pageName, $page);

        $this->simplePaginate = true;

        return $this;
    }

    /**
     * Force unique records in search results.
     * 
     * @param  string|callable|null  $key  The key to use for uniqueness, or null for default uniqueness
     */
    public function distinct(?string $key = null): self
    {
        $this->distinctKey = $key;

        return $this;
    }

    /**
     * Configure pagination to preserve all current query string parameters.
     */
    public function withQueryString(): self
    {
        // Store a flag to indicate we want to preserve query string
        $this->preserveQueryString = true;

        return $this;
    }

    /**
     * Limit the number of results.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Set the offset for the results.
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Parse the terms and loop through them with the optional callable.
     *
     * @return Collection<int, string>
     */
    public static function parseTerms(string $terms, ?callable $callback = null): Collection
    {
        /** @var Collection<int, string> $terms */
        $terms = Collection::make(str_getcsv($terms, ' ', '"'))
            ->filter()
            ->values();

        if ($callback) {
            $terms->each($callback);
        }

        return $terms;
    }

    /**
     * Creates a collection out of the given search term.
     *
     * @throws \Exception
     */
    protected function initializeTerms(string $terms): self
    {
        $this->rawTerms = $terms;

        $terms = $this->parseTerm ? static::parseTerms($terms) : $terms;

        $this->termsWithoutWildcards = Collection::wrap($terms)
            ->filter()
            ->map(fn ($term) => $this->ignoreCase ? Str::lower($term) : $term);

        $this->terms = $this->termsWithoutWildcards
            ->unless($this->soundsLike, fn ($terms) => $terms->map(fn ($term): string => ($this->beginWithWildcard ? '%' : '').$term.($this->endWithWildcard ? '%' : '')
            ));

        return $this;
    }

    /**
     * Adds a where clause to the builder, which encapsulates
     * a series 'orWhere' clauses for each column and for
     * each search term.
     *
     * @param  Builder<Model>  $builder
     */
    public function applySearchConstraints(Builder $builder, ModelToSearchThrough $modelToSearchThrough): void
    {
        if ($this->termsWithoutWildcards->isEmpty()) {
            return;
        }

        $builder->where(function (Builder $builder) use ($modelToSearchThrough): void {
            if (! $modelToSearchThrough->isFullTextSearch()) {
                $modelToSearchThrough->getColumns()->each(function ($column) use ($builder, $modelToSearchThrough): void {
                    if ($column instanceof Expression) {
                        $this->addWhereTermsToQuery($builder, $column);
                    } elseif (is_string($column) && Str::contains($column, '.')) {
                        $this->addNestedRelationToQuery($builder, $column);
                    } elseif (is_string($column)) {
                        $this->addWhereTermsToQuery($builder, $modelToSearchThrough->qualifyColumn($column));
                    }
                    // Skip arrays - they're handled by the relationship parsing logic elsewhere
                });

                return;
            }

            $modelToSearchThrough
                ->toGroupedCollection()
                ->each(function (ModelToSearchThrough $modelToSearchThrough) use ($builder): void {
                    if ($relation = $modelToSearchThrough->getFullTextRelation()) {
                        $builder->orWhereHas($relation, function ($relationQuery) use ($modelToSearchThrough): void {
                            $relationQuery->where(function ($query) use ($modelToSearchThrough): void {
                                $query->orWhereFullText(
                                    $modelToSearchThrough->getColumns()->all(),
                                    $this->rawTerms ?? '',
                                    $modelToSearchThrough->getFullTextOptions()
                                );
                            });
                        });
                    } else {
                        $builder->orWhereFullText(
                            $modelToSearchThrough->getColumns()->map(fn (string $column): string => $modelToSearchThrough->qualifyColumn($column))->all(),
                            $this->rawTerms ?? '',
                            $modelToSearchThrough->getFullTextOptions()
                        );
                    }
                });
        });
    }

    /**
     * Adds an 'orWhereHas' clause to the query to search through the given nested relation.
     *
     * @param Builder<Model> $builder
     */
    private function addNestedRelationToQuery(Builder $builder, string $nestedRelationAndColumn): void
    {
        $segments = explode('.', $nestedRelationAndColumn);

        $column = array_pop($segments);

        $relation = implode('.', $segments);

        $builder->orWhereHas($relation, function ($relationQuery) use ($column): void {
            $relationQuery->where(
                fn ($query) => $this->addWhereTermsToQuery($query, $query->qualifyColumn($column))
            );
        });
    }

    /**
     * Adds an 'orWhere' clause to search for each term in the given column.
     *
     * @param Builder<Model> $builder
     */
    private function addWhereTermsToQuery(Builder $builder, string|Expression $column): void
    {
        $connection = $builder->getConnection();
        $searchGrammar = $connection instanceof \Illuminate\Database\Connection
            ? $this->getSearchGrammar($connection)
            : $this->getSearchGrammar();

        // Handle both string columns and Expression objects
        if ($column instanceof Expression) {
            // For raw expressions, we need to use whereRaw
            // Get the Laravel query grammar for Expression::getValue()
            $laravelGrammar = $builder->toBase()->getGrammar();
            $rawExpression = $column->getValue($laravelGrammar);
            $this->terms->each(function (string $term) use ($builder, $rawExpression): void {
                if ($this->ignoreCase) {
                    $builder->orWhereRaw("LOWER({$rawExpression}) {$this->whereOperator} ?", [strtolower($term)]);
                } else {
                    $builder->orWhereRaw("{$rawExpression} {$this->whereOperator} ?", [$term]);
                }
            });
        } else {
            // Handle regular string columns
            $column = $this->ignoreCase ? $searchGrammar->wrap($column) : $column;

            $this->terms->each(function (string $term) use ($builder, $column, $searchGrammar): void {
                $this->ignoreCase
                    ? $builder->orWhereRaw($searchGrammar->caseInsensitive($column)." {$this->whereOperator} ?", [$term])
                    : $builder->orWhere($column, $this->whereOperator, $term);
            });
        }
    }

    /**
     * Adds a word count so we can order by relevance.
     *
     * @param  Builder<Model>  $builder
     */
    private function applyRelevanceSelect(Builder $builder, ModelToSearchThrough $modelToSearchThrough): void
    {
        if (! $this->isOrderingByRelevance() || $this->termsWithoutWildcards->isEmpty()) {
            return;
        }

        // Check if any columns contain relations (not applicable to raw expressions)
        $hasRelationColumns = $modelToSearchThrough->getColumns()->contains(fn($column): bool => is_string($column) && Str::contains($column, '.'));
        
        if ($hasRelationColumns) {
            throw OrderByRelevanceException::relationColumnsNotSupported();
        }

        // Skip relevance for raw expressions as they can't be properly counted
        if ($modelToSearchThrough->getColumns()->contains(fn ($column): bool => $column instanceof Expression)) {
            return;
        }

        $expressionsAndBindings = $modelToSearchThrough->getQualifiedColumns()->flatMap(function ($field) use ($modelToSearchThrough) {
            // At this point we know $field is a string since we filtered out expressions above
            $connection = $modelToSearchThrough->getModel()->getConnection();
            $searchGrammar = $this->getSearchGrammar();
            $prefix = $connection->getTablePrefix();
            $field = $searchGrammar->wrap($prefix.$field);

            return $this->termsWithoutWildcards->map(function ($term) use ($field, $searchGrammar): array {
                $lowerField = $searchGrammar->lower($field);
                $charLength = $searchGrammar->charLength($lowerField);
                $replace = $searchGrammar->replace($lowerField, '?', '?');
                $replacedCharLength = $searchGrammar->charLength($replace);

                return [
                    'expression' => $searchGrammar->coalesce(["{$charLength} - {$replacedCharLength}", '0']),
                    'bindings' => [Str::lower($term), Str::substr(Str::lower($term), 1)],
                ];
            });
        });

        $selects = $expressionsAndBindings->map(fn (array $item): string => $item['expression'])->implode(' + ');
        $bindings = $expressionsAndBindings->flatMap(fn (array $item): array => $item['bindings'])->all();

        $builder->selectRaw("{$selects} as terms_count", $bindings);
    }

    /**
     * Builds an array with all qualified columns for
     * both the ids and ordering.
     *
     * @return array<int, \Illuminate\Contracts\Database\Query\Expression>
     */
    protected function makeSelects(ModelToSearchThrough $currentModel): array
    {
        $searchGrammar = $this->getSearchGrammar();
        $isSingleModel = $this->models->count() === 1;

        // For single model queries, select actual columns without complex aliases
        if ($isSingleModel) {
            $keyName = $currentModel->getModel()->getKeyName();
            $orderByColumnName = $currentModel->getQualifiedOrderByColumnName();
            
            return [
                DB::raw($keyName . " as " . $searchGrammar->wrap($currentModel->getModelKey())),
                DB::raw($orderByColumnName . " as " . $searchGrammar->wrap($currentModel->getModelKey('order'))),
            ];
        }

        return $this->models->flatMap(function (ModelToSearchThrough $modelToSearchThrough) use ($currentModel, $searchGrammar): array {
            $isPostgreSQL = $this->getSearchGrammar() instanceof \ProtoneMedia\LaravelCrossEloquentSearch\Grammars\PostgreSqlSearchGrammar;
            
            // For PostgreSQL, we need to cast NULL values to appropriate types
            $nullKey = $isPostgreSQL ? 'NULL::bigint' : 'null';
            $nullOrder = $isPostgreSQL ? 'NULL::timestamp' : 'null';
            $nullModelOrder = $isPostgreSQL ? 'NULL::int' : 'null';
            
            $qualifiedKeyName = $qualifiedOrderByColumnName = $modelOrderKey = $nullKey;

            if ($modelToSearchThrough === $currentModel) {
                $prefix = $modelToSearchThrough->getModel()->getConnection()->getTablePrefix();

                $qualifiedKeyName = $prefix.$modelToSearchThrough->getQualifiedKeyName();
                $qualifiedOrderByColumnName = $prefix.$modelToSearchThrough->getQualifiedOrderByColumnName();

                if ($this->orderByModel) {
                    $modelOrderKey = array_search(
                        $modelToSearchThrough->getModel()::class,
                        $this->orderByModel
                    );

                    if ($modelOrderKey === false) {
                        $modelOrderKey = count($this->orderByModel);
                    }
                }
            } else {
                // Use typed nulls for non-current models
                $qualifiedOrderByColumnName = $nullOrder;
                $modelOrderKey = $nullModelOrder;
            }

            return array_filter([
                DB::raw("{$qualifiedKeyName} as {$searchGrammar->wrap($modelToSearchThrough->getModelKey())}"),
                DB::raw("{$qualifiedOrderByColumnName} as {$searchGrammar->wrap($modelToSearchThrough->getModelKey('order'))}"),
                $this->orderByModel ? DB::raw("{$modelOrderKey} as {$searchGrammar->wrap($modelToSearchThrough->getModelKey('model_order'))}") : null,
            ]);
        })->all();
    }

    /**
     * Implodes the qualified order keys with a comma and
     * wraps them in a COALESCE method.
     */
    protected function makeOrderBy(): string
    {
        $searchGrammar = $this->getSearchGrammar();
        $isSingleModel = $this->models->count() === 1;
        
        // For single model queries, use direct column reference
        if ($isSingleModel) {
            return $searchGrammar->wrap($this->models->first()->getModelKey('order'));
        }
        
        $modelOrderKeys = $this->models->map(fn(ModelToSearchThrough $modelToSearchThrough): string => $searchGrammar->wrap($modelToSearchThrough->getModelKey('order')))->toArray();

        /** @var array<int, string> $stringKeys */
        $stringKeys = array_values($modelOrderKeys);

        return $searchGrammar->coalesce($stringKeys);
    }

    /**
     * Implodes the qualified orderByModel keys with a comma and
     * wraps them in a COALESCE method.
     */
    protected function makeOrderByModel(): string
    {
        $searchGrammar = $this->getSearchGrammar();
        $modelOrderKeys = $this->models->map(fn(ModelToSearchThrough $modelToSearchThrough): string => $searchGrammar->wrap($modelToSearchThrough->getModelKey('model_order')))->toArray();

        /** @var array<int, string> $stringKeys */
        $stringKeys = array_values($modelOrderKeys);

        return $searchGrammar->coalesce($stringKeys);
    }

    /**
     * Builds the search queries for each given pending model.
     *
     * @return Collection<int, Builder<Model>>
     */
    protected function buildQueries(): Collection
    {
        return $this->models->map(fn(ModelToSearchThrough $modelToSearchThrough) => $modelToSearchThrough->getFreshBuilder()
            ->select($this->makeSelects($modelToSearchThrough))
            ->tap(function ($builder) use ($modelToSearchThrough): void {
                $this->applySearchConstraints($builder, $modelToSearchThrough);
                $this->applyRelevanceSelect($builder, $modelToSearchThrough);
            }));
    }

    /**
     * Returns a boolean wether the ordering is set to 'relevance'.
     */
    private function isOrderingByRelevance(): bool
    {
        return $this->orderByDirection === 'relevance';
    }

    /**
     * Compiles all queries to one big one which binds everything together
     * using UNION statements.
     */
    protected function getCompiledQueryBuilder(): QueryBuilder
    {
        $queries = $this->buildQueries();

        /** @var Builder<Model> $firstBuilder */
        $firstBuilder = $queries->shift();
        
        // Handle single model queries specially for PostgreSQL
        if ($this->models->count() === 1) {
            return $this->applySingleModelOrdering($firstBuilder);
        }
        
        /** @var BaseBuilder $builder */
        $builder = $firstBuilder->toBase();

        // Union the other queries together
        $queries->each(fn (Builder $query) => $builder->union($query));

        // SQLite and PostgreSQL need wrapped unions for proper ordering
        if (! $this->getSearchGrammar()->supportsUnionOrdering()) {
            return $this->wrapUnionForOrdering($builder);
        }

        // Apply ordering directly to the union query
        return $this->applyOrdering($builder);
    }

    /**
     * Apply ordering to single model queries using direct column references.
     */
    protected function applySingleModelOrdering(Builder $builder): QueryBuilder
    {
        $queryBuilder = $builder->toBase();
        $model = $this->models->first();
        $orderByColumn = $model->getQualifiedOrderByColumnName();
        
        // For single model queries, use the original column name directly
        $direction = $this->isOrderingByRelevance() ? 'asc' : $this->orderByDirection;
        $queryBuilder->orderBy($orderByColumn, $direction);
        
        return $queryBuilder;
    }

    /**
     * Wrap a UNION query for databases that don't support direct UNION ordering.
     */
    protected function wrapUnionForOrdering(BaseBuilder $baseBuilder): QueryBuilder
    {
        $searchGrammar = $this->getSearchGrammar();
        $wrappedQuery = $searchGrammar->wrapUnionQuery($baseBuilder->toSql(), $baseBuilder->getBindings());

        /** @var array<int, mixed> $bindings */
        $bindings = $wrappedQuery['bindings'] ?? [];
        $builder = DB::table(DB::raw($wrappedQuery['sql']))
            ->setBindings(array_values($bindings))
            ->select('*');

        return $this->applyOrdering($builder);
    }

    /**
     * Apply ordering to the query based on configuration.
     */
    protected function applyOrdering(QueryBuilder $queryBuilder): QueryBuilder
    {
        $searchGrammar = $this->getSearchGrammar();

        // Model type ordering takes precedence
        if ($this->orderByModel) {
            $modelOrderKeys = $this->models->map(fn (ModelToSearchThrough $modelToSearchThrough): string => $searchGrammar->wrap($modelToSearchThrough->getModelKey('model_order'))
            )->toArray();

            $direction = $this->isOrderingByRelevance() ? 'asc' : $this->orderByDirection;
            /** @var array<int, string> $stringKeys */
            $stringKeys = array_values($modelOrderKeys);
            $queryBuilder->orderByRaw($searchGrammar->coalesce($stringKeys).' '.$direction);
        }

        // Then relevance ordering or standard column ordering
        if ($this->isOrderingByRelevance() && $this->termsWithoutWildcards->isNotEmpty()) {
            $queryBuilder->orderBy('terms_count', 'desc');
        } else {
            // Always add the standard column ordering (even with model ordering as secondary sort)
            $orderKeys = $this->models->map(fn (ModelToSearchThrough $modelToSearchThrough): string => $searchGrammar->wrap($modelToSearchThrough->getModelKey('order'))
            )->toArray();

            $direction = $this->isOrderingByRelevance() ? 'asc' : $this->orderByDirection;
            /** @var array<int, string> $stringKeys */
            $stringKeys = array_values($orderKeys);
            $queryBuilder->orderByRaw($searchGrammar->coalesce($stringKeys).' '.$direction);
        }

        return $queryBuilder;
    }

    /**
     * Paginates the compiled query or fetches all results.
     *
     * @return Collection<int, \stdClass>|\Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \stdClass>
     */
    protected function getIdAndOrderAttributes()
    {
        $builder = $this->getCompiledQueryBuilder();

        // Determine the pagination method to call on Eloquent\Builder
        $paginateMethod = $this->simplePaginate ? 'simplePaginate' : 'paginate';

        // get all results or limit the results by pagination
        if ($this->pageName !== '' && $this->pageName !== '0') {
            /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \stdClass> $paginated */
            $paginated = $builder->{$paginateMethod}($this->perPage, ['*'], $this->pageName, $this->page);

            return $paginated;
        }

        // Apply limit and offset if set
        // Note: SQLite requires LIMIT to be present when using OFFSET
        if ($this->offset !== null || $this->limit !== null) {
            $limit = $this->limit ?? PHP_INT_MAX; // Use max int if only offset is set
            $builder->limit($limit);

            if ($this->offset !== null) {
                $builder->offset($this->offset);
            }
        }

        return $builder->get();

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
     * @param  Collection<int, \stdClass>|\Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \stdClass>  $results
     * @return Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, Model>|null>
     */
    protected function getModelsPerType($results)
    {
        /** @var Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, Model>|null> $resultCollection */
        $resultCollection = $this->models
            ->keyBy(fn (ModelToSearchThrough $modelToSearchThrough): string => $modelToSearchThrough->getModelKey())
            ->map(function (ModelToSearchThrough $modelToSearchThrough, $key) use ($results) {
                /** @var Collection<int, mixed> $resultCollection */
                $resultCollection = $results instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
                    ? collect($results->items())
                    : $results;

                $ids = $resultCollection->pluck($key)->filter();

                return $ids->isNotEmpty()
                    ? $modelToSearchThrough->getFreshBuilder()->whereKey($ids)->get()->keyBy(function (Model $model): int|string {
                        $key = $model->getKey();
                        if (is_int($key) || is_string($key)) {
                            return $key;
                        }
                        if (is_scalar($key)) {
                            return (string) $key;
                        }
                        throw new \RuntimeException('Invalid model key type: '.gettype($key));
                    })
                    : null;
            });

        return $resultCollection;

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
     */
    public function count(?string $terms = null): int
    {
        $this->initializeTerms($terms ?: '');

        return $this->getCompiledQueryBuilder()->count();
    }

    /**
     * Initialize the search terms, execute the search query and retrieve all
     * models per type. Map the results to a Eloquent collection and set
     * the collection on the paginator (whenever used).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Model>|\Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \stdClass>
     */
    public function search(?string $terms = null)
    {
        $this->initializeTerms($terms ?: '');

        $results = $this->getIdAndOrderAttributes();

        $modelsPerType = $this->getModelsPerType($results);

        // Convert results to collection for uniform processing
        /** @var Collection<int, \stdClass> $resultCollection */
        $resultCollection = $results instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
            ? collect($results->items())
            : $results;

        // loop over the results again and replace the object with the related model
        $models = $resultCollection->map(function (\stdClass $item) use ($modelsPerType): Model {
            // from this set, pick '0_post_key'
            //
            // [
            //     "0_post_key": 1
            //     "0_post_order": "2020-07-08 19:51:08"
            //     "1_video_key": null
            //     "1_video_order": null
            // ]

            $itemArray = (array) $item;
            $modelKey = Collection::make($itemArray)->search(fn($value, string $key): bool => $value && Str::endsWith($key, '_key'));

            if ($modelKey === false) {
                throw new \RuntimeException('No model key found in search results');
            }

            /** @var \Illuminate\Database\Eloquent\Collection<int, Model>|null $modelCollection */
            $modelCollection = $modelsPerType->get((string) $modelKey);
            if (! $modelCollection) {
                throw new \RuntimeException('Model collection not found for key: '.$modelKey);
            }

            $keyValue = $item->{$modelKey};

            // Ensure we have a valid key for model lookup
            if ($keyValue === null) {
                throw new \RuntimeException('Model key value is null');
            }

            // Use the key value as-is since Laravel Collection can handle both string and int keys
            $modelLookupKey = $keyValue;

            // Laravel Collection->get() can handle both string and integer keys
            if (is_scalar($modelLookupKey)) {
                /** @var Model|null $model */
                $model = $modelCollection->get($modelLookupKey);
            } else {
                throw new \RuntimeException('Invalid model key type: '.gettype($modelLookupKey));
            }

            if ($model === null) {
                throw new \RuntimeException('Model not found for key: '.$modelLookupKey);
            }

            if ($this->includeModelTypeWithKey) {
                $searchType = method_exists($model, 'searchType') ? $model->searchType() : class_basename($model);
                $model->setAttribute($this->includeModelTypeWithKey, $searchType);
            }

            return $model;
        });

        $eloquentCollection = new EloquentCollection($models);

        // Apply distinct/unique filtering if requested
        if ($this->distinctKey !== null) {
            $eloquentCollection = $eloquentCollection->unique($this->distinctKey);
        } elseif ($this->distinctKey === '') {
            // Empty string means use default uniqueness (by model key)
            $eloquentCollection = $eloquentCollection->unique(fn($model): string => $model::class . '::' . $model->getKey());
        }

        if ($this->pageName !== '' && $this->pageName !== '0') {
            if ($results instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
                /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \stdClass> $paginatedResults */
                $paginatedResults = $results;
                // @phpstan-ignore-next-line
                $paginatedResults->setCollection($eloquentCollection);
                // Apply query string preservation if requested
                if ($this->preserveQueryString) {
                    // @phpstan-ignore-next-line
                    $paginatedResults->withQueryString();
                }
                return $paginatedResults;
            }
            if ($results instanceof \Illuminate\Contracts\Pagination\Paginator) {
                /** @var \Illuminate\Contracts\Pagination\Paginator<int, \stdClass> $simplePaginatedResults */
                $simplePaginatedResults = $results;
                // @phpstan-ignore-next-line
                $simplePaginatedResults->setCollection($eloquentCollection);
                // Apply query string preservation if requested
                if ($this->preserveQueryString) {
                    // @phpstan-ignore-next-line
                    $simplePaginatedResults->withQueryString();
                }
                // @phpstan-ignore-next-line
                return $simplePaginatedResults;
            }
        }

        return $eloquentCollection;
    }


    /**
     * Gets the search grammar, initializing it lazily on first access.
     *
     * @param  \Illuminate\Database\Connection|null  $connection  Optional database connection
     */
    protected function getSearchGrammar($connection = null): SearchGrammarInterface
    {
        if ($this->searchGrammar instanceof \ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SearchGrammarInterface) {
            return $this->searchGrammar;
        }

        // Use provided connection or get from first model
        $connection = $connection ?: $this->getFirstModelConnection();

        $this->searchGrammar = DatabaseGrammarFactory::make($connection);
        $this->updateWhereOperator();

        // Explicit assertion - this will never be null since we just set it above
        assert($this->searchGrammar instanceof \ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SearchGrammarInterface);

        return $this->searchGrammar;
    }

    /**
     * Gets the database connection from the first available model.
     */
    protected function getFirstModelConnection(): Connection
    {
        throw_unless(
            $firstModel = $this->models->first(),
            new \RuntimeException('No models have been added to search through.')
        );

        return $firstModel->getModel()->getConnection();
    }

    /**
     * Updates the where operator based on the current grammar capabilities.
     */
    protected function updateWhereOperator(): void
    {
        if ($this->searchGrammar instanceof \ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SearchGrammarInterface) {
            $this->whereOperator = $this->soundsLike && $this->searchGrammar->supportsSoundsLike()
                ? $this->searchGrammar->soundsLikeOperator()
                : 'like';
        }
    }
}
