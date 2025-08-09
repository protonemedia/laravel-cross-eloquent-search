<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\ValueObjects;

enum SearchOperator: string
{
    case LIKE = 'like';
    case SOUNDS_LIKE = 'sounds like';

    public function isSoundsLike(): bool
    {
        return $this === self::SOUNDS_LIKE;
    }
}