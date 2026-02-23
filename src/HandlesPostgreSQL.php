<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\PostgresConnection;

trait HandlesPostgreSQL
{
    /**
     * Returns true if the current connection driver is PostgreSQL.
     */
    protected function isPostgreSQLConnection(): bool
    {
        return $this->modelsToSearchThrough->first()->getModel()->getConnection() instanceof PostgresConnection;
    }

    /**
     * Get NULL casting for PostgreSQL types.
     */
    protected function getPostgresNullCast(string $type): string
    {
        return match ($type) {
            'key' => 'NULL::bigint',
            'order' => 'NULL::text',
            'model_order' => 'NULL::integer',
            default => 'null',
        };
    }

    /**
     * Cast column to text for PostgreSQL UNION compatibility.
     */
    protected function castPostgresForUnion(string $column): string
    {
        return "({$column})::text";
    }

    /**
     * PostgreSQL requires subquery wrapping for UNION ORDER BY.
     */
    protected function postgresRequiresSubquery(): bool
    {
        return true;
    }

    /**
     * Apply PostgreSQL-specific ordering by wrapping in subquery.
     */
    protected function applyPostgresOrdering(QueryBuilder $unionQuery): QueryBuilder
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

    /**
     * Add PostgreSQL-specific full-text search using to_tsvector/to_tsquery.
     */
    protected function addPostgreSQLFullTextSearch($query, array $columns, string $terms, array $options = []): void
    {
        $escapedTerms = str_replace("'", "''", $terms);

        $tsqueryParts = [];
        foreach (explode(' ', trim($escapedTerms)) as $term) {
            $term = trim($term);
            if (empty($term)) {
                continue;
            }

            if (str_starts_with($term, '-')) {
                $tsqueryParts[] = '!' . ltrim($term, '-') . ':*';
            } elseif (str_starts_with($term, '+')) {
                $tsqueryParts[] = ltrim($term, '+') . ':*';
            } else {
                $tsqueryParts[] = $term . ':*';
            }
        }

        $tsquery = implode(' & ', $tsqueryParts);

        if (count($columns) === 1) {
            $column = $columns[0];
            $query->orWhereRaw(
                "to_tsvector('english', {$column}) @@ to_tsquery('english', ?)",
                [$tsquery]
            );
        } else {
            $columnExpr = implode(" || ' ' || ", $columns);
            $query->orWhereRaw(
                "to_tsvector('english', {$columnExpr}) @@ to_tsquery('english', ?)",
                [$tsquery]
            );
        }
    }

    /**
     * PostgreSQL uses COALESCE with subquery context.
     */
    protected function makePostgresOrderBy(string $modelOrderKeys): string
    {
        return "COALESCE({$modelOrderKeys}, NULL)";
    }
}
