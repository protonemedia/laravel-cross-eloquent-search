---
name: cross-eloquent-search-development
description: Build and work with protonemedia/laravel-cross-eloquent-search features including searching across multiple Eloquent models, sorting results, paginating combined output, full-text search, and relationship-based queries.
license: MIT
metadata:
  author: Protone Media
---

# Cross Eloquent Search Development

## Overview
Use protonemedia/laravel-cross-eloquent-search to search through multiple Eloquent models with a single query. Supports multi-column search, wildcard control, full-text search, pagination, sorting by relevance or model type, relationship searching, and phonetic matching.

## When to Activate
- Activate when working with searching across multiple Eloquent models simultaneously in Laravel.
- Activate when code references the `Search` facade, the `Searcher` class, or `ProtoneMedia\LaravelCrossEloquentSearch`.
- Activate when the user wants to add, configure, or debug multi-model search functionality.

## Scope
- In scope: multi-model searching, multi-column search, wildcard control, full-text search, pagination, sorting, relationship queries, phonetic matching, term parsing, search constraints.
- Out of scope: single-model search (use Eloquent directly), non-Laravel frameworks, Scout/Algolia/Meilisearch integration.

## Workflow
1. Identify the task (adding models to search, configuring wildcards, setting up full-text search, paginating results, etc.).
2. Read `references/cross-eloquent-search-guide.md` and focus on the relevant section.
3. Apply the patterns from the reference, keeping code minimal and Laravel-native.

## Core Concepts

### Basic Multi-Model Search
Add multiple models with columns to search, then call `search()`:

```php
use ProtoneMedia\LaravelCrossEloquentSearch\Search;

Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->search('howto');
```

### Multi-Column Search
```php
Search::add(Post::class, ['title', 'body'])
    ->add(Video::class, ['title', 'subtitle'])
    ->search('eloquent');
```

### Pagination
```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->paginate(15)
    ->search('build');
```

### Full-Text Search
```php
Search::add(Post::class, 'title')
    ->addFullText(Video::class, 'title', ['mode' => 'boolean'])
    ->search('framework -css');
```

### Relationship Search
```php
Search::add(Post::class, ['comments.body'])
    ->search('solution');
```

## Do and Don't

Do:
- Always call `->search($terms)` at the end of the chain to execute the query.
- Use `beginWithWildcard()` when searching in the middle of strings.
- Use pre-filtered query builders (e.g., `Post::published()`) to apply constraints.
- Use `orderByRelevance()` when result ranking matters.
- Use `addFullText()` with proper database indexes for better performance on large datasets.
- Use `paginate()` or `simplePaginate()` for large result sets.

Don't:
- Don't forget that the default wildcard is `term%` (end only) — call `beginWithWildcard()` if you need `%term%`.
- Don't mix `orderByRelevance()` with a custom per-model order column — relevance sorting overrides it.
- Don't use `soundsLike()` without understanding database-specific behavior (MySQL `SOUNDS LIKE`, PostgreSQL `similarity()`, SQLite pattern substitution).
- Don't call `search()` without adding at least one model via `add()` or `addFullText()`.

## References
- `references/cross-eloquent-search-guide.md`
