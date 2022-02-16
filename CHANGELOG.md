# Changelog

All notable changes to `laravel-cross-eloquent-search` will be documented in this file

## 3.0.0 - 2022-02-16

* Support for Full-Text Search.
* The `get` method has been renamed to `search`.
* The `addWhen` method has been removed in favor of `when`.
* Support for custom *type* values when using the `includeModelType` method.
* By default, the results are sorted by the *updated* column, which is the `updated_at` column in most cases. If you don't use timestamps, it will now use the primary key by default.

## 2.7.1 - 2022-02-10

- Add Conditionable trait to Searcher (thanks @Daanra!)

## 2.7.0 - 2022-02-04

- Support for Laravel 9

## 2.6.1 - 2021-12-22

- Bugfix for excluding models when searching for relations without a search term (fixes #37).

## 2.6.0 - 2021-12-22

- Added `includeModelType` method (thanks @mrkalmdn!)

## 2.5.0 - 2021-12-19

- Support for PHP 8.1
- Dropped support for PHP 7.4
- Dropped support for Laravel 6 and 7

## 2.4.0 - 2021-10-06

- Added support for searching through (nested) relationships

## 2.3.1 - 2021-10-01

- Respect the existing orders when ordering by model type

## 2.3.0 - 2021-10-01

- Added 'orderByModel' method

## 2.2.5 - 2021-10-01

- Bugfix for Non-Latin languages

## 2.2.4 - 2021-09-17

- Bugfix for JSON columns

## 2.2.3 - 2021-09-22

- Support for ignore case

## 2.2.2 - 2021-09-21

- Bugfix for empty search terms

## 2.2.1 - 2021-09-17

- Bugfix for JSON columns

## 2.2.0 - 2021-09-17

- Support for ordering by relevance

## 2.1.0 - 2021-08-09

- Support for Table prefixes

## 2.0.4 - 2021-05-03

- Fix phpdoc comment format (credit to @gazben)

## 2.0.3 - 2021-04-29

- Bugfix for non-paginated queries.

## 2.0.0 / 2.0.1 / 2.0.2 - 2021-01-29

- Support for the soundex algorithm
- Ability to disable wildcards
- Uses the `getUpdatedAtColumn` method to evaluate the *updated* column
- `startWithWildcard` method has been renamed to `beginWithWildcard`
- `allowEmptySearchQuery` method and `EmptySearchQueryException` class removed

## 1.9.0 - 2020-12-23

- Support for `addMany` and `andWhen` methods.

## 1.8.0 - 2020-12-23

- Support for simple pagination (credit to @mewejo).

## 1.7.0 - 2020-12-16

- Added a `count` method.

## 1.6.0 - 2020-12-16

- Allow empty search terms without selecting columns

## 1.5.0 - 2020-10-30

- Added support for PHP 8.0

## 1.4.0 - 2020-10-28

- Allow empty search terms
- Added `new()` method method

## 1.3.1 - 2020-10-28

- Docs

## 1.3.0 - 2020-09-24

- Support for Laravel 8.0

## 1.2.0 - 2020-08-28

- Standalone search terms parser

## 1.1.0 - 2020-08-10

- Option to disable the parsing of the search term

## 1.0.0 - 2020-07-08

- Initial release
