<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Dialects;

use ProtoneMedia\LaravelCrossEloquentSearch\Searcher;

abstract class BaseDialect
{
    public function __construct(
        protected Searcher $searcher
    ) {
    }
}