# Laravel Cross Eloquent Search Reference

Complete reference for `protonemedia/laravel-cross-eloquent-search`. Full documentation: https://github.com/protonemedia/laravel-cross-eloquent-search

## Basic Search

Search across multiple Eloquent models with a single fluent API:

```php
use ProtoneMedia\LaravelCrossEloquentSearch\Search;

$results = Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->search('howto');
```

The `$results` variable is a `\Illuminate\Database\Eloquent\Collection` containing a mixed set of model instances.

## Multi-Column Search

Search across multiple columns per model:

```php
$results = Search::add(Post::class, ['title', 'body'])
    ->add(Video::class, ['title', 'subtitle'])
    ->search('eloquent');
```

## Adding Models

### Using class string
```php
Search::add(Post::class, 'title');
```

### Using query builder with constraints
```php
Search::add(Post::published(), 'title');
Search::add(Video::where('views', '>', 2500), 'title');
```

### With eager loading
```php
Search::add(Post::with('comments', 'author'), 'title');
```

### With custom order column
```php
Search::add(Post::class, 'title', 'published_at');
```

### Adding multiple models at once
```php
Search::addMany([
    [Post::class, 'title'],
    [Video::class, 'subtitle'],
]);
```

## Wildcard Control

### Default behavior (end wildcard)
Searches with `term%` by default — matches values starting with the term:

```php
Search::add(Post::class, 'title')
    ->search('build');
// WHERE title LIKE 'build%'
```

### Begin with wildcard
Enable `%term%` matching for searching anywhere in a string:

```php
Search::add(Post::class, 'title')
    ->beginWithWildcard()
    ->search('build');
// WHERE title LIKE '%build%'
```

### Disable end wildcard
```php
Search::add(Post::class, 'title')
    ->endWithWildcard(false)
    ->search('build');
// WHERE title LIKE 'build'
```

### Exact match
Use the `=` operator with no wildcards:

```php
Search::add(Post::class, 'title')
    ->exactMatch()
    ->search('Build');
// WHERE title = 'Build'
```

## Term Parsing

By default, search terms are split on spaces. Each word is searched independently:

```php
Search::add(Post::class, 'title')
    ->search('foo bar');
// Searches for 'foo' AND 'bar' separately
```

### Quoted phrases
Use double quotes to treat phrases as a single term:

```php
Search::add(Post::class, 'title')
    ->search('"foo bar"');
// Searches for 'foo bar' as one term
```

### Disable term parsing
Treat the entire input as one search term:

```php
Search::add(Post::class, 'title')
    ->dontParseTerm()
    ->search('foo bar');
// Searches for 'foo bar' as one term
```

### Standalone term parser
Parse terms without running a search:

```php
$terms = Search::parseTerms('foo bar "baz qux"');
// ['foo', 'bar', 'baz qux']
```

With a callback to transform each term:

```php
$terms = Search::parseTerms('foo bar', fn ($term, $key) => strtoupper($term));
// ['FOO', 'BAR']
```

## Sorting

### Default sorting
Results are sorted by the model's `updated_at` column in ascending order. If no `updated_at` column exists, it falls back to `created_at`, then the primary key.

### Descending order
```php
Search::add(Post::class, 'title')
    ->orderByDesc()
    ->search('build');
```

### Ascending order (explicit)
```php
Search::add(Post::class, 'title')
    ->orderByAsc()
    ->search('build');
```

### Order by relevance
Sort results by how many search terms match (most relevant first):

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->orderByRelevance()
    ->search('build');
```

### Order by model type
Specify the order in which model types appear in results:

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->orderByModel([Post::class, Video::class])
    ->search('build');
```

### Custom order column per model
Specify a column per model to use for ordering instead of the default timestamp:

```php
Search::add(Post::class, 'title', 'published_at')
    ->add(Video::class, 'title', 'released_at')
    ->search('build');
```

## Pagination

### Length-aware pagination
```php
$results = Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->paginate(15)
    ->search('build');

// In Blade:
{{ $results->links() }}
```

### Simple pagination
```php
$results = Search::add(Post::class, 'title')
    ->simplePaginate(15)
    ->search('build');
```

### Custom page name and page number
```php
$results = Search::add(Post::class, 'title')
    ->paginate(15, 'page', 2)
    ->search('build');
```

### Preserve query string in pagination links
```php
$results = Search::add(Post::class, 'title')
    ->paginate(15)
    ->withQueryString()
    ->search('build');
```

## Counting Results

Get the total number of matching results without fetching models:

```php
$count = Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->count('build');
```

## Relationship Search

### Search through relationships using dot notation
```php
Search::add(Post::class, ['comments.body'])
    ->search('solution');
```

### Nested relationships
```php
Search::add(Video::class, ['posts.user.biography'])
    ->search('developer');
```

### Mix direct and relationship columns
```php
Search::add(Post::class, ['title', 'comments.body'])
    ->search('laravel');
```

## Full-Text Search

Use database full-text indexes for better performance:

```php
Search::addFullText(Post::class, 'title')
    ->search('framework');
```

### Boolean mode (MySQL)
```php
Search::addFullText(Post::class, 'title', ['mode' => 'boolean'])
    ->search('framework -css');
```

### Multiple full-text columns
```php
Search::addFullText(Post::class, ['title', 'body'])
    ->search('framework');
```

### Full-text through relationships
```php
Search::addFullText(Post::class, ['comments' => ['body']])
    ->search('solution');
```

### Mix regular and full-text search
```php
Search::add(Post::class, 'title')
    ->addFullText(Video::class, 'title', ['mode' => 'boolean'])
    ->search('framework');
```

## Phonetic Matching

Use `soundsLike()` for phonetic search. Behavior varies by database:
- **MySQL**: uses `SOUNDS LIKE` operator
- **PostgreSQL**: uses `similarity()` function (requires `pg_trgm` extension)
- **SQLite**: uses pattern substitution (ph/f, c/k, s/z)

```php
Search::add(Post::class, 'title')
    ->soundsLike()
    ->search('larafel');
// Matches 'laravel' via phonetic similarity
```

## Case-Insensitive Search

Force case-insensitive matching using `LOWER()`:

```php
Search::add(Post::class, 'title')
    ->ignoreCase()
    ->search('LARAVEL');
```

## Conditional Building

Use `when()` for conditional logic:

```php
Search::when($user->isVerified(), fn ($search) => $search->add(Post::class, 'title'))
    ->when($user->isAdmin(), fn ($search) => $search->add(Video::class, 'title'))
    ->search('howto');
```

## Model Type Identification

Include a model type identifier in results to distinguish between different models:

```php
$results = Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->includeModelType()
    ->search('build');

// Each result has a 'type' attribute:
// Post => 'posts', Video => 'videos'
```

### Custom key
```php
Search::add(Post::class, 'title')
    ->includeModelType('search_type')
    ->search('build');

// Each result has a 'search_type' attribute
```

## Inspecting the Searcher

Use `tap()` to inspect or debug the searcher configuration:

```php
Search::add(Post::class, 'title')
    ->tap(function ($searcher) {
        // Inspect $searcher properties
    })
    ->search('build');
```

## Getting Results Without Search Terms

Call `search()` with no arguments or an empty string to get all results from the configured models, still respecting order and pagination:

```php
$results = Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->orderByDesc()
    ->search();
```

## Database Support

The package supports MySQL 8.0+, PostgreSQL 12+, and SQLite 3.8+. Key differences:

| Feature | MySQL | PostgreSQL | SQLite |
|---------|-------|-----------|--------|
| Full-Text Search | Native `FULLTEXT` | `to_tsvector`/`to_tsquery` | LIKE-based simulation |
| Phonetic Matching | `SOUNDS LIKE` | `similarity()` | Pattern substitution |
| Case Sensitivity | Case-insensitive by default | Case-sensitive | Case-insensitive |
