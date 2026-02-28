# Laravel Cross Eloquent Search Reference

Complete reference for `protonemedia/laravel-cross-eloquent-search`.

Primary docs: https://github.com/protonemedia/laravel-cross-eloquent-search#readme

## Purpose

Search through multiple Eloquent models in a single query-like API and return combined results.

Supported features (from README):

- Cross-model search across one or more models.
- Sorting, pagination (length-aware + simple), query-string retention.
- Searching across multiple columns and (nested) relationships.
- Full-text search strategies (DB-dependent), similarity (“sounds like”).
- Model-specific constraints via scoped queries / query builders.
- Eager loading per model.
- Optional relevance ordering.

## Installation

```bash
composer require protonemedia/laravel-cross-eloquent-search
```

## Core API

Use the `Search` facade:

```php
use ProtoneMedia\LaravelCrossEloquentSearch\Search;

$results = Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->search('howto');
```

If you prefer indentation / chaining clarity:

```php
Search::new()
    ->add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->search('howto');
```

### Conditional configuration (`when`)

```php
Search::new()
    ->when($user->isVerified(), fn ($search) => $search->add(Post::class, 'title'))
    ->when($user->isAdmin(), fn ($search) => $search->add(Video::class, 'title'))
    ->search('howto');
```

### Inspecting configuration (`tap`)

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->tap(function ($searcher) {
        logger()->info('Search models', ['models' => $searcher->getModelsToSearchThrough()]);
    })
    ->search('laravel');
```

## Term parsing and matching

### Wildcards (default behavior)

By default, search terms are split into keywords and a wildcard is appended:

- `apple ios` → `apple%` and `ios%`

To also prefix with a wildcard (`%term%`):

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->beginWithWildcard()
    ->search('os');
```

To disable the trailing wildcard:

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->beginWithWildcard()
    ->endWithWildcard(false)
    ->search('os');
```

### Exact match

Disable wildcards and use equality (`=`) semantics:

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->exactMatch()
    ->search('Laravel');
```

### Multi-word phrases

Wrap in double quotes:

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->search('"macos big sur"');
```

Or disable parsing altogether:

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->dontParseTerm()
    ->search('macos big sur');
```

### Standalone term parser

```php
$terms = Search::parseTerms('drums guitar');

Search::parseTerms('drums guitar', function ($term, $key) {
    // inspect/transform
});
```

## Sorting

### Per-model order column

Pass the order column as the third argument to `add()`:

```php
Search::add(Post::class, 'title', 'published_at')
    ->add(Video::class, 'title', 'released_at')
    ->orderByDesc()
    ->search('learn');
```

### Relevance ordering

```php
Search::add(Post::class, 'title')
    ->beginWithWildcard()
    ->orderByRelevance()
    ->search('Apple iPad');
```

Pitfall (README): ordering by relevance is **not supported** when searching through (nested) relationships.

### Ordering by model type

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

## Pagination

Call `paginate()` (or `simplePaginate()`) **before** `search()`.

```php
$paginator = Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->paginate(15)
    ->search('build');
```

Simple pagination:

```php
$paginator = Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->simplePaginate(15)
    ->search('build');
```

### Retaining query string parameters

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->paginate(15)
    ->withQueryString()
    ->search('build');
```

Or pass a custom parameter array:

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->paginate(15)
    ->withQueryString(['filter' => 'active', 'sort' => 'date'])
    ->search('build');
```

## Constraints and scoped queries

Instead of a model class, pass an Eloquent Builder with constraints/scopes:

```php
Search::add(Post::published(), 'title')
    ->add(Video::where('views', '>', 2500), 'title')
    ->search('compile');
```

## Searching multiple columns

```php
Search::add(Post::class, ['title', 'body'])
    ->add(Video::class, ['title', 'subtitle'])
    ->search('eloquent');
```

## Searching through relationships

Use dot notation (supports nesting):

```php
Search::add(Post::class, ['comments.body'])
    ->add(Video::class, ['posts.user.biography'])
    ->search('solution');
```

## Full-text search

Use `addFullText()` to switch a model/columns to native full-text behavior:

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

### Driver compatibility notes

From README: feature support differs per database driver.

- MySQL: native full-text indexes.
- PostgreSQL: `tsquery` (similarity search requires `pg_trgm`).
- SQLite: LIKE-based alternatives.

When changing query compilation logic, ensure the driver detection and strategy selection remains correct.

## Similarity search (“sounds like”)

```php
Search::new()
    ->add(Post::class, 'framework')
    ->add(Video::class, 'framework')
    ->soundsLike()
    ->search('larafel');
```

## Eager loading

```php
Search::add(Post::with('comments'), 'title')
    ->add(Video::with('likes'), 'title')
    ->search('guitar');
```

## Getting results without searching

Call `search()` with no term or an empty term.

In this case, you may omit the second argument to `add()` and use `orderBy()` to set the order column for each model:

```php
Search::add(Post::class)
    ->orderBy('published_at')
    ->add(Video::class)
    ->orderBy('released_at')
    ->search();
```

## Counting

```php
$count = Search::add(Post::published(), 'title')
    ->add(Video::where('views', '>', 2500), 'title')
    ->count('compile');
```

## Including model type in results

Add a `type` field (key is customizable):

```php
$paginator = Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->includeModelType()
    ->paginate()
    ->search('foo');
```

Customize type name per model by adding a `searchType()` method:

```php
class Video extends Model
{
    public function searchType()
    {
        return 'awesome_video';
    }
}
```

## Common pitfalls / gotchas

- **Default ordering:** by default, results are ordered by each model’s “updated” column (`getUpdatedAtColumn()`), falling back to primary key when timestamps are disabled.
- **Relevance + relationships:** `orderByRelevance()` does not support relationship searches.
- **Full-text requirements:** MySQL needs proper full-text indexes; PostgreSQL similarity search may require extensions.
- **Pagination call order:** configure pagination *before* calling `search()`.
- **Result type collisions:** if you aggregate different models, ensure consumers can distinguish them (`includeModelType()`).

## Testing

```bash
composer test
```
