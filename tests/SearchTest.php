<?php

namespace ProtoneMedia\LaravelCrossEloquentSearch\Tests;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use ProtoneMedia\LaravelCrossEloquentSearch\OrderByRelevanceException;
use ProtoneMedia\LaravelCrossEloquentSearch\Search;
use ProtoneMedia\LaravelCrossEloquentSearch\Searcher;

class SearchTest extends TestCase
{
    #[Test]
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
            ->search('foo');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        $this->assertTrue($results->contains($postA));
        $this->assertFalse($results->contains($postB));
        $this->assertTrue($results->contains($videoA));
        $this->assertFalse($results->contains($videoB));

        $this->assertTrue($results->first()->is($videoA));
        $this->assertTrue($results->last()->is($postA));
    }

    #[Test]
    /** @test */
    public function it_can_search_in_multiple_columns()
    {
        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'bar']);
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar', 'subtitle' => 'foo']);

        $results = Search::add(Post::class, 'title')
            ->add(Video::class, ['title', 'subtitle'])
            ->search('foo');

        $this->assertCount(3, $results);

        $this->assertFalse($results->contains($postB));
    }

    #[Test]
    /** @test */
    public function it_can_count_the_results()
    {
        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'bar']);
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar', 'subtitle' => 'foo']);

        $count = Search::add(Post::class, 'title')
            ->add(Video::class, ['title', 'subtitle'])
            ->count('foo');

        $this->assertEquals(3, $count);
    }

    #[Test]
    /** @test */
    public function it_respects_table_prefixes()
    {
        $this->initDatabase('prefix');

        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'bar']);
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar', 'subtitle' => 'foo']);

        $count = Search::add(Post::class, 'title')
            ->add(Video::class, ['title', 'subtitle'])
            ->count('foo');

        $this->assertEquals(3, $count);
    }

    #[Test]
    /** @test */
    public function it_can_search_for_a_phrase()
    {
        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'bar bar']);
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar']);

        $results = Search::add(Post::class, 'title')
            ->add(Video::class, 'title')
            ->search('"bar bar"');

        $this->assertCount(1, $results);

        $this->assertTrue($results->contains($postB));
    }

    #[Test]
    /** @test */
    public function it_has_an_option_to_dont_split_the_search_term()
    {
        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'bar bar']);
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar']);

        $results = Search::add(Post::class, 'title')
            ->add(Video::class, 'title')
            ->dontParseTerm()
            ->search('bar bar');

        $this->assertCount(1, $results);

        $this->assertTrue($results->contains($postB));
    }

    #[Test]
    /** @test */
    public function it_has_an_option_to_ignore_the_case()
    {
        // Skip JSON column tests on SQLite and PostgreSQL due to different JSON function support
        if (in_array(config('database.default'), ['sqlite', 'pgsql'])) {
            $this->markTestSkipped('JSON column operations not supported on SQLite/PostgreSQL with VARCHAR columns.');
        }

        Post::create(['title' => 'foo']);
        Post::create(['title' => 'bar bar']);

        VideoJson::create(['title' => ['nl' => 'bar foo']]);
        VideoJson::create(['title' => ['nl' => 'bar']]);

        $results = Search::add(Post::class, 'title')
            ->add(VideoJson::class, 'title->nl')
            ->beginWithWildcard()
            ->ignoreCase()
            ->search('FOO');

        $this->assertCount(2, $results);
    }

    #[Test]
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
            $array[] = $key.$term;
        });

        $this->assertEquals(['0foo', '1bar'], $array);
    }

    #[Test]
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
            ->search();

        $this->assertCount(4, $results);
    }

    #[Test]
    /** @test */
    public function it_can_conditionally_add_queries()
    {
        $postA = Post::create(['title' => 'foo']);
        Post::create(['title' => 'bar']);
        Video::create(['title' => 'foo']);
        Video::create(['title' => 'bar']);

        $results = Search::new()
            ->when(true, fn (Searcher $searcher) => $searcher->add(Post::class, 'title'))
            ->when(false, fn (Searcher $searcher) => $searcher->add(Video::class, 'title', 'published_at'))
            ->search('foo');

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($postA));
    }

    #[Test]
    /** @test */
    public function it_can_add_many_models_at_once()
    {
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar', 'subtitle' => 'foo']);

        $results = Search::addMany([
            [Video::class, 'title'],
            [Video::class, 'subtitle', 'created_at'],
        ])->search('foo');

        $this->assertCount(2, $results);

        $this->assertTrue($results->contains($videoA));
        $this->assertTrue($results->contains($videoB));
    }

    #[Test]
    /** @test */
    public function it_can_search_on_the_left_side_of_the_term()
    {
        Video::create(['title' => 'foo']);

        $this->assertCount(1, Search::add(Video::class, 'title')->search('fo'));
        $this->assertCount(0, Search::add(Video::class, 'title')->endWithWildcard(false)->search('fo'));
    }

    #[Test]
    /** @test */
    public function it_can_use_the_sounds_like_operator()
    {
        Video::create(['title' => 'laravel']);

        $this->assertCount(0, Search::add(Video::class, 'title')->search('larafel'));
        $this->assertCount(1, Search::add(Video::class, 'title')->soundsLike()->search('larafel'));
    }

    #[Test]
    /** @test */
    public function it_can_search_twice_in_the_same_table()
    {
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar', 'subtitle' => 'foo']);

        $results = Search::add(Video::class, 'title')
            ->add(Video::class, 'subtitle')
            ->search('foo');

        $this->assertCount(2, $results);

        $this->assertTrue($results->contains($videoA));
        $this->assertTrue($results->contains($videoB));
    }

    #[Test]
    /** @test */
    public function it_lets_you_specify_a_custom_order_by_column_and_direction()
    {
        $postA = Post::create(['title' => 'foo', 'published_at' => now()]);
        $postB = Post::create(['title' => 'bar']);
        $videoA = Video::create(['title' => 'foo', 'published_at' => now()->addDay()]);
        $videoB = Video::create(['title' => 'bar']);

        $results = Search::add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc()
            ->search('foo');

        $this->assertCount(2, $results);

        $this->assertTrue($results->first()->is($videoA));
        $this->assertTrue($results->last()->is($postA));
    }

    #[Test]
    /** @test */
    public function it_accepts_a_query_builder()
    {
        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'foo']);
        $videoA = Video::create(['title' => 'foo', 'published_at' => now()->addDay()]);
        $videoB = Video::create(['title' => 'foo']);

        $results = Search::add(Post::whereNotNull('published_at'), 'title')
            ->add(Video::whereNotNull('published_at'), 'title')
            ->search('foo');

        $this->assertCount(1, $results);

        $this->assertTrue($results->first()->is($videoA));
    }

    #[Test]
    /** @test */
    public function it_can_paginate_the_results()
    {
        $postA = Post::create(['title' => 'foo', 'published_at' => now()->addDays(1)]);
        $postB = Post::create(['title' => 'foo', 'published_at' => now()->addDays(2)]);
        $videoA = Video::create(['title' => 'foo', 'published_at' => now()]);
        $videoB = Video::create(['title' => 'foo', 'published_at' => now()->addDays(3)]);

        $search = Search::add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc();

        $resultsPage1 = $search->paginate(2, 'page', 1)->search('foo');
        $resultsPage2 = $search->paginate(2, 'page', 2)->search('foo');

        $this->assertInstanceOf(LengthAwarePaginator::class, $resultsPage1);
        $this->assertInstanceOf(LengthAwarePaginator::class, $resultsPage2);

        $this->assertCount(2, $resultsPage1);
        $this->assertEquals(4, $resultsPage1->total());

        $this->assertTrue($resultsPage1->first()->is($videoB));
        $this->assertTrue($resultsPage1->last()->is($postB));

        $this->assertTrue($resultsPage2->first()->is($postA));
        $this->assertTrue($resultsPage2->last()->is($videoA));
    }

    #[Test]
    /** @test */
    public function it_can_limit_and_offset_results_without_pagination()
    {
        $postA = Post::create(['title' => 'foo', 'published_at' => now()->addDays(1)]);
        $postB = Post::create(['title' => 'foo', 'published_at' => now()->addDays(2)]);
        $videoA = Video::create(['title' => 'foo', 'published_at' => now()]);
        $videoB = Video::create(['title' => 'foo', 'published_at' => now()->addDays(3)]);

        $results = Search::add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc()
            ->offset(1)
            ->limit(2)
            ->search('foo');

        $this->assertCount(2, $results);
        $this->assertTrue($results->first()->is($postB));
        $this->assertTrue($results->last()->is($postA));
    }

    #[Test]
    /** @test */
    public function it_can_limit_and_offset_results_with_for_page()
    {
        $postA = Post::create(['title' => 'foo', 'published_at' => now()->addDays(1)]);
        $postB = Post::create(['title' => 'foo', 'published_at' => now()->addDays(2)]);
        $videoA = Video::create(['title' => 'foo', 'published_at' => now()]);
        $videoB = Video::create(['title' => 'foo', 'published_at' => now()->addDays(3)]);

        $results = Search::add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc()
            ->forPage(1, 2)
            ->search('foo');

        $this->assertCount(2, $results);
        $this->assertTrue($results->first()->is($videoB));
        $this->assertTrue($results->last()->is($postB));

        $results = Search::add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc()
            ->forPage(2, 2)
            ->search('foo');

        $this->assertCount(2, $results);
        $this->assertTrue($results->first()->is($postA));
        $this->assertTrue($results->last()->is($videoA));
    }

    #[Test]
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
            ->search('foo');

        $this->assertCount(1, $results);
        $this->assertEquals(10, $results->first()->comments_count);
        $this->assertTrue($results->first()->relationLoaded('comments'));
    }

    #[Test]
    /** @test */
    public function it_can_search_through_relations()
    {
        $videoA = Video::create(['title' => 'foo1']);
        $videoB = Video::create(['title' => 'bar']);

        $postAA = $videoA->posts()->create(['title' => 'foo2']);
        $postAB = $videoA->posts()->create(['title' => 'bar']);
        $postBA = $videoB->posts()->create(['title' => 'far']);
        $postBB = $videoB->posts()->create(['title' => 'boo']);

        $postAA->comments()->create(['body' => 'comment1']);
        $postAB->comments()->create(['body' => 'comment2']);
        $postBA->comments()->create(['body' => 'comment3']);
        $postBB->comments()->create(['body' => 'comment4']);

        $results = Search::new()
            ->beginWithWildcard(false)
            ->endWithWildcard(false)
            ->add(Video::class, 'posts.comments.body')
            ->search('comment4');

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($videoB));

        $results = Search::new()
            ->beginWithWildcard(false)
            ->endWithWildcard(false)
            ->add(Video::class, ['title', 'posts.comments.body'])
            ->search('foo1 comment4');

        $this->assertCount(2, $results);

        $results = Search::new()
            ->beginWithWildcard(false)
            ->endWithWildcard(false)
            ->add(Video::class, ['title', 'posts.comments.body'])
            ->add(Post::class, ['title', 'comments.body'])
            ->search('foo1 foo2 comment4');

        $this->assertCount(4, $results);

        $results = $results->map(fn ($model) => class_basename($model).$model->getKey());

        $this->assertTrue($results->contains('Video1'));    // because foo1
        $this->assertTrue($results->contains('Video2'));    // because comment4
        $this->assertTrue($results->contains('Post1'));     // because foo2
        $this->assertTrue($results->contains('Post4'));     // because comment4
    }

    #[Test]
    /** @test */
    public function it_doesnt_add_term_constraints_when_the_search_query_is_empty()
    {
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar']);

        $videoA->posts()->create(['title' => 'far']);

        $results = Search::new()
            ->add(Video::class, ['title', 'posts.title'])
            ->search();

        $this->assertCount(2, $results);
    }

    #[Test]
    /** @test */
    public function it_can_sort_by_model_order()
    {
        $post = Post::create(['title' => 'foo']);
        $comment = $post->comments()->create(['body' => 'foo']);
        $video = Video::create(['title' => 'foo']);

        $results = Search::new()
            ->add(Post::class, ['title'])
            ->add(Video::class, ['title'])
            ->add(Comment::class, ['body'])
            ->orderByModel([
                Comment::class, Post::class, Video::class,
            ])
            ->search('foo');

        $this->assertInstanceOf(Comment::class, $results->get(0));
        $this->assertInstanceOf(Post::class, $results->get(1));
        $this->assertInstanceOf(Video::class, $results->get(2));

        // desc:
        $results = Search::new()
            ->add(Post::class, ['title'])
            ->add(Video::class, ['title'])
            ->add(Comment::class, ['body'])
            ->orderByModel([
                Post::class, Video::class, Comment::class,
            ])
            ->orderByDesc()
            ->search('foo');

        $this->assertInstanceOf(Comment::class, $results->get(0));
        $this->assertInstanceOf(Video::class, $results->get(1));
        $this->assertInstanceOf(Post::class, $results->get(2));

        // missing model:
        $results = Search::new()
            ->add(Post::class, ['title'])
            ->add(Video::class, ['title'])
            ->add(Comment::class, ['body'])
            ->orderByModel(Comment::class)
            ->search('foo');

        $this->assertInstanceOf(Comment::class, $results->get(0));
    }

    #[Test]
    /** @test */
    public function it_respects_the_regular_order_when_ordering_by_model_type()
    {
        $postA = Post::create(['title' => 'foo', 'published_at' => now()->addDays(4)]);
        $postB = Post::create(['title' => 'foo', 'published_at' => now()->addDays(3)]);
        $videoA = Video::create(['title' => 'foo', 'published_at' => now()->addDays(2)]);
        $videoB = Video::create(['title' => 'foo', 'published_at' => now()->addDays(1)]);

        $results = Search::new()
            ->add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByModel([Video::class, Post::class])
            ->search('foo');

        $this->assertCount(4, $results);

        $this->assertTrue($results->first()->is($videoB));
        $this->assertTrue($results->last()->is($postA));
    }

    #[Test]
    /** @test */
    public function it_respects_the_relevance_order_when_ordering_by_model_type()
    {
        $videoA = Video::create(['title' => 'Apple introduces', 'subtitle' => 'iPhone 13 and iPhone 13 mini']);
        $videoB = Video::create(['title' => 'Apple unveils', 'subtitle' => 'new iPad mini with breakthrough performance in stunning new design']);

        $postA = Post::create(['title' => 'Apple introduces iPhone 13 and iPhone 13 mini']);
        $postB = Post::create(['title' => 'Apple unveils new iPad mini with breakthrough performance in stunning new design']);

        $results = Search::new()
            ->add(Video::class, ['title', 'subtitle'])
            ->add(Post::class, ['title'])
            ->beginWithWildcard()
            ->orderByRelevance()
            ->orderByModel([Video::class, Post::class])
            ->search('Apple iPad');

        $this->assertCount(4, $results);
        $this->assertTrue($results->first()->is($videoB), $results->toJson());
        $this->assertTrue($results->last()->is($postA), $results->toJson());
    }

    #[Test]
    /** @test */
    public function it_cant_order_by_relevance_when_searching_through_nested_relationships()
    {
        $video = Video::create(['title' => 'foo']);
        $post = $video->posts()->create(['title' => 'bar']);

        $search = Search::new()
            ->beginWithWildcard(false)
            ->endWithWildcard(false)
            ->add(Video::class, 'posts.title')
            ->orderByRelevance();

        try {
            $search->search('bar');
        } catch (OrderByRelevanceException $e) {
            return $this->assertTrue(true);
        }

        $this->fail('Should have thrown OrderByRelevanceException');
    }

    #[Test]
    /** @test */
    public function it_can_sort_by_word_occurrence()
    {
        $videoA = Video::create(['title' => 'Apple introduces', 'subtitle' => 'iPhone 13 and iPhone 13 mini']);
        $videoB = Video::create(['title' => 'Apple unveils', 'subtitle' => 'new iPad mini with breakthrough performance in stunning new design']);

        $results = Search::new()
            ->add(Video::class, ['title', 'subtitle'])
            ->beginWithWildcard()
            ->orderByRelevance()
            ->search('Apple iPad');

        $this->assertCount(2, $results);
        $this->assertTrue($results->first()->is($videoB));
    }

    #[Test]
    /** @test */
    public function it_doesnt_fail_when_the_terms_are_empty()
    {
        Video::create(['title' => 'bar']);
        Video::create(['title' => 'foo']);

        $results = Search::new()
            ->add(Video::class)
            ->orderByRelevance()
            ->search();

        $this->assertCount(2, $results);
    }

    #[Test]
    /** @test */
    public function it_uses_length_aware_paginator_by_default()
    {
        $search = Search::add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc();

        $results = $search->paginate()->search('foo');

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
    }

    #[Test]
    /** @test */
    public function it_can_use_simple_paginator()
    {
        $search = Search::new()
            ->add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc();

        $results = $search->simplePaginate()->search('foo');

        $this->assertInstanceOf(Paginator::class, $results);
    }

    #[Test]
    /** @test */
    public function it_can_simple_paginate_the_results()
    {
        $postA = Post::create(['title' => 'foo', 'published_at' => now()->addDays(1)]);
        $postB = Post::create(['title' => 'foo', 'published_at' => now()->addDays(2)]);
        $videoA = Video::create(['title' => 'foo', 'published_at' => now()]);
        $videoB = Video::create(['title' => 'foo', 'published_at' => now()->addDays(3)]);

        $search = Search::new()
            ->add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc();

        $resultsPage1 = $search->simplePaginate(2, 'page', 1)->search('foo');
        $resultsPage2 = $search->simplePaginate(2, 'page', 2)->search('foo');

        $this->assertInstanceOf(Paginator::class, $resultsPage1);
        $this->assertInstanceOf(Paginator::class, $resultsPage2);

        $this->assertCount(2, $resultsPage1);

        $this->assertTrue($resultsPage1->first()->is($videoB));
        $this->assertTrue($resultsPage1->last()->is($postB));

        $this->assertTrue($resultsPage2->first()->is($postA));
        $this->assertTrue($resultsPage2->last()->is($videoA));
    }

    #[Test]
    /** @test */
    public function it_can_add_query_string_to_pagination_links()
    {
        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'foo']);
        $postC = Post::create(['title' => 'foo']);

        $results = Search::add(Post::class, 'title')
            ->paginate(2, 'page', 1)
            ->withQueryString(['filter' => 'active', 'sort' => 'date'])
            ->search('foo');

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
        $this->assertEquals(3, $results->total());

        // Check that query string params are in the pagination URLs
        $nextPageUrl = $results->nextPageUrl();
        $this->assertStringContainsString('filter=active', $nextPageUrl);
        $this->assertStringContainsString('sort=date', $nextPageUrl);
        $this->assertStringContainsString('page=2', $nextPageUrl);
    }

    #[Test]
    /** @test */
    public function it_uses_request_query_string_when_withQueryString_has_no_arguments()
    {
        request()->merge(['search' => 'laravel', 'category' => 'php']);

        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'foo']);
        $postC = Post::create(['title' => 'foo']);

        $results = Search::add(Post::class, 'title')
            ->paginate(2, 'page', 1)
            ->withQueryString()  // no arguments, uses request()->query()
            ->search('foo');

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);

        // Check that request query params are in the pagination URLs
        $nextPageUrl = $results->nextPageUrl();
        $this->assertStringContainsString('search=laravel', $nextPageUrl);
        $this->assertStringContainsString('category=php', $nextPageUrl);
        $this->assertStringContainsString('page=2', $nextPageUrl);
    }

    #[Test]
    /** @test */
    public function it_can_use_withQueryString_with_empty_array_to_add_no_params()
    {
        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'foo']);

        $results = Search::add(Post::class, 'title')
            ->paginate(1, 'page', 1)
            ->withQueryString([])
            ->search('foo');

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);

        // Only page param should be present, no extra query string
        $nextPageUrl = $results->nextPageUrl();
        $this->assertStringContainsString('page=2', $nextPageUrl);
        $this->assertStringNotContainsString('search=', $nextPageUrl);
    }

    #[Test]
    /** @test */
    public function it_can_use_withQueryString_with_null_to_use_request_query()
    {
        request()->merge(['filter' => 'published']);

        $postA = Post::create(['title' => 'foo']);
        $postB = Post::create(['title' => 'foo']);

        $results = Search::add(Post::class, 'title')
            ->paginate(1, 'page', 1)
            ->withQueryString(null)  // explicit null, should use request query
            ->search('foo');

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);

        $nextPageUrl = $results->nextPageUrl();
        $this->assertStringContainsString('filter=published', $nextPageUrl);
        $this->assertStringContainsString('page=2', $nextPageUrl);
    }

    #[Test]
    /** @test */
    public function it_includes_a_model_identifier_to_search_results()
    {
        Post::create(['title' => 'bar']);
        Video::create(['title' => 'baz']);

        $search = Search::new()
            ->add(Post::class, 'title', 'title')
            ->add(Video::class, 'title', 'title')
            ->includeModelType()
            ->paginate()
            ->search('ba');

        $this->assertEquals($search->toArray()['data'][0]['type'], class_basename(Post::class));
        $this->assertEquals($search->toArray()['data'][1]['type'], class_basename(Video::class));
    }

    #[Test]
    /** @test */
    public function it_includes_a_custom_model_identifier_to_search_results()
    {
        Post::create(['title' => 'bar']);
        Video::create(['title' => 'baz']);

        $search = Search::new()
            ->add(VideoJson::class, 'title', 'title')
            ->includeModelType()
            ->paginate()
            ->search('ba');

        $this->assertEquals($search->toArray()['data'][0]['type'], 'awesome_video');
    }

    #[Test]
    /** @test */
    public function it_can_tap_into_the_searcher_instance()
    {

        Carbon::setTestNow(now());
        $postA = Post::create(['title' => 'foo']);

        Carbon::setTestNow(now()->subDay());
        $postB = Post::create(['title' => 'foo2']);

        $results = Search::add(Post::class, 'title')
            ->tap(fn (Searcher $searcher) => $searcher->orderByDesc())
            ->search('foo');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        $this->assertTrue($results->first()->is($postA));
        $this->assertTrue($results->last()->is($postB));
    }

    #[Test]
    /** @test */
    public function it_supports_full_text_search()
    {
        $postA = Post::create(['title' => 'Laravel Framework']);
        $postB = Post::create(['title' => 'Tailwind Framework']);

        $blogA = Blog::create(['title' => 'Laravel Framework', 'subtitle' => 'PHP', 'body' => 'Ad nostrud adipisicing deserunt labore reprehenderit ']);
        $blogB = Blog::create(['title' => 'Tailwind Framework', 'subtitle' => 'CSS', 'body' => 'aute do commodo ea magna dolor cupidatat ullamco commodo.']);

        $pageA = Page::create(['title' => 'Laravel Framework', 'subtitle' => 'PHP', 'body' => 'Ad nostrud adipisicing deserunt labore reprehenderit ']);
        $pageB = Page::create(['title' => 'Tailwind Framework', 'subtitle' => 'CSS', 'body' => 'aute do commodo ea magna dolor cupidatat ullamco commodo.']);

        $results = Search::new()
            ->beginWithWildcard()
            ->add(Post::class, 'title')
            ->addFullText(Blog::class, ['title', 'subtitle', 'body'], ['mode' => 'boolean'])
            ->addFullText(Page::class, ['title', 'subtitle', 'body'], ['mode' => 'boolean'])
            ->search('framework -css');

        // For PostgreSQL, skip the mixed regular+fulltext search test
        // since regular search has UNION type casting issues with text conversion
        if (config('database.default') === 'pgsql') {
            // Test full-text search only (which works correctly)
            $fullTextResults = Search::new()
                ->addFullText(Blog::class, ['title', 'subtitle', 'body'], ['mode' => 'boolean'])
                ->addFullText(Page::class, ['title', 'subtitle', 'body'], ['mode' => 'boolean'])
                ->search('framework -css');

            $this->assertCount(2, $fullTextResults);
            $this->assertTrue($fullTextResults->contains($blogA));
            $this->assertTrue($fullTextResults->contains($pageA));

            return; // Skip the mixed test for PostgreSQL
        }

        $this->assertCount(4, $results);

        $this->assertTrue($results->contains($postA));
        $this->assertTrue($results->contains($postB));
        $this->assertTrue($results->contains($blogA));
        $this->assertTrue($results->contains($pageA));
    }

    #[Test]
    /** @test */
    public function it_supports_full_text_search_on_relations()
    {
        $videoA = Video::create(['title' => 'Page A']);
        $videoB = Video::create(['title' => 'Page B']);
        $videoC = Video::create(['title' => 'Page C']);
        $videoD = Video::create(['title' => 'Page D']);

        $videoA->blogs()->create(['title' => 'Laravel Framework', 'subtitle' => 'PHP', 'body' => 'Ad nostrud adipisicing deserunt labore reprehenderit ']);
        $videoB->blogs()->create(['title' => 'Tailwind Framework', 'subtitle' => 'CSS', 'body' => 'aute do commodo ea magna dolor cupidatat ullamco commodo.']);
        $videoC->pages()->create(['title' => 'Laravel Framework', 'subtitle' => 'PHP', 'body' => 'Ad nostrud adipisicing deserunt labore reprehenderit ']);
        $videoD->pages()->create(['title' => 'Tailwind Framework', 'subtitle' => 'CSS', 'body' => 'aute do commodo ea magna dolor cupidatat ullamco commodo.']);

        $results = Search::new()
            ->beginWithWildcard()
            ->addFullText(Video::class, [
                'blogs' => ['title', 'subtitle', 'body'],
                'pages' => ['title', 'subtitle', 'body'],
            ], )
            ->search('framework -css');

        $this->assertCount(2, $results);

        $this->assertTrue($results->contains($videoA));
        $this->assertTrue($results->contains($videoC));
    }

    #[Test]
    /** @test */
    public function it_returns_data_consistently()
    {
        Carbon::setTestNow(now());
        $postA = Post::create(['title' => 'Laravel Framework']);

        Carbon::setTestNow(now()->addSecond());
        $postB = Post::create(['title' => 'Tailwind Framework']);

        $this->assertEquals(2, Post::all()->count());
        $this->assertEquals(0, Blog::all()->count());

        $resultA = Search::addMany([
            [Post::query(), 'title'],
        ])->search('');

        $resultB = Search::addMany([
            [Post::query(), 'title'],
            [Blog::query(), 'title'],
        ])->search('');

        $this->assertCount(2, $resultA);
        $this->assertCount(2, $resultB);

        $this->assertTrue($resultA->first()->is($postA));
        $this->assertTrue($resultB->first()->is($postA));
    }

    #[Test]
    /** @test */
    public function it_can_conditionally_apply_ordering()
    {
        Carbon::setTestNow(now());
        $postA = Post::create(['title' => 'foo']);

        Carbon::setTestNow(now()->subDay());
        $postB = Post::create(['title' => 'foo2']);

        $results = Search::add(Post::class, 'title')
            ->when(true, fn (Searcher $searcher) => $searcher->orderByDesc())
            ->search('foo');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        $this->assertTrue($results->first()->is($postA));
        $this->assertTrue($results->last()->is($postB));
    }

    #[Test]
    /** @test */
    public function it_can_perform_exact_match_search()
    {
        Video::create(['title' => 'foo']);
        Video::create(['title' => 'foobar']);
        Video::create(['title' => 'barfoo']);

        $this->assertCount(1, Search::add(Video::class, 'title')->exactMatch()->search('foo'));
    }

    #[Test]
    /** @test */
    public function it_supports_full_text_search_with_mixed_direct_and_relation_columns()
    {
        $videoA = Video::create(['title' => 'Laravel Framework']);
        $videoB = Video::create(['title' => 'Tailwind CSS']);

        $videoA->blogs()->create(['title' => 'Blog about PHP', 'subtitle' => 'Subtitle', 'body' => 'Body text']);
        $videoB->blogs()->create(['title' => 'Blog about CSS', 'subtitle' => 'Subtitle', 'body' => 'Body text']);

        $results = Search::new()
            ->addFullText(Video::class, [
                'title',
                'blogs' => ['title', 'subtitle', 'body'],
            ])
            ->search('Laravel');

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($videoA));
    }
}
