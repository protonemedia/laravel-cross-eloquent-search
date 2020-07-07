# Laravel Cross Eloquent Search

[![Latest Version on Packagist](https://img.shields.io/packagist/v/protonemedia/laravel-cross-eloquent-search.svg?style=flat-square)](https://packagist.org/packages/protonemedia/laravel-cross-eloquent-search)
[![Build Status](https://img.shields.io/travis/pascalbaljetmedia/laravel-cross-eloquent-search/master.svg?style=flat-square)](https://travis-ci.org/pascalbaljetmedia/laravel-cross-eloquent-search)
[![Quality Score](https://img.shields.io/scrutinizer/g/pascalbaljetmedia/laravel-cross-eloquent-search.svg?style=flat-square)](https://scrutinizer-ci.com/g/pascalbaljetmedia/laravel-cross-eloquent-search)
[![Total Downloads](https://img.shields.io/packagist/dt/protonemedia/laravel-cross-eloquent-search.svg?style=flat-square)](https://packagist.org/packages/protonemedia/laravel-cross-eloquent-search)

## Requirements

* PHP 7.4+
* MySQL 5.7+
* Laravel 6.0 / 7.0

## Installation

You can install the package via composer:

```bash
composer require protonemedia/laravel-cross-eloquent-search
```

## Configuration

No configuration!

## Usage

### Basic

```php
$results = Search::new()
    ->add(Post::class, 'title')
    ->add(Video::class, 'title')
    ->get('foo');
```

### Pagination

```php
$results = Search::new()
    ->add(Post::class, 'title')
    ->add(Video::class, 'title')

    ->paginate()
    // or
    ->paginate($perPage = 15, $pageName = 'page', $page = 1)

    ->get('foo');
```

### Scoped queries

```php
$results = Search::new()
    ->add(Post::published(), 'title')
    ->add(Video::where('views', '>', 2500), 'title')
    ->get('foo');
```

### Multiple columns

```php
$results = Search::new()
    ->add(Post::class, ['title', 'body'])
    ->add(Video::class, ['title', 'subtitle'])
    ->get('foo');
```

### Order results

```php
$results = Search::new()
    ->add(Post::class, 'title', 'publihed_at')
    ->add(Video::class, 'title', 'released_at')
    ->orderByDesc() // optional
    ->get('foo');
```

### Testing

``` bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email pascal@protone.media instead of using the issue tracker.

## Credits

- [Pascal Baljet](https://github.com/protonemedia)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
