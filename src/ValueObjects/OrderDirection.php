<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\ValueObjects;

enum OrderDirection: string
{
    case ASC = 'asc';
    case DESC = 'desc';
    case RELEVANCE = 'relevance';

    public function isRelevance(): bool
    {
        return $this === self::RELEVANCE;
    }

    public function toString(): string
    {
        return match ($this) {
            self::RELEVANCE => 'asc',
            default => $this->value,
        };
    }
}