# Changelog

## Laravel 6.0 and up

### [2.0.3] - 2019-10-29

Added support for inserting newly created models into a specific position.
Records created with a `position` value will now be inserted at that position after creation. 

### [2.0.2] - 2019-10-18

Fixed an issue with the ignore ID condition used in `reorderPositionsOnItemsBetween()`.
(2.0.1 is an imperfect fix, replaced by a neater one in this version.) 

### [2.0.0] - 2019-09-21

Support for Laravel 6.0 and PHP 7.2+ only.
Added strict return types and scalar typehints.

## Laravel 5.8 and below


[2.0.3]: https://github.com/czim/laravel-listify/compare/2.0.3...2.0.2
[2.0.2]: https://github.com/czim/laravel-listify/compare/2.0.2...2.0.0
[2.0.0]: https://github.com/czim/laravel-listify/compare/2.0.0...1.1.1
