<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SQLiteConnection;

trait HandlesDatabaseDrivers
{
    /**
     * Returns true if the current connection driver is SQLite.
     */
    protected function isSQLiteConnection(): bool
    {
        return $this->modelsToSearchThrough->first()->getModel()->getConnection() instanceof SQLiteConnection;
    }

    /**
     * Returns true if the current connection driver is PostgreSQL.
     */
    protected function isPostgreSQLConnection(): bool
    {
        return $this->modelsToSearchThrough->first()->getModel()->getConnection() instanceof PostgresConnection;
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
}
