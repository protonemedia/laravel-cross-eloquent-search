<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\SQLiteConnection;

trait HandlesSQLite
{
    /**
     * Returns true if the current connection driver is SQLite.
     */
    protected function isSQLiteConnection(): bool
    {
        return $this->modelsToSearchThrough->first()->getModel()->getConnection() instanceof SQLiteConnection;
    }

    /**
     * Get the string length function name for SQLite.
     */
    protected function getSQLiteStringLengthFunction(): string
    {
        return 'LENGTH';
    }

    /**
     * SQLite requires subquery wrapping for UNION ORDER BY.
     */
    protected function sqliteRequiresSubquery(): bool
    {
        return true;
    }

    /**
     * Apply SQLite-specific ordering by wrapping in subquery.
     */
    protected function applySQLiteOrdering(QueryBuilder $unionQuery): QueryBuilder
    {
        return $this->applySubqueryOrdering($unionQuery);
    }

    /**
     * Add SQLite SOUNDS LIKE functionality using phonetic similarity patterns.
     */
    protected function addSQLiteSoundsLikeToQuery($query, string $column, string $term): void
    {
        $term = strtolower($term);

        $patterns = [
            $term,
            str_replace(['ph', 'f'], ['f', 'ph'], $term),
            str_replace(['c', 'k'], ['k', 'c'], $term),
            str_replace(['s', 'z'], ['z', 's'], $term),
            '%' . substr($term, 0, min(3, strlen($term))) . '%',
            '%' . substr($term, -2) . '%',
        ];

        $patterns = array_unique(array_filter($patterns));

        $query->orWhere(function ($subQuery) use ($column, $patterns) {
            foreach ($patterns as $pattern) {
                $subQuery->orWhereRaw("LOWER({$column}) LIKE ?", [$pattern]);
            }
        });
    }

    /**
     * Add SQLite full-text search simulation using LIKE patterns and boolean operators.
     */
    protected function addSQLiteFullTextSearch($query, array $columns, string $terms, array $options = []): void
    {
        $positiveTerms = [];
        $negativeTerms = [];

        foreach (explode(' ', trim($terms)) as $term) {
            $term = trim($term);
            if (empty($term)) {
                continue;
            }

            if (str_starts_with($term, '-')) {
                $negativeTerms[] = ltrim($term, '-');
            } elseif (str_starts_with($term, '+')) {
                $positiveTerms[] = ltrim($term, '+');
            } else {
                $positiveTerms[] = $term;
            }
        }

        $query->orWhere(function ($subQuery) use ($columns, $positiveTerms, $negativeTerms) {
            if (!empty($positiveTerms)) {
                foreach ($positiveTerms as $term) {
                    $subQuery->where(function ($termQuery) use ($columns, $term) {
                        foreach ($columns as $column) {
                            $termQuery->orWhere($column, 'like', "%{$term}%");
                        }
                    });
                }
            }

            foreach ($negativeTerms as $term) {
                foreach ($columns as $column) {
                    $subQuery->where($column, 'not like', "%{$term}%");
                }
            }
        });
    }
}
