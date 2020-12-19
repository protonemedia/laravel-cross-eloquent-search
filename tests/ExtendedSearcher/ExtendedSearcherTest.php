<?php

namespace ProtoneMedia\LaravelCrossEloquentSearch\Tests\ExtendedSearcher;

use ProtoneMedia\LaravelCrossEloquentSearch\Tests\Post;
use ProtoneMedia\LaravelCrossEloquentSearch\Tests\TestCase;
use ProtoneMedia\LaravelCrossEloquentSearch\Tests\Video;

class ExtendedSearcherTest extends TestCase
{
    /** @test */
    public function it_can_use_extended_searcher_to_search()
    {
        Post::create(['title' => 'foo']);
        Post::create(['title' => 'bar']);
        Video::create(['title' => 'foo']);
        Video::create(['title' => 'bar']);

        $results = (new ExtendedSearcher())
            ->add(Post::class)->orderBy('updated_at')
            ->add(Video::class)->orderBy('published_at')
            ->allowEmptySearchQuery()
            ->get();

        $this->assertCount(4, $results);
        $this->assertTrue(ExtendedSearcher::$searchWasCalled);
    }
}
