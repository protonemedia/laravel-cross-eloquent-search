# Changelog

All notable changes to `laravel-cross-eloquent-search` will be documented in this file

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
