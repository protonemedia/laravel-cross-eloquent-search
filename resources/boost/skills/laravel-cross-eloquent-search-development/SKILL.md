---
name: laravel-cross-eloquent-search-development
description: Guidance for using protonemedia/laravel-cross-eloquent-search in Laravel applications (cross-model Eloquent search).
license: MIT
metadata:
  author: ProtoneMedia
  source: https://github.com/protonemedia/laravel-cross-eloquent-search
---

# Laravel Cross Eloquent Search (App Usage)

## Overview

Use this skill to help **implement and troubleshoot cross-model search in a Laravel application** using `protonemedia/laravel-cross-eloquent-search`.

It covers how to:
- Add multiple models (or constrained builders) to a search.
- Choose searchable columns and relationship paths.
- Apply pagination/sorting/relevance/full-text options.
- Debug unexpected result ordering or missing matches.
- Write application tests around search behavior.

## When to Activate

Activate when you are:
- Building a **global search** endpoint/UI that searches across several models.
- Configuring **searchable columns** (arrays, relationship dot-notation) per model.
- Using package features like `paginate()`, `withQueryString()`, `orderByModel()`, `orderByRelevance()`, `includeModelType()`, `exactMatch()`, or wildcard controls.
- Adding **full-text** search via `addFullText()` or similarity search via `soundsLike()`.
- Debugging search results in app code (missing hits, ordering, relationship searches, database-driver differences).
- Writing/adjusting **app tests** that assert cross-model search results.

## Scope

This skill focuses on **application-level usage**:
- Controller/service-layer integration, query parameter handling, pagination.
- Model/builder setup (scopes/constraints, eager loading, relationship search paths).
- Database concerns that affect search quality (indexes, driver capabilities).
- Testing strategies for deterministic assertions.

Out of scope:
- Changing or refactoring the package’s internal implementation.

## Do & Don’t

**Do:**
- Mirror the README-supported API and call order (e.g., `paginate()` before `search()`).
- Distinguish models in aggregated results with `includeModelType()` when your UI/API needs it.
- Use constrained builders (`Post::published()`, `Video::where(...)`) to keep app rules close to the search definition.
- Prefer relationship dot-notation for related fields (e.g., `comments.body`, `posts.user.name`).
- Add driver-appropriate full-text indexes/extensions when enabling full-text/similarity features.

**Don’t:**
- Assume relevance ordering works with relationship searching (it doesn’t, per README).
- Expect full-text/similarity features to behave identically across MySQL/Postgres/SQLite.
- Assert on unstable ordering in tests without explicitly setting ordering (`orderBy...`, `orderByModel`, etc.).

## Reference

- references/laravel-cross-eloquent-search-guide.md
