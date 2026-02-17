<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Dialects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use ProtoneMedia\LaravelCrossEloquentSearch\Contracts\DatabaseDialect;

class PostgreSQLDialect extends BaseDialect implements DatabaseDialect
{
    protected bool $useSimilaritySearch = false;

    public function getName(): string
    {
        return 'PostgreSQL';
    }

    public function addWhereTermsToQuery(Builder $query, array|string $column): void
    {
        $column = $this->searcher->isCaseInsensitive() ? $query->getGrammar()->wrap($column) : $column;

        $this->searcher->getSearchTerms()->each(function ($term) use ($query, $column) {
            if ($this->useSimilaritySearch) {
                // PostgreSQL similarity search using pg_trgm extension
                // Combines LIKE and similarity() for better results
                $this->searcher->isCaseInsensitive()
                    ? $query->orWhereRaw("LOWER({$column}) LIKE ? OR similarity({$column}, ?) > 0.3", [$term, str_replace('%', '', $term)])
                    : $query->orWhereRaw("{$column} LIKE ? OR similarity({$column}, ?) > 0.3", [$term, str_replace('%', '', $term)]);
            } else {
                $this->searcher->isCaseInsensitive()
                    ? $query->orWhereRaw("LOWER({$column}) LIKE ?", [$term])
                    : $query->orWhere($column, 'LIKE', $term);
            }
        });
    }

    public function useSoundsLike(): void
    {
        $this->useSimilaritySearch = true;
    }

    public function avoidSoundsLike(): void
    {
        $this->useSimilaritySearch = false;
    }

    public function makeCoalesce(Collection $keys): string
    {
        return 'COALESCE(' . $keys->implode(', ') . ')';
    }

    public function getCharLengthFunction(): string
    {
        return 'CHAR_LENGTH';
    }

    public function supportsFullTextSearch(): bool
    {
        return false;
    }

    public function supportsOrderByModel(): bool
    {
        return false;
    }
}