# laravel-cross-eloquent-search development guide

For full documentation, see the README: https://github.com/protonemedia/laravel-cross-eloquent-search#readme

## At a glance
Searches across multiple **Eloquent models**, with support for sorting, pagination, scopes, and eager-loading.

## Local setup
- Install dependencies: `composer install`
- Keep the dev loop package-focused (avoid adding app-only scaffolding).

## Testing
- Run: `composer test` (preferred) or the repository’s configured test runner.
- Add regression tests for bug fixes.

## Notes & conventions
- Query performance matters: avoid N+1 and unnecessary joins/subqueries.
- Keep the API for defining searchable models/columns stable.
- Add tests for ordering/pagination consistency across models.
