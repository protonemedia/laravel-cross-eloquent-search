<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Exception;

class UnsupportedOperationException extends Exception
{
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}