<?php

namespace ProtoneMedia\LaravelCrossEloquentSearch\Tests\ExtendedSearcher;

class ExtendedSearcher extends \ProtoneMedia\LaravelCrossEloquentSearch\Searcher
{
    public static bool $searchWasCalled = false;

    protected function getIdAndOrderAttributes()
    {
        self::$searchWasCalled = true;

        return parent::getIdAndOrderAttributes();
    }
}

