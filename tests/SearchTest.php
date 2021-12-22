<?php

namespace ProtoneMedia\LaravelCrossEloquentSearch\Tests;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ProtoneMedia\LaravelCrossEloquentSearch\OrderByRelevanceException;
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

    /** @test */
    public function it_can_search_in_multiple_columns()
    {
        $postA  = Post::create(['title' => 'foo']);
        $postB  = Post::create(['title' => 'bar']);
        $videoA = Video::create(['title' => 'foo']);
        $videoB = Video::create(['title' => 'bar', 'subtitle' => 'foo']);

        $results = Search::add(Post::class, 'title')
            ->add(Video::class, ['title', 'subtitle'])
            ->search('foo');

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
            ->search('"bar bar"');

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
            ->search('bar bar');

        $this->assertCount(1, $results);

        $this->assertTrue($results->contains($postB));
    }

    /** @test */
    public function it_has_an_option_to_ignore_the_case()
    {
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
            ->search();

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
            ->search('foo');

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
        ])->search('foo');

        $this->assertCount(2, $results);

        $this->assertTrue($results->contains($videoA));
        $this->assertTrue($results->contains($videoB));
    }

    /** @test */
    public function it_can_search_on_the_left_side_of_the_term()
    {
        Video::create(['title' => 'foo']);

        $this->assertCount(1, Search::add(Video::class, 'title')->search('fo'));
        $this->assertCount(0, Search::add(Video::class, 'title')->endWithWildcard(false)->search('fo'));
    }

    /** @test */
    public function it_can_use_the_sounds_like_operator()
    {
        Video::create(['title' => 'laravel']);

        $this->assertCount(0, Search::add(Video::class, 'title')->search('larafel'));
        $this->assertCount(1, Search::add(Video::class, 'title')->soundsLike()->search('larafel'));
    }

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
            ->search('foo');

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
            ->search('foo');

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

        $results = $results->map(fn ($model) => class_basename($model) . $model->getKey());

        $this->assertTrue($results->contains('Video1'));    // because foo1
        $this->assertTrue($results->contains('Video2'));    // because comment4
        $this->assertTrue($results->contains('Post1'));     // because foo2
        $this->assertTrue($results->contains('Post4'));     // because comment4
    }

    /** @test */
    public function it_can_sort_by_model_order()
    {
        $post    = Post::create(['title' => 'foo']);
        $comment = $post->comments()->create(['body' => 'foo']);
        $video   = Video::create(['title' => 'foo']);

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

    /** @test */
    public function it_respects_the_regular_order_when_ordering_by_model_type()
    {
        $postA  = Post::create(['title' => 'foo', 'published_at' => now()->addDays(4)]);
        $postB  = Post::create(['title' => 'foo', 'published_at' => now()->addDays(3)]);
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

    /** @test */
    public function it_cant_order_by_relevance_when_searching_through_nested_relationships()
    {
        $video = Video::create(['title' => 'foo']);
        $post  = $video->posts()->create(['title' => 'bar']);

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

    /** @test */
    public function it_uses_length_aware_paginator_by_default()
    {
        $search = Search::add(Post::class, 'title', 'published_at')
            ->add(Video::class, 'title', 'published_at')
            ->orderByDesc();

        $results = $search->paginate()->search('foo');

        $this->assertInstanceOf(LengthAwarePaginator::class, $results);
    }

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
            ->get('ba');

        $this->assertEquals($search->toArray()['data'][0]['type'], class_basename(Post::class));
        $this->assertEquals($search->toArray()['data'][1]['type'], class_basename(Video::class));
    }
}
