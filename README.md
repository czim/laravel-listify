# Laravel Listify

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status](https://travis-ci.org/czim/laravel-listify.svg?branch=master)](https://travis-ci.org/czim/laravel-listify)
[![Latest Stable Version](http://img.shields.io/packagist/v/czim/laravel-listify.svg)](https://packagist.org/packages/czim/laravel-listify)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/369fd4d7-b2d1-4438-9e08-e7ad586b81c4/mini.png)](https://insight.sensiolabs.com/projects/369fd4d7-b2d1-4438-9e08-e7ad586b81c4)

This is a rebuild (from scratch) of [Lookitsatravis' Listify](https://github.com/lookitsatravis/listify) for personal reasons.
It uses the same interface as Listify, so switching from that to this package should not break anything.


## Why?

Listify is *great*, but has a few shortcomings:

- A very minor one is the code standard, which could IMHO do with some serious cleanup.
- More annoying are the heavy reliance on `private` methods and properties, making it impossible to use Listify with flexible inheritance approaches.
- Functionally, Listify is not suited for variable scopes. The belongs-to scope is too restrictive (no nullable foreign key columns), and any string-based scope is set inflexibly.
  For my purposes, I require a callable scope that handles swapping scopes well and treats a `null`-scope as taking an item out of a list.


## Install

Via Composer

``` bash
$ composer require czim/laravel-listify
```

## Usage 

Although it is not required, you may make models that use the trait implement the `ListifyInterface` for your own purposes. 


## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.


## Credits

- [Coen Zimmerman][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/czim/laravel-listify.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/czim/laravel-listify.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/czim/laravel-listify
[link-downloads]: https://packagist.org/packages/czim/laravel-listify
[link-author]: https://github.com/czim
[link-contributors]: ../../contributors
