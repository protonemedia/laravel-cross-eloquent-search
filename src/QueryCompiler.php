<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SearchGrammarInterface;
use ProtoneMedia\LaravelCrossEloquentSearch\ValueObjects\OrderDirection;
use ProtoneMedia\LaravelCrossEloquentSearch\ValueObjects\ModelOrder;

class QueryCompiler
{
    public function __construct(
        private readonly SearchGrammarInterface $grammar,
        private readonly Collection $models,
        private readonly OrderDirection $orderDirection,
        private readonly ?ModelOrder $modelOrder = null
    ) {}

    /**
     * Compile all model queries into a unified query with UNION statements.
     */
    public function compile(array $modelQueries): QueryBuilder
    {
        $queries = collect($modelQueries);

        /** @var QueryBuilder $firstQuery */
        $firstQuery = $queries->shift()->toBase();

        // Union the other queries together
        $queries->each(fn ($query) => $firstQuery->union($query));

        return $this->needsUnionWrapper($firstQuery)
            ? $this->buildWrappedUnionQuery($firstQuery)
            : $this->buildStandardUnionQuery($firstQuery);
    }

    /**
     * Determine if the query needs to be wrapped for proper ordering.
     */
    private function needsUnionWrapper(QueryBuilder $query): bool
    {
        return $this->hasMultipleModels()
            && !$this->grammar->supportsUnionOrdering();
    }

    /**
     * Check if we're searching across multiple models.
     */
    private function hasMultipleModels(): bool
    {
        return $this->models->count() > 1;
    }

    /**
     * Build a wrapped union query for databases that don't support complex UNION ordering.
     */
    private function buildWrappedUnionQuery(QueryBuilder $firstQuery): QueryBuilder
    {
        $wrappedQuery = $this->grammar->wrapUnionQuery($firstQuery->toSql(), $firstQuery->getBindings());

        $wrapperQuery = DB::table(DB::raw($wrappedQuery['sql']))
            ->setBindings($wrappedQuery['bindings'])
            ->select('*');

        $this->applyOrdering($wrapperQuery);

        return $wrapperQuery;
    }

    /**
     * Build a standard union query for databases that support complex UNION ordering.
     */
    private function buildStandardUnionQuery(QueryBuilder $firstQuery): QueryBuilder
    {
        $this->applyOrdering($firstQuery);

        return $firstQuery;
    }

    /**
     * Apply all ordering to the query.
     */
    private function applyOrdering(QueryBuilder $query): void
    {
        $this->applyModelOrdering($query);
        $this->applyRelevanceOrdering($query);
        $this->applyStandardOrdering($query);
    }

    /**
     * Apply model ordering to the query.
     */
    private function applyModelOrdering(QueryBuilder $query): void
    {
        if (!$this->modelOrder?->hasOrder()) {
            return;
        }

        $modelOrderKeys = $this->models->map(function ($modelToSearchThrough) {
            return $this->grammar->wrap($modelToSearchThrough->getModelKey('model_order'));
        })->toArray();

        $modelCoalesceExpr = $this->grammar->coalesce($modelOrderKeys);

        $query->orderByRaw($modelCoalesceExpr . ' ' . $this->getOrderDirection());
    }

    /**
     * Apply relevance ordering to the query.
     */
    private function applyRelevanceOrdering(QueryBuilder $query): void
    {
        if ($this->orderDirection->isRelevance()) {
            $query->orderBy('terms_count', 'desc');
        }
    }

    /**
     * Apply standard column ordering to the query.
     */
    private function applyStandardOrdering(QueryBuilder $query): void
    {
        if ($this->orderDirection->isRelevance()) {
            return;
        }

        $orderKeys = $this->models->map(function ($modelToSearchThrough) {
            return $this->grammar->wrap($modelToSearchThrough->getModelKey('order'));
        })->toArray();

        $coalesceExpr = $this->grammar->coalesce($orderKeys);

        $query->orderByRaw($coalesceExpr . ' ' . $this->getOrderDirection());
    }

    /**
     * Get the appropriate order direction string.
     */
    private function getOrderDirection(): string
    {
        return $this->orderDirection->toString();
    }
}