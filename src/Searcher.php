<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use ProtoneMedia\LaravelCrossEloquentSearch\Exceptions\LimitAlreadyPassedException;
use ProtoneMedia\LaravelCrossEloquentSearch\Exceptions\OffsetAlreadyPassedException;
use ProtoneMedia\LaravelCrossEloquentSearch\Exceptions\PaginateAlreadyPassedException;
use ProtoneMedia\LaravelCrossEloquentSearch\Exceptions\OrderByRelevanceException;

class Searcher
{
    use Conditionable;

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
    protected null|array $orderByModel = null;

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
    protected null|string $rawTerms = null;

    /**
     * Collection of search terms.
     */
    protected Collection $terms;

    /**
     * Collection of search terms.
     */
    protected Collection $termsWithoutWildcards;

    /**
     * The number of items to be shown.
     */
    protected null|int $limit = null;

    /**
     * The number of items to be skipped.
     */
    protected null|int $offset = null;

    /**
     * The query string variable used to store the page.
     */
    protected null|string $pageName = null;

    /**
     * Parse the search term into multiple terms.
     */
    protected bool $parseTerm = true;

    /**
     * Use simplePaginate() on Eloquent\Builder vs paginate()
     */
    protected bool $simplePaginate = false;

    /**
     * If used simplePaginate or paginate equals true
     * If used limit or offset equals false
     * Else equals null
     *
     * @var bool|null
     */
    protected null|bool $isPaginate = null;

    /**
     * Current page.
     *
     * @var int|null
     */
    protected null|int $page = null;

    /**
     * Include the model type in the search results.
     */
    protected null|string $includeModelTypeWithKey = null;

    /**
     * Uses for wrap raw fields.
     *
     * @var MySqlGrammar
     */
    protected MySqlGrammar $grammar;

    /**
     * Initialises the instanace with a fresh Collection and default sort.
     */
    public function __construct()
    {
        $this->modelsToSearchThrough = new Collection();
        $this->grammar = new MySqlGrammar();

        $this->orderByAsc();
    }

    /**
     * Sort the results in ascending order.
     *
     * @return $this
     */
    public function orderByAsc(): static
    {
        $this->orderByDirection = 'asc';

        return $this;
    }

    /**
     * Sort the results in descending order.
     *
     * @return $this
     */
    public function orderByDesc(): static
    {
        $this->orderByDirection = 'desc';

        return $this;
    }

    /**
     * Sort the results in relevance order.
     *
     * @return $this
     */
    public function orderByRelevance(): static
    {
        $this->orderByDirection = 'relevance';

        return $this;
    }

    /**
     * Sort the results in order of the given models.
     *
     * @param iterable|string $modelClasses
     * @return $this
     */
    public function orderByModel(iterable|string $modelClasses): static
    {
        $this->orderByModel = Arr::wrap($modelClasses);

        return $this;
    }

    /**
     * Disable the parsing of the search term.
     * @return $this
     */
    public function dontParseTerm(): static
    {
        $this->parseTerm = false;

        return $this;
    }

    /**
     * Enable the inclusion of the model type in the search results.
     *
     * @param string $key
     * @return $this
     */
    public function includeModelType(string $key = 'type'): static
    {
        $this->includeModelTypeWithKey = $key;

        return $this;
    }

    /**
     * Add a model to search through.
     *
     * @param class-string|Builder $query
     * @param iterable|string|Collection|null $columns
     * @param null|string $orderByColumn
     * @return $this
     */
    public function add(Builder|string $query, iterable|string|Collection $columns = null, null|string $orderByColumn = null): static
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

    /**
     * @param class-string|Builder $query
     * @param iterable|string|Collection|null $columns
     * @param array $options
     * @param string|null $orderByColumn
     * @return $this
     */
    public function addFullText(Builder|string $query, iterable|string|Collection $columns = null, array $options = [], null|string $orderByColumn = null): static
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
     * @param iterable $queries
     * @return $this
     */
    public function addMany(iterable $queries): static
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
     * @return $this
     */
    public function orderBy(string $orderByColumn): static
    {
        $this->modelsToSearchThrough->last()->orderByColumn($orderByColumn);

        return $this;
    }

    /**
     * Ignore case of terms.
     *
     * @param boolean $state
     * @return $this
     */
    public function ignoreCase(bool $state = true): static
    {
        $this->ignoreCase = $state;

        return $this;
    }

    /**
     * Let's each search term begin with a wildcard.
     *
     * @param boolean $state
     * @return $this
     */
    public function beginWithWildcard(bool $state = true): static
    {
        $this->beginWithWildcard = $state;

        return $this;
    }

    /**
     * Let's each search term end with a wildcard.
     *
     * @param boolean $state
     * @return $this
     */
    public function endWithWildcard(bool $state = true): static
    {
        $this->endWithWildcard = $state;

        return $this;
    }

    /**
     * Use 'sounds like' operator instead of 'like'.
     *
     * @return $this
     */
    public function soundsLike(bool $state = true): static
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
     * @return $this
     */
    public function paginate(int $perPage = 15, string $pageName = 'page', null|int $page = null): static
    {
        if ($this->isPaginate === false) {
            if (!is_null($this->offset)) {
                throw OffsetAlreadyPassedException::make();
            }

            throw LimitAlreadyPassedException::make();
        }

        $this->page = $page ?: Paginator::resolveCurrentPage($pageName);
        $this->pageName = $pageName;
        $this->limit = $perPage;
        $this->simplePaginate = false;
        $this->isPaginate = true;

        return $this;
    }

    /**
     * Paginate using simple pagination.
     *
     * @param integer $perPage
     * @param string $pageName
     * @param int|null $page
     * @return $this
     */
    public function simplePaginate(int $perPage = 15, string $pageName = 'page', null|int $page = null): static
    {
        $this->paginate($perPage, $pageName, $page);

        $this->simplePaginate = true;

        return $this;
    }

    /**
     * Set the offset
     *
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): static
    {
        if ($this->isPaginate) {
            throw PaginateAlreadyPassedException::make(__FUNCTION__);
        }

        $this->offset = $offset;
        $this->isPaginate = false;

        return $this;
    }

    /**
     * Set limit
     *
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): static
    {
        if ($this->isPaginate) {
            throw PaginateAlreadyPassedException::make(__FUNCTION__);
        }

        $this->limit = $limit;
        $this->isPaginate = false;

        return $this;
    }

    /**
     * Parse the terms and loop through them with the optional callable.
     *
     * @param string $terms
     * @param null|callable $callback
     * @return Collection
     */
    public function parseTerms(string $terms, null|callable $callback = null): Collection
    {
        return Collection::make(str_getcsv($terms, ' ', '"'))
            ->filter()
            ->values()
            ->when(!is_null($callback), function ($terms) use ($callback) {
                return $terms->each(fn ($value, $key) => call_user_func($callback, $value, $key));
            });
    }

    /**
     * Creates a collection out of the given search term.
     *
     * @param string $terms
     * @return $this
     */
    protected function initializeTerms(string $terms): static
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
     *
     * @param Builder $builder
     * @param ModelToSearchThrough $modelToSearchThrough
     * @return void
     */
    public function addSearchQueryToBuilder(Builder $builder, ModelToSearchThrough $modelToSearchThrough): void
    {
        if ($this->termsWithoutWildcards->isEmpty()) {
            return;
        }

        $builder->where(function (Builder $query) use ($modelToSearchThrough) {
            if (!$modelToSearchThrough->isFullTextSearch()) {
                $modelToSearchThrough->getColumns()->each(
                    function ($column) use ($query, $modelToSearchThrough) {
                        Str::contains($column, '.')
                            ? $this->addNestedRelationToQuery($query, $column)
                            : $this->addWhereTermsToQuery($query, $modelToSearchThrough->qualifyColumn($column));
                    }
                );

                return;
            }

            $modelToSearchThrough
                ->toGroupedCollection()
                ->each(function (ModelToSearchThrough $modelToSearchThrough) use ($query) {
                    if ($relation = $modelToSearchThrough->getFullTextRelation()) {
                        $query->orWhereHas($relation, function ($relationQuery) use ($modelToSearchThrough) {
                            $relationQuery->where(function ($query) use ($modelToSearchThrough) {
                                $query->orWhereFullText(
                                    $modelToSearchThrough
                                        ->getColumns()
                                        ->all(),
                                    $this->rawTerms,
                                    $modelToSearchThrough
                                        ->getFullTextOptions()
                                );
                            });
                        });
                    } else {
                        $query->orWhereFullText(
                            $modelToSearchThrough
                                ->getColumns()
                                ->map(fn ($column) => $modelToSearchThrough
                                    ->qualifyColumn($column))
                                ->all(),
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
     * @param Builder $query
     * @param string $nestedRelationAndColumn
     * @return void
     */
    private function addNestedRelationToQuery(Builder $query, string $nestedRelationAndColumn): void
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
     * @param Builder $query
     * @param string $column
     * @return void
     */
    private function addWhereTermsToQuery(Builder $query, string $column): void
    {
        $column = $this->ignoreCase ? $this->grammar->wrap($column) : $column;

        $this->terms->each(function ($term) use ($query, $column) {
            $this->ignoreCase
                ? $query->orWhereRaw("LOWER({$column}) {$this->whereOperator} ?", [$term])
                : $query->orWhere($column, $this->whereOperator, $term);
        });
    }

    /**
     * Adds a word count, so we can order by relevance.
     *
     * @param Builder $builder
     * @param ModelToSearchThrough $modelToSearchThrough
     * @return void
     * @throws OrderByRelevanceException
     */
    private function addRelevanceQueryToBuilder(Builder $builder, ModelToSearchThrough $modelToSearchThrough): void
    {
        if (!$this->isOrderingByRelevance() || $this->termsWithoutWildcards->isEmpty()) {
            return;
        }

        if (Str::contains($modelToSearchThrough->getColumns()->implode(''), '.')) {
            throw OrderByRelevanceException::make();
        }


        $expressionsAndBindings = $modelToSearchThrough->getQualifiedColumns()->flatMap(function ($field) use ($modelToSearchThrough) {
            $prefix = $modelToSearchThrough->getModel()->getConnection()->getTablePrefix();
            $field = $this->grammar->wrap($field);

            return $this->termsWithoutWildcards->map(function ($term) use ($field) {
                return [
                    'expression' => "COALESCE(CHAR_LENGTH(LOWER({$field})) - CHAR_LENGTH(REPLACE(LOWER({$field}), ?, ?)), 0)",
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
     *
     * @param ModelToSearchThrough $currentModel
     * @return array
     */
    protected function makeSelects(ModelToSearchThrough $currentModel): array
    {
        return $this->modelsToSearchThrough->flatMap(
            function (ModelToSearchThrough $modelToSearchThrough) use ($currentModel) {
                $qualifiedKeyName = $qualifiedOrderByColumnName = $modelOrderKey = 'null';

                if ($modelToSearchThrough === $currentModel) {
                    $prefix = $modelToSearchThrough->getModel()->getConnection()->getTablePrefix();

                    $qualifiedKeyName = $prefix . $modelToSearchThrough->getQualifiedKeyName();
                    $qualifiedOrderByColumnName = $prefix . $modelToSearchThrough->getQualifiedOrderByColumnName();

                    if ($this->orderByModel) {
                        $modelOrderKey = array_search(
                            get_class($modelToSearchThrough->getModel()),
                            $this->orderByModel
                        );

                        if ($modelOrderKey === false) {
                            $modelOrderKey = count($this->orderByModel);
                        }
                    }
                }

                return array_filter([
                    DB::raw("{$qualifiedKeyName} as {$modelToSearchThrough->getModelKey()}"),
                    DB::raw("{$qualifiedOrderByColumnName} as {$modelToSearchThrough->getModelKey('order')}"),
                    $this->orderByModel ? DB::raw(
                        "{$modelOrderKey} as {$modelToSearchThrough->getModelKey('model_order')}"
                    ) : null,
                ]);
            }
        )->all();
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
     * Implodes the qualified orderByModel keys with a comma and
     * wraps them in a COALESCE method.
     *
     * @return string
     */
    protected function makeOrderByModel(): string
    {
        $modelOrderKeys = $this->modelsToSearchThrough->map->getModelKey('model_order')->implode(',');

        return "COALESCE({$modelOrderKeys})";
    }

    /**
     * Builds the search queries for each given pending model.
     *
     * @return Collection
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
     * @return QueryBuilder
     */
    protected function getCompiledQueryBuilder(): QueryBuilder
    {
        $queries = $this->buildQueries();

        // take the first query

        /** @var BaseBuilder $firstQuery */
        $firstQuery = $queries->shift()->toBase();

        // union the other queries together
        $queries->each(fn (Builder $query) => $firstQuery->union($query));

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
     * @return Collection|LengthAwarePaginator|Paginator
     */
    protected function getIdAndOrderAttributes(): Collection|LengthAwarePaginator|Paginator
    {
        $query = $this->getCompiledQueryBuilder();

        if (!is_null($this->pageName)) {
            // Determine the pagination method to call on Eloquent\Builder
            $paginateMethod = $this->simplePaginate ? 'simplePaginate' : 'paginate';

            // get limit the results by pagination
            return $query->{$paginateMethod}($this->limit, ['*'], $this->pageName, $this->page);
        }

        // get results
        return $query
            ->when(!is_null($this->limit), fn($query) => $query
                ->limit($this->limit))
            ->when(!is_null($this->offset), fn($query) => $query
                ->offset($this->offset))
            ->get();

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
     * @param Collection|LengthAwarePaginator|Paginator $results
     * @return Collection
     */
    protected function getModelsPerType(Collection|LengthAwarePaginator|Paginator $results): Collection
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
     * @param null|string $terms
     * @return integer
     */
    public function count(null|string $terms = null): int
    {
        $this->initializeTerms($terms ?: '');

        return $this->getCompiledQueryBuilder()->count();
    }

    /**
     * Initialize the search terms, execute the search query and retrieve all
     * models per type. Map the results to a Eloquent collection and set
     * the collection on the paginator (whenever used).
     *
     * @param null|string $terms
     * @return EloquentCollection|LengthAwarePaginator|Paginator
     */
    public function search(null|string $terms = null): EloquentCollection|LengthAwarePaginator|Paginator
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
}
