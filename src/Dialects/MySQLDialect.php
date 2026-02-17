<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Dialects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use ProtoneMedia\LaravelCrossEloquentSearch\Contracts\DatabaseDialect;

class MySQLDialect extends BaseDialect implements DatabaseDialect
{
    protected string $whereOperator = 'like';

    public function getName(): string
    {
        return 'MySQL';
    }

    public function addWhereTermsToQuery(Builder $query, array|string $column): void
    {
        $column = $this->searcher->isCaseInsensitive() ? $query->getGrammar()->wrap($column) : $column;

        $this->searcher->getSearchTerms()->each(function ($term) use ($query, $column) {
            $this->searcher->isCaseInsensitive()
                ? $query->orWhereRaw("LOWER({$column}) {$this->whereOperator} ?", [$term])
                : $query->orWhere($column, $this->whereOperator, $term);
        });
    }

    public function useSoundsLike(): void
    {
        $this->whereOperator = 'sounds like';
    }

    public function avoidSoundsLike(): void
    {
        $this->whereOperator = 'like';
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
        return true;
    }

    public function supportsOrderByModel(): bool
    {
        return true;
    }
}