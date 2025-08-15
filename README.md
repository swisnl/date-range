# Date Range
    
[![Latest Version on Packagist](https://img.shields.io/packagist/v/swisnl/date-range.svg?style=flat-square)](https://packagist.org/packages/swisnl/date-range)
[![Software License](https://img.shields.io/packagist/l/swisnl/date-range.svg?style=flat-square)](LICENSE.md)
[![Buy us a tree](https://img.shields.io/badge/Treeware-%F0%9F%8C%B3-lightgreen.svg?style=flat-square)](https://plant.treeware.earth/swisnl/date-range)
[![Made by SWIS](https://img.shields.io/badge/%F0%9F%9A%80-made%20by%20SWIS-%230737A9.svg?style=flat-square)](https://www.swis.nl)

This PHP package provides classes for immutable objects to represent date ranges and methods to manipulate them.

## Installation

You can install the package via composer:

```bash
composer require swisnl/date-range
```

## Usage

`Swis\DateRange\DateRange` is the main class to represent a date range. It is an immutable object that has a start and
end date (both optional to support open ended ranges), and provides methods to manipulate the range. Because
manipulating date ranges can easily lead to multiple separate date ranges, the package also provides a 
`Swis\DateRange\DateRangeSet` class to represent a collection of date ranges. This class also provides methods to
manipulate set of date ranges. Both classes are immutable, so any manipulation will return a new instance of the class.

```php
use Swis\DateRange\DateRange;

$range = DateRange::make('2023-01-01', '2023-01-31');
$range->inRange('2023-01-15'); // true
$range->inRange('2023-02-01'); // false

$range2 = DateRange::make('2023-01-15', '2023-01-20');
$range->overlaps($range2); // true
$range->intersect($range2)->toArray(); // ['2023-01-15', '2023-01-20']
$range->subtract($range2)->toArray(); // [['2023-01-01', '2023-01-14'], ['2023-01-21', '2023-01-31']]

$set = $range->subtract($range2);
$set->addDateRange(DateRange::make('2023-01-10', null)); // [['2023-01-01', null]]
```

For all the available methods, please refer to the PHPDoc documentation in the source code.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](https://github.com/swisnl/date-range/blob/main/CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/swisnl/date-range/blob/main/CONTRIBUTING.md) and [CODE_OF_CONDUCT](https://github.com/swisnl/date-range/blob/main/CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email security@swis.nl instead of using the issue tracker.

## Credits

- [Rolf van de Krol](https://github.com/rolfvandekrol)
- [All Contributors](https://github.com/swisnl/date-range/contributors)

## License

The MIT License (MIT). Please see [License File](https://github.com/swisnl/date-range/blob/main/LICENSE.md) for more information.

This package is [Treeware](https://treeware.earth). If you use it in production, then we ask that you
[**buy the world a tree**](https://plant.treeware.earth/swisnl/date-range) to thank us for our work. By
contributing to the Treeware forest you’ll be creating employment for local families and restoring wildlife habitats.

## SWIS ❤️ Open Source

[SWIS](https://www.swis.nl) is a web agency from Leiden, the Netherlands. We love working with open source software.
