# Laravel Listify

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status](https://travis-ci.org/czim/laravel-listify.svg?branch=master)](https://travis-ci.org/czim/laravel-listify)
[![Latest Stable Version](http://img.shields.io/packagist/v/czim/laravel-listify.svg)](https://packagist.org/packages/czim/laravel-listify)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/7d80b8fa-5647-40c6-b5bb-d9583398d128/mini.png)](https://insight.sensiolabs.com/projects/7d80b8fa-5647-40c6-b5bb-d9583398d128)

This is a rebuild (from scratch) of [Lookitsatravis' Listify](https://github.com/lookitsatravis/listify) for personal reasons.
It uses the same interface as Listify, so switching from that to this package should not break anything.


## Version Compatibility

 Laravel         | Package 
:----------------|:--------
 5.7 and older   | 1.0
 5.8 - 5.9       | 1.1
 6.0 and up      | 2.0
 
 
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


## Dealing with global scopes

When using global scopes on a listified model, this may break expected functionality, especially when the global scope affects how the records are ordered. To deal with this, listify will check for a method to clean up the scope as required. To use this, simply add an implementation of the following method to your listified model class:

```php
    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function cleanListifyScopedQuery($query)
    {
        // Return the query builder after applying the necessary cleanup
        // operations on it. In this example, a global scope ordering the
        // records by their position column is disabled by using unordered()
        return $query->unordered();
    }
```

This method will be called any time that listify performs checks and operations on the model 
for which it needs access (and its own ordering).


## To Do

- The way string (and QueryBuilder) scopes work is slightly strange. 
  Adding new records that fall outside of the scope will be added with a position value.
  Those records are reported as being in a list, and odd things can happen when manipulating them.


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
