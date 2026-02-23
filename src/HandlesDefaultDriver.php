<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Query\Builder as QueryBuilder;

trait HandlesDefaultDriver
{
    /**
     * Get the string length function name (default for MySQL).
     */
    protected function getStringLengthFunction(): string
    {
        return 'CHAR_LENGTH';
    }

    /**
     * Get NULL value for non-PostgreSQL drivers.
     */
    protected function getNullCast(string $type): string
    {
        return 'null';
    }

    /**
     * No casting needed for UNION on default drivers.
     */
    protected function castForUnion(string $column): string
    {
        return $column;
    }

    /**
     * Default drivers don't need subquery wrapping for UNION ORDER BY.
     */
    protected function requiresSubqueryForUnion(): bool
    {
        return false;
    }

    /**
     * Apply default ordering (no subquery needed).
     */
    protected function applyDriverSpecificOrdering(QueryBuilder $unionQuery): QueryBuilder
    {
        return $unionQuery;
    }

    /**
     * Apply subquery ordering (used by SQLite/PostgreSQL traits).
     */
    protected function applySubqueryOrdering(QueryBuilder $unionQuery): QueryBuilder
    {
        $subQuery = \Illuminate\Support\Facades\DB::query()->fromSub($unionQuery, 'union_results');

        if ($this->orderByModel) {
            $subQuery->orderBy(
                \Illuminate\Support\Facades\DB::raw($this->makeOrderByModel()),
                $this->isOrderingByRelevance() ? 'asc' : $this->orderByDirection
            );
        }

        if ($this->isOrderingByRelevance() && $this->termsWithoutWildcards->isNotEmpty()) {
            return $subQuery->orderBy('terms_count', 'desc');
        }

        return $subQuery->orderBy(
            \Illuminate\Support\Facades\DB::raw($this->makeOrderBy()),
            $this->isOrderingByRelevance() ? 'asc' : $this->orderByDirection
        );
    }
}
