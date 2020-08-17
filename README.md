# Laravel Cross Eloquent Search

[![Latest Version on Packagist](https://img.shields.io/packagist/v/protonemedia/laravel-cross-eloquent-search.svg?style=flat-square)](https://packagist.org/packages/protonemedia/laravel-cross-eloquent-search)
[![Build Status](https://img.shields.io/travis/pascalbaljetmedia/laravel-cross-eloquent-search/master.svg?style=flat-square)](https://travis-ci.org/pascalbaljetmedia/laravel-cross-eloquent-search)
[![Quality Score](https://img.shields.io/scrutinizer/g/pascalbaljetmedia/laravel-cross-eloquent-search.svg?style=flat-square)](https://scrutinizer-ci.com/g/pascalbaljetmedia/laravel-cross-eloquent-search)
[![Total Downloads](https://img.shields.io/packagist/dt/protonemedia/laravel-cross-eloquent-search.svg?style=flat-square)](https://packagist.org/packages/protonemedia/laravel-cross-eloquent-search)
[![Buy us a tree](https://img.shields.io/badge/Treeware-%F0%9F%8C%B3-lightgreen)](https://plant.treeware.earth/pascalbaljetmedia/laravel-cross-eloquent-search)

This Laravel package allows you to search through multiple Eloquent models. It supports sorting, pagination, scoped queries, eager load relationships and searching through single or multiple columns.

## Requirements

* PHP 7.4+
* MySQL 5.7+
* Laravel 6.0 / 7.0

## Features

* Search through one or more [Eloquent models](https://laravel.com/docs/master/eloquent).
* Support for cross-model [pagination](https://laravel.com/docs/master/pagination#introduction).
* Search through single or multiple columns.
* Use [constraints](https://laravel.com/docs/master/eloquent#retrieving-models) and [scoped queries](https://laravel.com/docs/master/eloquent#query-scopes).
* [Eager load relationships](https://laravel.com/docs/master/eloquent-relationships#eager-loading) for each model.
* In-database [sorting](https://laravel.com/docs/master/queries#ordering-grouping-limit-and-offset) of the combined result.
* Zero third-party dependencies

## Blogpost

If you want to know more about the background of this package, please read [the blogpost](https://protone.media/blog/search-through-multiple-eloquent-models-with-our-latest-laravel-package).

## Installation

You can install the package via composer:

```bash
composer require protonemedia/laravel-cross-eloquent-search
```

## Usage

Start your search query by adding one or more models to search through. Call the `add` method with the class name of the model and the column you want to search through. Then call the `get` method with the search term, and you'll get a `\Illuminate\Database\Eloquent\Collection` instance with the results. By default, the results are sorted in ascending order by the `updated_at` column.

```php
use ProtoneMedia\LaravelCrossEloquentSearch\Search;

$results = Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->get('howto');
```

### Sorting

If you want to sort the results by another column, you can pass that column to the `add` method as a third parameter. Call the `orderByDesc` method to sort the results in descending order.

```php
Search::add(Post::class, 'title', 'publihed_at')
    ->add(Video::class, 'title', 'released_at')
    ->orderByDesc()
    ->get('learn');
```

### Start search term with wildcard

By default, we split up the search term, and each keyword will get a wildcard symbol to do partial matching. Practically this means the search term `apple ios` will result in `apple%` and `ios%`. If you want a wildcard symbol to start with as well, you can call the `startWithWildcard` method. This will result in `%apple%` and `%ios%`.

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->startWithWildcard()
    ->get('os');
```

### Multi-word search

Multi-word search is supported out of the box. Simply wrap your phrase into double-quotes.

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->get('"macos big sur"');
```

You can disable the parsing of the search term by calling the `dontParseTerm` method, which gives you the same results as using double-quotes.

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->dontParseTerm()
    ->get('macos big sur');
```

### Pagination

We highly recommend to paginate your results. Call the `paginate` method before the `get` method, and you'll get an instance of `\Illuminate\Contracts\Pagination\LengthAwarePaginator` as a result. The `paginate` method takes three (optional) parameters to customize the paginator. These arguments are [the same](https://laravel.com/docs/master/pagination#introduction) as Laravel's database paginator.

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')

    ->paginate()
    // or
    ->paginate($perPage = 15, $pageName = 'page', $page = 1)

    ->get('build');
```

### Constraints and scoped queries

Instead of the class name, you can also pass an instance of the [Eloquent query builder](https://laravel.com/docs/master/eloquent#retrieving-models) to the `add` method. This allows you to add constraints to each model.

```php
Search::add(Post::published(), 'title')
    ->add(Video::where('views', '>', 2500), 'title')
    ->get('compile');
```

### Multiple columns per model

You can search through multiple columns by passing an array of columns as the second argument.

```php
Search::add(Post::class, ['title', 'body'])
    ->add(Video::class, ['title', 'subtitle'])
    ->get('eloquent');
```


### Eager load relationships

Not much to explain here, but this is supported as well :)

```php
Search::add(Post::with('comments'), 'title')
    ->add(Video::with('likes'), 'title')
    ->get('guitar');
```

### Testing

``` bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information about what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Other Laravel packages

* [`Laravel Analytics Event Tracking`](https://github.com/pascalbaljetmedia/laravel-analytics-event-tracking): Laravel package to easily send events to Google Analytics.
* [`Laravel Blade On Demand`](https://github.com/pascalbaljetmedia/laravel-blade-on-demand): Laravel package to compile Blade templates in memory.
* [`Laravel FFMpeg`](https://github.com/pascalbaljetmedia/laravel-ffmpeg): This package provides an integration with FFmpeg for Laravel. The storage of the files is handled by Laravel's Filesystem.
* [`Laravel Form Components`](https://github.com/pascalbaljetmedia/laravel-form-components): Blade components to rapidly build forms with Tailwind CSS Custom Forms and Bootstrap 4. Supports validation, model binding, default values, translations, includes default vendor styling and fully customizable!
* [`Laravel Paddle`](https://github.com/pascalbaljetmedia/laravel-paddle): Paddle.com API integration for Laravel with support for webhooks/events.
* [`Laravel Verify New Email`](https://github.com/pascalbaljetmedia/laravel-verify-new-email): This package adds support for verifying new email addresses: when a user updates its email address, it won't replace the old one until the new one is verified.
* [`Laravel WebDAV`](https://github.com/pascalbaljetmedia/laravel-webdav): WebDAV driver for Laravel's Filesystem.

### Security

If you discover any security related issues, please email pascal@protone.media instead of using the issue tracker.

## Credits

- [Pascal Baljet](https://github.com/protonemedia)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Treeware

This package is [Treeware](https://treeware.earth). If you use it in production, then we ask that you [**buy the world a tree**](https://plant.treeware.earth/pascalbaljetmedia/laravel-cross-eloquent-search) to thank us for our work. By contributing to the Treeware forest you’ll be creating employment for local families and restoring wildlife habitats.
