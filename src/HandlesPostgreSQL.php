<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

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
