<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Exceptions;

use Exception;

class OrderByRelevanceException extends Exception
{
    public static function make(): static
    {
        return new static("You can't order by relevance if you're searching through nested relations.");
    }
}
