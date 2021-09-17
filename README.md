# Laravel Cross Eloquent Search

[![Latest Version on Packagist](https://img.shields.io/packagist/v/protonemedia/laravel-cross-eloquent-search.svg?style=flat-square)](https://packagist.org/packages/protonemedia/laravel-cross-eloquent-search)
![run-tests](https://github.com/protonemedia/laravel-cross-eloquent-search/workflows/run-tests/badge.svg)
[![Quality Score](https://img.shields.io/scrutinizer/g/protonemedia/laravel-cross-eloquent-search.svg?style=flat-square)](https://scrutinizer-ci.com/g/protonemedia/laravel-cross-eloquent-search)
[![Total Downloads](https://img.shields.io/packagist/dt/protonemedia/laravel-cross-eloquent-search.svg?style=flat-square)](https://packagist.org/packages/protonemedia/laravel-cross-eloquent-search)
[![Buy us a tree](https://img.shields.io/badge/Treeware-%F0%9F%8C%B3-lightgreen)](https://plant.treeware.earth/protonemedia/laravel-cross-eloquent-search)

This Laravel package allows you to search through multiple Eloquent models. It supports sorting, pagination, scoped queries, eager load relationships, and searching through single or multiple columns.

## Launcher 🚀

Hey! We've built a Docker-based deployment tool to launch apps and sites fully containerized. You can find all features and the roadmap on our [website](https://uselauncher.com), and we are on [Twitter](https://twitter.com/uselauncher) as well!

## Requirements

* PHP 7.4+
* MySQL 5.7+
* Laravel 6.0 and higher

## Features

* Search through one or more [Eloquent models](https://laravel.com/docs/master/eloquent).
* Support for cross-model [pagination](https://laravel.com/docs/master/pagination#introduction).
* Search through single or multiple columns.
* Order by (cross-model) columns or by relevance.
* Use [constraints](https://laravel.com/docs/master/eloquent#retrieving-models) and [scoped queries](https://laravel.com/docs/master/eloquent#query-scopes).
* [Eager load relationships](https://laravel.com/docs/master/eloquent-relationships#eager-loading) for each model.
* In-database [sorting](https://laravel.com/docs/master/queries#ordering-grouping-limit-and-offset) of the combined result.
* Zero third-party dependencies


### 📺 Want to watch an implementation of this package? Rewatch the live stream (skip to 13:44 for the good stuff): [https://youtu.be/WigAaQsPgSA](https://youtu.be/WigAaQsPgSA)

## Blog post

If you want to know more about this package's background, please read [the blog post](https://protone.media/blog/search-through-multiple-eloquent-models-with-our-latest-laravel-package).

## Support

We proudly support the community by developing Laravel packages and giving them away for free. Keeping track of issues and pull requests takes time, but we're happy to help! If this package saves you time or if you're relying on it professionally, please consider [supporting the maintenance and development](https://github.com/sponsors/pascalbaljet).

## Installation

You can install the package via composer:

```bash
composer require protonemedia/laravel-cross-eloquent-search
```

## Upgrading from v1

* The `startWithWildcard` method has been renamed to `beginWithWildcard`.
* The default order column is now evaluated by the `getUpdatedAtColumn` method. Previously it was hard-coded to `updated_at`. You still can use [another column](#sorting) to order by.
* The `allowEmptySearchQuery` method and `EmptySearchQueryException` class have been removed, but you can still [get results without searching](#getting-results-without-searching).

## Usage

Start your search query by adding one or more models to search through. Call the `add` method with the model's class name and the column you want to search through. Then call the `get` method with the search term, and you'll get a `\Illuminate\Database\Eloquent\Collection` instance with the results.

The results are sorted in ascending order by the *updated* column by default. In most cases, this column is `updated_at`. If you've [customized](https://laravel.com/docs/master/eloquent#timestamps) your model's `UPDATED_AT` constant, or overwritten the `getUpdatedAtColumn` method, this package will use the customized column. Of course, you can [order by another column](#sorting) as well.

```php
use ProtoneMedia\LaravelCrossEloquentSearch\Search;

$results = Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->get('howto');
```

If you care about indentation, you can optionally use the `new` method on the facade:

```php
Search::new()
    ->add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->get('howto');
```

You can add multiple models at once by using the `addMany` method:

```php
Search::addMany([
    [Post::class, 'title'],
    [Video::class, 'title'],
])->get('howto');
```

There's also an `addWhen` method, that adds the model when the first argument given to the method evaluates to `true`:

```php
Search::new()
    ->addWhen($user, Post::class, 'title')
    ->addWhen($user->isAdmin(), Video::class, 'title')
    ->get('howto');
```

### Wildcards

By default, we split up the search term, and each keyword will get a wildcard symbol to do partial matching. Practically this means the search term `apple ios` will result in `apple%` and `ios%`. If you want a wildcard symbol to begin with as well, you can call the `beginWithWildcard` method. This will result in `%apple%` and `%ios%`.

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->beginWithWildcard()
    ->get('os');
```

*Note: in previous versions of this package, this method was called `startWithWildcard()`.*

If you want to disable the behaviour where a wildcard is appended to the terms, you should call the `endWithWildcard` method with `false`:

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->beginWithWildcard()
    ->endWithWildcard(false)
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

### Sorting

If you want to sort the results by another column, you can pass that column to the `add` method as a third parameter. Call the `orderByDesc` method to sort the results in descending order.

```php
Search::add(Post::class, 'title', 'published_at')
    ->add(Video::class, 'title', 'released_at')
    ->orderByDesc()
    ->get('learn');
```

You can call the `orderByRelevance` method to sort the results by the number of occurrences of the search terms. Imagine these two sentences:

* Apple introduces iPhone 13 and iPhone 13 mini
* Apple unveils new iPad mini with breakthrough performance in stunning new design

If you search for *Apple iPad*, the second sentence will come up first, as there are more matches of the search terms.

```php
Search::add(Post::class, 'title')
    ->beginWithWildcard()
    ->orderByRelevance()
    ->get('Apple iPad');
```

### Pagination

We highly recommend paginating your results. Call the `paginate` method before the `get` method, and you'll get an instance of `\Illuminate\Contracts\Pagination\LengthAwarePaginator` as a result. The `paginate` method takes three (optional) parameters to customize the paginator. These arguments are [the same](https://laravel.com/docs/master/pagination#introduction) as Laravel's database paginator.

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')

    ->paginate()
    // or
    ->paginate($perPage = 15, $pageName = 'page', $page = 1)

    ->get('build');
```

You may also use [simple pagination](https://laravel.com/docs/master/pagination#simple-pagination). This will return an instance of `\Illuminate\Contracts\Pagination\Paginator`, which is not length aware:

```php
Search::add(Post::class, 'title')
    ->add(Video::class, 'title')

    ->simplePaginate()
    // or
    ->simplePaginate($perPage = 15, $pageName = 'page', $page = 1)

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

### Sounds like

MySQL has a *soundex* algorithm built-in so you can search for terms that sound almost the same. You can use this feature by calling the `soundsLike` method:

```php
Search::new()
    ->add(Post::class, 'framework')
    ->add(Video::class, 'framework')
    ->soundsLike()
    ->get('larafel');
```

### Eager load relationships

Not much to explain here, but this is supported as well :)

```php
Search::add(Post::with('comments'), 'title')
    ->add(Video::with('likes'), 'title')
    ->get('guitar');
```

### Getting results without searching

You call the `get` method without a term or with an empty term. In this case, you can discard the second argument of the `add` method. With the `orderBy` method, you can set the column to sort by (previously the third argument):

```php
Search::add(Post::class)
    ->orderBy('published_at')
    ->add(Video::class)
    ->orderBy('released_at')
    ->get();
```

### Counting records

You can count the number of results with the `count` method:

```php
Search::add(Post::published(), 'title')
    ->add(Video::where('views', '>', 2500), 'title')
    ->count('compile');
```

### Standalone parser

You can use the parser with the `parseTerms` method:

```php
$terms = Search::parseTerms('drums guitar');
```

You can also pass in a callback as a second argument to loop through each term:

```php
Search::parseTerms('drums guitar', function($term, $key) {
    //
});
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

* [`Laravel Analytics Event Tracking`](https://github.com/protonemedia/laravel-analytics-event-tracking): Laravel package to easily send events to Google Analytics.
* [`Laravel Blade On Demand`](https://github.com/protonemedia/laravel-blade-on-demand): Laravel package to compile Blade templates in memory.
* [`Laravel Eloquent Scope as Select`](https://github.com/protonemedia/laravel-eloquent-scope-as-select): Stop duplicating your Eloquent query scopes and constraints in PHP. This package lets you re-use your query scopes and constraints by adding them as a subquery.
* [`Laravel Eloquent Where Not`](https://github.com/protonemedia/laravel-eloquent-where-not): This Laravel package allows you to flip/invert an Eloquent scope, or really any query constraint.
* [`Laravel FFMpeg`](https://github.com/protonemedia/laravel-ffmpeg): This package provides an integration with FFmpeg for Laravel. The storage of the files is handled by Laravel's Filesystem.
* [`Laravel Form Components`](https://github.com/protonemedia/laravel-form-components): Blade components to rapidly build forms with Tailwind CSS Custom Forms and Bootstrap 4. Supports validation, model binding, default values, translations, includes default vendor styling and fully customizable!
* [`Laravel Mixins`](https://github.com/protonemedia/laravel-mixins): A collection of Laravel goodies.
* [`Laravel Paddle`](https://github.com/protonemedia/laravel-paddle): Paddle.com API integration for Laravel with support for webhooks/events.
* [`Laravel Verify New Email`](https://github.com/protonemedia/laravel-verify-new-email): This package adds support for verifying new email addresses: when a user updates its email address, it won't replace the old one until the new one is verified.
* [`Laravel WebDAV`](https://github.com/protonemedia/laravel-webdav): WebDAV driver for Laravel's Filesystem.

### Security

If you discover any security-related issues, please email pascal@protone.media instead of using the issue tracker.

## Credits

- [Pascal Baljet](https://github.com/protonemedia)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Treeware

This package is [Treeware](https://treeware.earth). If you use it in production, we ask that you [**buy the world a tree**](https://plant.treeware.earth/pascalbaljetmedia/laravel-cross-eloquent-search) to thank us for our work. By contributing to the Treeware forest, you'll create employment for local families and restoring wildlife habitats.
