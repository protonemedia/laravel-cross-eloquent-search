<?php

namespace ProtoneMedia\LaravelCrossEloquentSearch\Tests;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        $results = Search::add(Post::class, 'title')
            ->add(Video::class, 'title')
            ->get('foo');

        $this->assertInstanceOf(Collection::class, $results);
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

        $results = Search::add(Post::class, 'title')
            ->add(Video::class, ['title', 'subtitle'])
            ->get('foo');

        $this->assertCount(3, $results);

        $this->assertFalse($results->contains($postB));
    }

    /** @test */
    public function it_can_count_the_results()
    {
        $postA  = Post::create(['title' => 'foo']);
        $postB  = Post::create(['title' => 'bar']);
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar', 'subtitle' => 'foo']);

        $count = Search::add(Post::class, 'title')
            ->add(Video::class, ['title', 'subtitle'])
            ->count('foo');

        $this->assertEquals(3, $count);
    }

    /** @test */
    public function it_respects_table_prefixes()
    {
        $this->initDatabase('prefix');

        $postA  = Post::create(['title' => 'foo']);
        $postB  = Post::create(['title' => 'bar']);
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar', 'subtitle' => 'foo']);

        $count = Search::add(Post::class, 'title')
            ->add(Video::class, ['title', 'subtitle'])
            ->count('foo');

        $this->assertEquals(3, $count);
    }

    /** @test */
    public function it_can_search_for_a_phrase()
    {
        $postA  = Post::create(['title' => 'foo']);
        $postB  = Post::create(['title' => 'bar bar']);
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar']);

        $results = Search::add(Post::class, 'title')
            ->add(Video::class, 'title')
            ->get('"bar bar"');

        $this->assertCount(1, $results);

        $this->assertTrue($results->contains($postB));
    }

    /** @test */
    public function it_has_an_option_to_dont_split_the_search_term()
    {
        $postA  = Post::create(['title' => 'foo']);
        $postB  = Post::create(['title' => 'bar bar']);
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar']);

        $results = Search::add(Post::class, 'title')
            ->add(Video::class, 'title')
            ->dontParseTerm()
            ->get('bar bar');

        $this->assertCount(1, $results);

        $this->assertTrue($results->contains($postB));
    }

    /** @test */
    public function it_has_an_option_to_ignore_the_case()
    {
        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'bar bar']);

        $videoA = new Video;
        $videoB = new Video;

        $videoA->mergeCasts(['title' => 'json']);
        $videoB->mergeCasts(['title' => 'json']);

        $videoA->forceFill(['title' => ['nl' => 'bar foo']])->save();
        $videoB->forceFill(['title' => ['nl' => 'bar']])->save();

        $results = Search::add(Post::class, 'title')
            ->add(Video::class, 'title->nl')
            ->beginWithWildcard()
            ->ignoreCase()
            ->get('FOO');

        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_has_a_method_to_parse_the_terms()
    {
        $this->assertEquals(['foo'], Search::parseTerms('foo')->all());
        $this->assertEquals(['foo'], Search::parseTerms('foo ')->all());
        $this->assertEquals(['foo'], Search::parseTerms(' foo ')->all());
        $this->assertEquals(['foo', 'bar'], Search::parseTerms('foo bar')->all());
        $this->assertEquals(['foo bar'], Search::parseTerms('"foo bar"')->all());

        $array = [];

        Search::parseTerms('foo bar', function ($term, $key) use (&$array) {
            $array[] = $key . $term;
        });

        $this->assertEquals(['0foo','1bar'], $array);
    }

    /** @test */
    public function it_can_search_without_a_term()
    {
        Post::create(['title' => 'foo']);
        Post::create(['title' => 'bar']);
        Video::create(['title' => 'foo']);
        Video::create(['title' => 'bar']);

        $results = Search::new()
            ->add(Post::class)->orderBy('updated_at')
            ->add(Video::class)->orderBy('published_at')
            ->get();

        $this->assertCount(4, $results);
    }

    /** @test */
    public function it_can_conditionally_add_queries()
    {
        $postA = Post::create(['title' => 'foo']);
        Post::create(['title' => 'bar']);
        Video::create(['title' => 'foo']);
        Video::create(['title' => 'bar']);

        $results = Search::new()
            ->addWhen(true, Post::class, 'title')
            ->addWhen(false, Video::class, 'title', 'published_at')
            ->get('foo');

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($postA));
    }

    /** @test */
    public function it_can_add_many_models_at_once()
    {
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar', 'subtitle' => 'foo']);

        $results = Search::addMany([
            [Video::class, 'title'],
            [Video::class, 'subtitle', 'created_at'],
        ])->get('foo');

        $this->assertCount(2, $results);

        $this->assertTrue($results->contains($videoA));
        $this->assertTrue($results->contains($videoB));
    }

    /** @test */
    public function it_can_search_on_the_left_side_of_the_term()
    {
        Video::create(['title' => 'foo']);

        $this->assertCount(1, Search::add(Video::class, 'title')->get('fo'));
        $this->assertCount(0, Search::add(Video::class, 'title')->endWithWildcard(false)->get('fo'));
    }

    /** @test */
    public function it_can_use_the_sounds_like_operator()
    {
        Video::create(['title' => 'laravel']);

        $this->assertCount(0, Search::add(Video::class, 'title')->get('larafel'));
        $this->assertCount(1, Search::add(Video::class, 'title')->soundsLike()->get('larafel'));
    }

    /** @test */
    public function it_can_search_twice_in_the_same_table()
    {
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar', 'subtitle' => 'foo']);

        $results = Search::add(Video::class, 'title')
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

        $results = Search::add(Post::class, 'title', 'published_at')
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

        $results = Search::add(Post::whereNotNull('published_at'), 'title')
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

        $search = Search::add(Post::class, 'title', 'published_at')
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

        $results = Search::add(Post::with('comments')->withCount('comments'), 'title')
            ->get('foo');

        $this->assertCount(1, $results);
        $this->assertEquals(10, $results->first()->comments_count);
        $this->assertTrue($results->first()->relationLoaded('comments'));
    }

    /** @test */
    public function it_can_sort_by_word_occurrence()
    {
        $videoA = Video::create(['title' => 'Apple introduces', 'subtitle' => 'iPhone 13 and iPhone 13 mini']);
        $videoB = Video::create(['title' => 'Apple unveils', 'subtitle' => 'new iPad mini with breakthrough performance in stunning new design']);

        $results = Search::new()
            ->add(Video::class, ['title', 'subtitle'])
            ->beginWithWildcard()
            ->orderByRelevance()
            ->get('Apple iPad');

        $this->assertCount(2, $results);
        $this->assertTrue($results->first()->is($videoB));
    }

    /** @test */
    public function it_doesnt_fail_when_the_terms_are_empty()
    {
        Video::create(['title' => 'bar']);
        Video::create(['title' => 'foo']);

        $results = Search::new()
            ->add(Video::class)
            ->orderByRelevance()
            ->get();

        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_uses_length_aware_paginator_by_default()
    {
        $search = Search::add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc();

        $results = $search->paginate()->get('foo');

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
    }

    /** @test */
    public function it_can_use_simple_paginator()
    {
        $search = Search::new()
            ->add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc();

        $results = $search->simplePaginate()->get('foo');

        $this->assertInstanceOf(Paginator::class, $results);
    }

    /** @test */
    public function it_can_simple_paginate_the_results()
    {
        $postA  = Post::create(['title' => 'foo', 'published_at' => now()->addDays(1)]);
        $postB  = Post::create(['title' => 'foo', 'published_at' => now()->addDays(2)]);
        $videoA = Video::create(['title' => 'foo', 'published_at' => now()]);
        $videoB = Video::create(['title' => 'foo', 'published_at' => now()->addDays(3)]);

        $search = Search::new()
            ->add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc();

        $resultsPage1 = $search->simplePaginate(2, 'page', 1)->get('foo');
        $resultsPage2 = $search->simplePaginate(2, 'page', 2)->get('foo');

        $this->assertInstanceOf(Paginator::class, $resultsPage1);
        $this->assertInstanceOf(Paginator::class, $resultsPage2);

        $this->assertCount(2, $resultsPage1);

        $this->assertTrue($resultsPage1->first()->is($videoB));
        $this->assertTrue($resultsPage1->last()->is($postB));

        $this->assertTrue($resultsPage2->first()->is($postA));
        $this->assertTrue($resultsPage2->last()->is($videoA));
    }
}
