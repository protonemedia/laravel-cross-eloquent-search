<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Dialects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use ProtoneMedia\LaravelCrossEloquentSearch\Contracts\DatabaseDialect;
use ProtoneMedia\LaravelCrossEloquentSearch\Exceptions\UnsupportedOperationException;

class SQLiteDialect extends BaseDialect implements DatabaseDialect
{
    public function getName(): string
    {
        return 'SQLite';
    }

    public function addWhereTermsToQuery(Builder $query, array|string $column): void
    {
        $column = $this->searcher->isCaseInsensitive() ? $query->getGrammar()->wrap($column) : $column;

        $this->searcher->getSearchTerms()->each(function ($term) use ($query, $column) {
            $this->searcher->isCaseInsensitive()
                ? $query->orWhereRaw("LOWER({$column}) LIKE ?", [$term])
                : $query->orWhere($column, 'LIKE', $term);
        });
    }

    public function useSoundsLike(): void
    {
        throw new UnsupportedOperationException('The SQLite driver does not support sounds like matching');
    }

    public function avoidSoundsLike(): void
    {
        // No-op for SQLite
    }

    public function makeCoalesce(Collection $keys): string
    {
        $fields = $keys->implode(', ');
        if ($keys->count() < 2) {
            $fields .= ', NULL';
        }

        return "COALESCE({$fields})";
    }

    public function getCharLengthFunction(): string
    {
        return 'LENGTH';
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