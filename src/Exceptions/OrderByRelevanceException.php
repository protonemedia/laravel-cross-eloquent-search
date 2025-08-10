<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Exceptions;

use Exception;

class OrderByRelevanceException extends Exception
{
    public static function relationColumnsNotSupported(): self
    {
        return new self('Cannot order by relevance when searching through relationship columns.');
    }
}