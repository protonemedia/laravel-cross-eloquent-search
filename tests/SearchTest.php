<?php

namespace ProtoneMedia\LaravelCrossEloquentSearch\Tests;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use ProtoneMedia\LaravelCrossEloquentSearch\EmptySearchQueryException;
use ProtoneMedia\LaravelCrossEloquentSearch\Search;

class SearchTest extends TestCase
{
    /** @test */
    public function it_can_search_two_models_and_orders_by_updated_at_by_default()
    {
        Carbon::setTestNow(now());

        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'bar']);

        Carbon::setTestNow(now()->subDay());

        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar']);

        $results = Search::new()
            ->add(Post::class, 'title')
            ->add(Video::class, 'title')
            ->get('foo');

        $this->assertCount(2, $results);

        $this->assertTrue($results->contains($postA));
        $this->assertFalse($results->contains($postB));
        $this->assertTrue($results->contains($videoA));
        $this->assertFalse($results->contains($videoB));

        $this->assertTrue($results->first()->is($videoA));
        $this->assertTrue($results->last()->is($postA));
    }

    /** @test */
    public function it_can_search_in_multiple_columns()
    {
        $postA  = Post::create(['title' => 'foo']);
        $postB  = Post::create(['title' => 'bar']);
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar', 'subtitle' => 'foo']);

        $results = Search::new()
            ->add(Post::class, 'title')
            ->add(Video::class, ['title', 'subtitle'])
            ->get('foo');

        $this->assertCount(3, $results);

        $this->assertFalse($results->contains($postB));
    }

    /** @test */
    public function it_throws_an_exception_when_the_query_is_empty()
    {
        try {
            Search::new()->get('');
        } catch (EmptySearchQueryException $exception) {
            return $this->assertTrue(true);
        }

        $this->fail('Should have thrown EmptySearchQueryException.');
    }

    /** @test */
    public function it_can_search_on_the_left_side_of_the_term()
    {
        Video::create(['title' => 'foo']);

        $this->assertCount(0, Search::new()->add(Video::class, 'title')->get('oo'));
        $this->assertCount(1, Search::new()->add(Video::class, 'title')->wildcardLeft()->get('oo'));
    }

    /** @test */
    public function it_can_search_twice_in_the_same_table()
    {
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar', 'subtitle' => 'foo']);

        $results = Search::new()
            ->add(Video::class, 'title')
            ->add(Video::class, 'subtitle')
            ->get('foo');

        $this->assertCount(2, $results);

        $this->assertTrue($results->contains($videoA));
        $this->assertTrue($results->contains($videoB));
    }

    /** @test */
    public function it_lets_you_specify_a_custom_order_by_column_and_direction()
    {
        $postA  = Post::create(['title' => 'foo', 'published_at' => now()]);
        $postB  = Post::create(['title' => 'bar']);
        $videoA = Video::create(['title' => 'foo', 'published_at' => now()->addDay()]);
        $videoB = Video::create(['title' => 'bar']);

        $results = Search::new()
            ->add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc()
            ->get('foo');

        $this->assertCount(2, $results);

        $this->assertTrue($results->first()->is($videoA));
        $this->assertTrue($results->last()->is($postA));
    }

    /** @test */
    public function it_accepts_a_query_builder()
    {
        $postA  = Post::create(['title' => 'foo']);
        $postB  = Post::create(['title' => 'foo']);
        $videoA = Video::create(['title' => 'foo', 'published_at' => now()->addDay()]);
        $videoB = Video::create(['title' => 'foo']);

        $results = Search::new()
            ->add(Post::whereNotNull('published_at'), 'title')
            ->add(Video::whereNotNull('published_at'), 'title')
            ->get('foo');

        $this->assertCount(1, $results);

        $this->assertTrue($results->first()->is($videoA));
    }

    /** @test */
    public function it_can_paginate_the_results()
    {
        $postA  = Post::create(['title' => 'foo', 'published_at' => now()->addDays(1)]);
        $postB  = Post::create(['title' => 'foo', 'published_at' => now()->addDays(2)]);
        $videoA = Video::create(['title' => 'foo', 'published_at' => now()]);
        $videoB = Video::create(['title' => 'foo', 'published_at' => now()->addDays(3)]);

        $search = Search::new()
            ->add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc();

        $resultsPage1 = $search->paginate(2, 'page', 1)->get('foo');
        $resultsPage2 = $search->paginate(2, 'page', 2)->get('foo');

        $this->assertInstanceOf(LengthAwarePaginator::class, $resultsPage1);
        $this->assertInstanceOf(LengthAwarePaginator::class, $resultsPage2);

        $this->assertCount(2, $resultsPage1);
        $this->assertEquals(4, $resultsPage1->total());

        $this->assertTrue($resultsPage1->first()->is($videoB));
        $this->assertTrue($resultsPage1->last()->is($postB));

        $this->assertTrue($resultsPage2->first()->is($postA));
        $this->assertTrue($resultsPage2->last()->is($videoA));
    }

    /** @test */
    public function it_can_eager_load_relations()
    {
        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'bar']);

        foreach (range(1, 10) as $i) {
            $postA->comments()->create(['body' => 'ok']);
            $postB->comments()->create(['body' => 'ok']);
        }

        $results = Search::new()
            ->add(Post::with('comments'), 'title')
            ->get('foo');

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->relationLoaded('comments'));
    }
}
