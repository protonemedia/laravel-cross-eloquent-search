<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Exceptions;

use Exception;

class PaginateAlreadyPassedException extends Exception
{
    public static function make(string $place): static
    {
        return new static("You can't set $place if you use a paginate or simplePaginate.");
    }
}
