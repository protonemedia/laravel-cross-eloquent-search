# Laravel Cross Eloquent Search Reference

Complete reference for `protonemedia/laravel-cross-eloquent-search.`. Full documentation: https://github.com/protonemedia/laravel-cross-eloquent-search#readme

Reference for **using** `protonemedia/laravel-cross-eloquent-search` inside Laravel applications.

Primary docs (README): https://github.com/protonemedia/laravel-cross-eloquent-search#readme

## Purpose

Build a single “search” that can query **multiple Eloquent models** and return a combined result set (optionally paginated), while still letting you:

- Choose searchable columns (including related fields).
- Apply per-model constraints.
- Control ordering (by updated timestamp, custom columns, model priority, relevance).
- Use full-text or similarity strategies when supported by your database.

## Installation

```bash
composer require protonemedia/laravel-cross-eloquent-search
```

## Quick start (typical app integration)

### Basic global search (controller/service)

```php
use ProtoneMedia\LaravelCrossEloquentSearch\Search;

public function __invoke(Request $request)
{
    $term = (string) $request->query('q', '');

    $results = Search::new()
        ->add(Post::class, ['title', 'body'])
        ->add(Video::class, ['title', 'description'])
        ->paginate(15)
        ->withQueryString() // keep ?q=... while paging
        ->includeModelType() // helpful for API/UI rendering
        ->search($term);

    return view('search.results', [
        'term' => $term,
        'results' => $results,
    ]);
}
```

### Adding constrained builders (respect app rules)

Instead of a model class, pass an Eloquent builder:

```php
$results = Search::new()
    ->add(Post::query()->where('status', 'published'), ['title', 'body'])
    ->add(Video::query()->where('is_public', true), ['title', 'description'])
    ->paginate(20)
    ->search($term);
```

This is a clean way to incorporate authorization-ish or product rules (visibility, status, tenant scoping) into search.

## Choosing searchable fields

### Multiple columns

```php
Search::new()
    ->add(Post::class, ['title', 'body'])
    ->add(Video::class, ['title', 'subtitle'])
    ->search('eloquent');
```

### Searching through relationships (dot notation)

```php
Search::new()
    ->add(Post::class, ['comments.body', 'user.name'])
    ->add(Video::class, ['channel.name'])
    ->search('artisan');
```

Notes:
- Relationship paths can be nested.
- If you need model-specific eager loading, pass a builder: `Post::with('user')`.

## Term parsing and matching behavior

### Default parsing (keywords + trailing wildcard)

By default, the term is split into keywords and `term%` matching is used.

### Start/end wildcards

```php
Search::new()
    ->add(Post::class, 'title')
    ->beginWithWildcard()      // becomes %term%
    ->endWithWildcard(true)
    ->search('os');
```

Disable the trailing wildcard:

```php
Search::new()
    ->add(Post::class, 'title')
    ->endWithWildcard(false)   // becomes term (or %term when beginWithWildcard is on)
    ->search('laravel');
```

### Exact match

```php
Search::new()
    ->add(Post::class, 'slug')
    ->exactMatch()
    ->search('my-post-slug');
```

### Quoted phrases and disabling parsing

```php
Search::new()
    ->add(Post::class, ['title', 'body'])
    ->search('"macos big sur"');
```

Or disable parsing entirely:

```php
Search::new()
    ->add(Post::class, ['title', 'body'])
    ->dontParseTerm()
    ->search('macos big sur');
```

## Ordering and sorting

### Per-model order column

```php
Search::new()
    ->add(Post::class, 'title', 'published_at')
    ->add(Video::class, 'title', 'released_at')
    ->orderByDesc()
    ->search('learn');
```

### Order by model priority (useful for UX)

```php
Search::new()
    ->add(Comment::class, ['body'])
    ->add(Post::class, ['title'])
    ->add(Video::class, ['title', 'description'])
    ->orderByModel([
        Post::class,
        Video::class,
        Comment::class,
    ])
    ->search('Artisan School');
```

### Relevance ordering

```php
Search::new()
    ->add(Post::class, 'title')
    ->beginWithWildcard()
    ->orderByRelevance()
    ->search('Apple iPad');
```

Important limitation (README): `orderByRelevance()` is **not supported** when searching through (nested) relationships.

## Pagination

Call `paginate()` (or `simplePaginate()`) **before** `search()`:

```php
$paginator = Search::new()
    ->add(Post::class, ['title', 'body'])
    ->add(Video::class, ['title'])
    ->paginate(15)
    ->withQueryString()
    ->search($term);
```

## Full-text search (database-dependent)

Switch a model/columns to full-text behavior with `addFullText()`:

```php
Search::new()
    ->add(Post::class, 'title')
    ->addFullText(Video::class, 'title', ['mode' => 'boolean'])
    ->addFullText(Blog::class, ['title', 'subtitle', 'body'], ['mode' => 'boolean'])
    ->search('framework -css');
```

Full-text through relationships uses an associative structure:

```php
Search::new()
    ->addFullText(Page::class, [
        'posts' => ['title', 'body'],
        'sections' => ['title', 'subtitle', 'body'],
    ])
    ->search('framework -css');
```

Driver notes (high level):
- **MySQL/MariaDB:** requires full-text indexes on the searched columns.
- **PostgreSQL:** uses `tsquery`; similarity search may require `pg_trgm`.
- **SQLite:** often falls back to LIKE-based strategies; full-text may differ.

## Similarity search (“sounds like”)

```php
Search::new()
    ->add(Post::class, 'framework')
    ->add(Video::class, 'framework')
    ->soundsLike()
    ->search('larafel');
```

## Eager loading

If your UI/API needs relations, pass a builder so results come preloaded:

```php
Search::new()
    ->add(Post::with('user', 'comments'), 'title')
    ->add(Video::with('channel'), 'title')
    ->search('guitar');
```

## Returning results without a term

`search()` with no term (or empty) can be used to retrieve a combined feed:

```php
$feed = Search::new()
    ->add(Post::class)
    ->orderBy('published_at')
    ->add(Video::class)
    ->orderBy('released_at')
    ->paginate(20)
    ->search();
```

## Debugging & troubleshooting in apps

### Log executed queries

When results are missing or ordering is surprising, start by logging SQL:

```php
DB::listen(function ($query) {
    logger()->debug('SQL', [
        'sql' => $query->sql,
        'bindings' => $query->bindings,
        'time_ms' => $query->time,
    ]);
});
```

Common causes:
- Calling `paginate()` after `search()` (pagination must be configured first).
- Using `orderByRelevance()` together with relationship fields.
- Missing full-text indexes / missing Postgres extensions for similarity.
- Asserting on implicit default ordering (set an explicit order for deterministic behavior).

## Common pitfalls / gotchas

- **Default ordering:** by default, results are ordered by each model’s “updated” column (`getUpdatedAtColumn()`), falling back to primary key when timestamps are disabled.
- **Relevance + relationships:** `orderByRelevance()` doesn’t support relationship searches.
- **Full-text prerequisites:** add the proper indexes/extensions or you may get slow queries or poor matches.
- **Pagination call order:** configure pagination *before* calling `search()`.
- **Mixed model rendering:** use `includeModelType()` (and optionally a model `searchType()` method) to prevent UI/API ambiguity.

## Testing tips (application tests)

### Prefer deterministic fixtures + explicit ordering

```php
use ProtoneMedia\LaravelCrossEloquentSearch\Search;

public function test_it_returns_posts_and_videos_for_a_term(): void
{
    Post::factory()->create(['title' => 'Laravel tips']);
    Video::factory()->create(['title' => 'Laravel course']);

    $results = Search::new()
        ->add(Post::class, 'title')
        ->add(Video::class, 'title')
        ->includeModelType()
        ->orderByModel([Post::class, Video::class])
        ->search('laravel');

    $this->assertCount(2, $results);
    $this->assertSame('post', $results[0]->type ?? null);
}
```

### Assert pagination behavior

```php
$paginator = Search::new()
    ->add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->paginate(1)
    ->search('laravel');

$this->assertTrue($paginator->hasPages());
```

### Database choice matters

If your app uses MySQL/Postgres-specific full-text or similarity behavior in production, consider running those tests on the same driver in CI. SQLite can behave differently for text search.
