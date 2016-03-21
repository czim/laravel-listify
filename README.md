# Laravel Listify

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status](https://travis-ci.org/czim/laravel-listify.svg?branch=master)](https://travis-ci.org/czim/laravel-listify)
[![Latest Stable Version](http://img.shields.io/packagist/v/czim/laravel-listify.svg)](https://packagist.org/packages/czim/laravel-listify)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/7d80b8fa-5647-40c6-b5bb-d9583398d128/mini.png)](https://insight.sensiolabs.com/projects/7d80b8fa-5647-40c6-b5bb-d9583398d128)

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

For general functionality the original Listify interface is largely the same. For reference, see [the original documentation](https://github.com/lookitsatravis/listify).

Some exceptions apply:

- You may now use a `callable` scope, which may return any string to be used in a (raw) `where` clause. The original limitations to Listify string scopes apply. However, `null` is now an acceptable scope that will keep or remove the record from any list (its `position` will remain `NULL`).   
- You may now use nullable and null foreign keys for BelongsTo scopes. A record without the relevant foreign key will not be added to a list.

Although it is not required, you may make models that use the trait implement the `ListifyInterface` for your own purposes. 


## Differences with the original Listify

There is no `attach` artisan command supplied; you are expected to handle your `position` column migrations yourself.

The exceptions that do get thrown have been simplified. `InvalidArgumentException`, `UnexpectedValueException` and `BadMethodCallException` are thrown instead of custom exceptions.
Exceptions are no longer thrown for 'null scope' or 'null foreign key', since these are now expected and allowed. Models with an effective 'null scope' will now silently be excluded from lists. 
  
Note that this package has been tested with the original listify PHPUnit tests and passes them where behavior has not intentionally changed.

Finally, this trait may be used with inheritance (because its base scope is `protected` rather than `private`). You can make a 'BaseListifyModel' and extend that to avoid code (or setup) duplication. 


## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.


## Credits

- [Coen Zimmerman][link-author]
- [All Contributors][link-contributors]

Obviously, the main credits for anything to do with Listify go to:

- [Travis Vignon](https://github.com/lookitsatravis)


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/czim/laravel-listify.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/czim/laravel-listify.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/czim/laravel-listify
[link-downloads]: https://packagist.org/packages/czim/laravel-listify
[link-author]: https://github.com/czim
[link-contributors]: ../../contributors
