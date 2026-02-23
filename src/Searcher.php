<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Tappable;

class Searcher
{
    use Conditionable;
    use HandlesMySQL;
    use HandlesPostgreSQL;
    use HandlesSQLite;
    use Tappable;

    /**
     * Collection of models to search through.
     */
    protected Collection $modelsToSearchThrough;

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
     * Initialises the instanace with a fresh Collection and default sort.
     */
    public function __construct()
    {
        $this->modelsToSearchThrough = new Collection;

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
     */
    public function orderByModel($modelClasses): self
    {
        $this->orderByModel = Arr::wrap($modelClasses);

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
     * @param  \Illuminate\Database\Eloquent\Builder|string  $query
     * @param  string|array|\Illuminate\Support\Collection  $columns
     * @param  bool  $fullText
     */
    public function add($query, $columns = null, ?string $orderByColumn = null): self
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
            $this->modelsToSearchThrough->count(),
        );

        $this->modelsToSearchThrough->push($modelToSearchThrough);

        return $this;
    }

    public function addFullText($query, $columns = null, array $options = [], ?string $orderByColumn = null): self
    {
        $builder = is_string($query) ? $query::query() : $query;

        $modelToSearchThrough = new ModelToSearchThrough(
            $builder,
            Collection::wrap($columns),
            $orderByColumn ?: $builder->getModel()->getUpdatedAtColumn(),
            $this->modelsToSearchThrough->count(),
            true,
            $options
        );

        $this->modelsToSearchThrough->push($modelToSearchThrough);

        return $this;
    }

    /**
     * Loop through the queries and add them.
     *
     * @param  mixed  $value
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
     */
    public function orderBy(string $orderByColumn): self
    {
        $this->modelsToSearchThrough->last()->orderByColumn($orderByColumn);

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
     * Let's each search term be an exact match.
     */
    public function exactMatch(): self
    {
        $this->beginWithWildcard(false)->endWithWildcard(false);
        $this->whereOperator = '=';

        return $this;
    }

    /**
     * Use 'sounds like' operator instead of 'like'.
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
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int|null  $page
     */
    public function paginate($perPage = 15, $pageName = 'page', $page = null): self
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
     * Parse the terms and loop through them with the optional callable.
     */
    public function parseTerms(string $terms, ?callable $callback = null): Collection
    {
        $callback = $callback ?: fn () => null;

        return Collection::make(str_getcsv($terms, ' ', '"', "\\"))
            ->filter()
            ->values()
            ->when($callback !== null, function ($terms) use ($callback) {
                return $terms->each(fn ($value, $key) => $callback($value, $key));
            });
    }

    /**
     * Creates a collection out of the given search term.
     *
     * @throws \ProtoneMedia\LaravelCrossEloquentSearch\EmptySearchQueryException
     */
    protected function initializeTerms(string $terms): self
    {
        $this->rawTerms = $terms;

        $terms = $this->parseTerm ? $this->parseTerms($terms) : $terms;

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
     */
    public function addSearchQueryToBuilder(Builder $builder, ModelToSearchThrough $modelToSearchThrough): void
    {
        if ($this->termsWithoutWildcards->isEmpty()) {
            return;
        }

        $builder->where(function (Builder $query) use ($modelToSearchThrough) {
            if (! $modelToSearchThrough->isFullTextSearch()) {
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
                                $this->addFullTextSearchToQuery(
                                    $query,
                                    $modelToSearchThrough->getColumns()->all(),
                                    $this->rawTerms,
                                    $modelToSearchThrough->getFullTextOptions()
                                );
                            });
                        });
                    } else {
                        $this->addFullTextSearchToQuery(
                            $query,
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
     * @param  string  $column
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
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  array|string  $columns
     * @return void
     */
    private function addWhereTermsToQuery(Builder $query, $column)
    {
        $column = $this->ignoreCase ? $query->getConnection()->getQueryGrammar()->wrap($column) : $column;

        $this->terms->each(function ($term) use ($query, $column) {
            if ($this->soundsLike) {
                $this->addSoundsLikeToQuery($query, $column, $term);
            } elseif ($this->ignoreCase) {
                $query->orWhereRaw("LOWER({$column}) {$this->whereOperator} ?", [$term]);
            } else {
                $query->orWhere($column, $this->whereOperator, $term);
            }
        });
    }

    /**
     * Add SOUNDS LIKE query based on driver capabilities.
     */
    private function addSoundsLikeToQuery(Builder $query, string $column, string $term): void
    {
        $cleanTerm = str_replace('%', '', $term);

        if ($this->isPostgreSQLConnection()) {
            $query->orWhereRaw("similarity({$column}, ?) > 0.3", [$cleanTerm]);
        } elseif ($this->isSQLiteConnection()) {
            $this->addSQLiteSoundsLikeToQuery($query, $column, $cleanTerm);
        }
    }

    /**
     * Adds a word count so we can order by relevance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \ProtoneMedia\LaravelCrossEloquentSearch\ModelToSearchThrough  $modelToSearchThrough
     * @return void
     */
    private function addRelevanceQueryToBuilder($builder, $modelToSearchThrough)
    {
        if (! $this->isOrderingByRelevance() || $this->termsWithoutWildcards->isEmpty()) {
            return;
        }

        if (Str::contains($modelToSearchThrough->getColumns()->implode(''), '.')) {
            throw OrderByRelevanceException::new();
        }

        $lengthFunctionName = $this->isSQLiteConnection()
            ? $this->getSQLiteStringLengthFunction()
            : 'CHAR_LENGTH';

        $expressionsAndBindings = $modelToSearchThrough->getQualifiedColumns()->flatMap(function ($field) use ($modelToSearchThrough, $lengthFunctionName) {
            $connection = $modelToSearchThrough->getModel()->getConnection();
            $prefix = $connection->getTablePrefix();
            $field = $connection->getQueryGrammar()->wrap($prefix.$field);

            return $this->termsWithoutWildcards->map(function ($term) use ($field, $lengthFunctionName) {
                return [
                    'expression' => sprintf(
                        'COALESCE(%1$s(LOWER(%2$s)) - %1$s(REPLACE(LOWER(%2$s), ?, ?)), 0)',
                        $lengthFunctionName,
                        $field
                    ),
                    'bindings' => [Str::lower($term), Str::substr(Str::lower($term), 1)],
                ];
            });
        });

        $selects = $expressionsAndBindings->map->expression->implode(' + ');
        $bindings = $expressionsAndBindings->flatMap->bindings->all();

        $builder->selectRaw("{$selects} as terms_count", $bindings);
    }

    /**
     * Builds an array with all qualified columns for
     * both the ids and ordering.
     */
    protected function makeSelects(ModelToSearchThrough $currentModel): array
    {
        return $this->modelsToSearchThrough->flatMap(function (ModelToSearchThrough $modelToSearchThrough) use ($currentModel) {
            $qualifiedKeyName = $this->isPostgreSQLConnection()
                ? $this->getPostgresNullCast('key')
                : 'null';
            $qualifiedOrderByColumnName = $this->isPostgreSQLConnection()
                ? $this->getPostgresNullCast('order')
                : 'null';
            $modelOrderKey = $this->isPostgreSQLConnection()
                ? $this->getPostgresNullCast('model_order')
                : 'null';

            if ($modelToSearchThrough === $currentModel) {
                $prefix = $modelToSearchThrough->getModel()->getConnection()->getTablePrefix();

                $qualifiedKeyName = $prefix.$modelToSearchThrough->getQualifiedKeyName();
                $orderColumn = $prefix.$modelToSearchThrough->getQualifiedOrderByColumnName();
                $qualifiedOrderByColumnName = $this->isPostgreSQLConnection()
                    ? $this->castPostgresForUnion($orderColumn)
                    : $orderColumn;

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

            $grammar = $modelToSearchThrough->getModel()->getConnection()->getQueryGrammar();

            $keyAlias = $grammar->wrap($modelToSearchThrough->getModelKey());
            $orderAlias = $grammar->wrap($modelToSearchThrough->getModelKey('order'));
            $modelOrderAlias = $grammar->wrap($modelToSearchThrough->getModelKey('model_order'));

            return array_filter([
                DB::raw("{$qualifiedKeyName} as {$keyAlias}"),
                DB::raw("{$qualifiedOrderByColumnName} as {$orderAlias}"),
                $this->orderByModel ? DB::raw("{$modelOrderKey} as {$modelOrderAlias}") : null,
            ]);
        })->all();
    }

    /**
     * Implodes the qualified order keys with a comma and
     * wraps them in a COALESCE method.
     */
    protected function makeOrderBy(): string
    {
        $grammar = $this->modelsToSearchThrough->first()->getModel()->getConnection()->getQueryGrammar();

        $modelOrderKeys = $this->modelsToSearchThrough->map->getModelKey('order')
            ->map(fn ($key) => $grammar->wrap($key))
            ->implode(',');

        return match (true) {
            $this->isSQLiteConnection() => $this->makeSQLiteOrderBy($modelOrderKeys),
            $this->isPostgreSQLConnection() => $this->makePostgresOrderBy($modelOrderKeys),
            default => $this->makeMySQLOrderBy($modelOrderKeys),
        };
    }

    /**
     * Implodes the qualified orderByModel keys with a comma and
     * wraps them in a COALESCE method.
     */
    protected function makeOrderByModel(): string
    {
        $grammar = $this->modelsToSearchThrough->first()->getModel()->getConnection()->getQueryGrammar();

        $modelOrderKeys = $this->modelsToSearchThrough->map->getModelKey('model_order')
            ->map(fn ($key) => $grammar->wrap($key))
            ->implode(',');

        return match (true) {
            $this->isSQLiteConnection() => $this->makeSQLiteOrderBy($modelOrderKeys),
            $this->isPostgreSQLConnection() => $this->makePostgresOrderBy($modelOrderKeys),
            default => $this->makeMySQLOrderBy($modelOrderKeys),
        };
    }

    /**
     * Builds the search queries for each given pending model.
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

        // take the first query

        /** @var BaseBuilder $firstQuery */
        $firstQuery = $queries->shift()->toBase();

        // union the other queries together
        $queries->each(fn (Builder $query) => $firstQuery->union($query));

        // SQLite and PostgreSQL require subquery wrapping for UNION ORDER BY
        if ($this->isSQLiteConnection()) {
            return $this->applySQLiteOrdering($firstQuery);
        }

        if ($this->isPostgreSQLConnection()) {
            return $this->applyPostgresOrdering($firstQuery);
        }

        if ($this->orderByModel) {
            $firstQuery->orderBy(
                DB::raw($this->makeOrderByModel()),
                $this->isOrderingByRelevance() ? 'asc' : $this->orderByDirection
            );
        }

        if ($this->isOrderingByRelevance() && $this->termsWithoutWildcards->isNotEmpty()) {
            return $firstQuery->orderBy('terms_count', 'desc');
        }

        // sort by the given columns and direction
        return $firstQuery->orderBy(
            DB::raw($this->makeOrderBy()),
            $this->isOrderingByRelevance() ? 'asc' : $this->orderByDirection
        );
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
     * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator  $results
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
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function search(?string $terms = null)
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
     * Add database-specific full-text search to query.
     */
    protected function addFullTextSearchToQuery($query, array $columns, string $terms, array $options = []): void
    {
        if ($this->isPostgreSQLConnection()) {
            $this->addPostgreSQLFullTextSearch($query, $columns, $terms, $options);
        } elseif ($this->isSQLiteConnection()) {
            $this->addSQLiteFullTextSearch($query, $columns, $terms, $options);
        } else {
            $query->orWhereFullText($columns, $terms, $options);
        }
    }
}
