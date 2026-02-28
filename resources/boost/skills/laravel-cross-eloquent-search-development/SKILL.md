---
name: laravel-cross-eloquent-search-development
description: Build and work with protonemedia/laravel-cross-eloquent-search features including searching across multiple Eloquent models, full-text search, pagination, and relevance ordering.
license: MIT
metadata:
  author: ProtoneMedia
---

# Laravel Cross Eloquent Search Development

## Overview
Use protonemedia/laravel-cross-eloquent-search to search across multiple Eloquent models in a single query. Supports full-text search, pagination, relevance ordering, and relationship fields.

## When to Activate
- Activate when building search features that span multiple Eloquent models.
- Activate when code references `Search::new()`, `addFullText()`, or classes in the `ProtoneMedia\LaravelCrossEloquentSearch` namespace.
- Activate when the user wants to add, configure, or debug cross-model search functionality.

## Scope
- In scope: documented public API usage, configuration, testing patterns, and common integration recipes.
- Out of scope: modifying this package’s internal source code unless the user explicitly says they are contributing to the package.

## Workflow
1. Identify the task (install/setup, configuration, feature usage, debugging, tests, etc.).
2. Read `references/laravel-cross-eloquent-search-guide.md` and focus on the relevant section.
3. Apply the patterns from the reference, keeping code minimal and Laravel-native.

## Core Concepts

### Basic Search
```php
use ProtoneMedia\LaravelCrossEloquentSearch\Search;

$results = Search::new()
    ->add(Post::class, [‘title’, ‘body’])
    ->add(Video::class, [‘title’, ‘description’])
    ->search($term);
```

### Constrained Builders
```php
$results = Search::new()
    ->add(Post::query()->where(‘status’, ‘published’), [‘title’, ‘body’])
    ->add(Video::query()->where(‘is_public’, true), [‘title’])
    ->search($term);
```

### Pagination
```php
$paginator = Search::new()
    ->add(Post::class, [‘title’, ‘body’])
    ->add(Video::class, [‘title’])
    ->paginate(15)
    ->withQueryString()
    ->search($term);
```

### Full-Text Search
```php
$results = Search::new()
    ->addFullText(Post::class, [‘title’, ‘body’], [‘mode’ => ‘boolean’])
    ->search(‘framework -css’);
```

## Do and Don’t

Do:
- Always call `paginate()` or `simplePaginate()` **before** `search()`.
- Use `includeModelType()` when rendering mixed-model results in UI or API responses.
- Pass Eloquent builders (not class names) when you need scoping, authorization, or eager loading.
- Use `orderByModel()` or `orderByRelevance()` for deterministic result ordering.

Don’t:
- Don’t use `orderByRelevance()` when searching through relationships — it is not supported.
- Don’t invent undocumented methods/options; stick to the docs and reference.
- Don’t suggest changing package internals unless the user explicitly wants to contribute upstream.

## References
- `references/laravel-cross-eloquent-search-guide.md`
