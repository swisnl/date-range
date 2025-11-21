<?php

use Swis\DateRange\DateRange;
use Swis\DateRange\DateRangeSet;

it('converts to array', function () {
    $dateRangeSet = DateRangeSet::make([
        DateRange::make(
            createDate('2021-01-01'),
            createDate('2021-01-31')
        ),
    ]);

    expect($dateRangeSet->toArray())->toEqual([['2021-01-01', '2021-01-31']]);
});

it('converts to array with null dates', function () {
    $dateRangeSet = DateRangeSet::make([
        DateRange::make(
            null,
            createDate('2021-01-31')
        ),
    ]);

    expect($dateRangeSet->toArray())->toEqual([[null, '2021-01-31']]);
});

it('converts to array with multiple ranges', function () {
    $dateRangeSet = DateRangeSet::make([
        DateRange::make(
            createDate('2021-01-01'),
            createDate('2021-01-31')
        ),
        DateRange::make(
            createDate('2022-02-01'),
            createDate('2022-02-28')
        ),
    ]);

    expect($dateRangeSet->toArray())->toEqual([
        ['2021-01-01', '2021-01-31'],
        ['2022-02-01', '2022-02-28'],
    ]);
});

it('joins and reorders ranges', function (array $ranges, array $set) {
    $dateRangeSet = DateRangeSet::fromArray($ranges);

    expect($dateRangeSet->toArray())->toEqual($set);
})->with([
    'overlap' => [
        [
            ['2021-01-01', '2021-01-31'],
            ['2021-01-15', '2021-02-15'],
        ],
        [
            ['2021-01-01', '2021-02-15'],
        ],
    ],
    'overlap with nulls' => [
        [
            [null, '2021-01-31'],
            ['2021-01-15', null],
        ],
        [
            [null, null],
        ],
    ],
    'adjoining' => [
        [
            ['2021-01-01', '2021-01-31'],
            ['2021-02-01', '2021-02-28'],
        ],
        [
            ['2021-01-01', '2021-02-28'],
        ],
    ],
    'adjoining flipped' => [
        [
            ['2021-02-01', '2021-02-28'],
            ['2021-01-01', '2021-01-31'],
        ],
        [
            ['2021-01-01', '2021-02-28'],
        ],
    ],
    'no overlap' => [
        [
            ['2021-01-01', '2021-01-31'],
            ['2021-03-01', '2021-03-31'],
        ],
        [
            ['2021-01-01', '2021-01-31'],
            ['2021-03-01', '2021-03-31'],
        ],
    ],
    'overlap inside null' => [
        [
            [null, '2021-01-31'],
            ['2021-01-01', '2021-01-15'],
        ],
        [
            [null, '2021-01-31'],
        ],
    ],
    'keep only one null on both sides' => [
        [
            [null, '2021-01-01'],
            [null, '2021-01-15'],
            ['2021-03-01', null],
            ['2021-02-15', null],
        ],
        [
            [null, '2021-01-15'],
            ['2021-02-15', null],
        ],
    ],
    'single null range from multiple ranges' => [
        [
            [null, '2021-01-01'],
            [null, '2021-01-15'],
            ['2021-03-01', null],
            ['2021-02-15', null],
            ['2021-01-16', '2021-02-14'],
        ],
        [
            [null, null],
        ],
    ],
]);

it('adds date range sets', function () {
    $set = DateRangeSet::fromArray([[null, '2021-01-01'], ['2021-03-01', null]])
        ->add(DateRangeSet::fromArray([[null, '2021-01-15'], ['2021-02-15', null]]))
        ->add(DateRangeSet::fromArray([['2021-01-16', '2021-02-14']]));

    expect($set->toArray())->toEqual([[null, null]]);
});

it('subtracts', function (array $ranges, array $subtract, array $set) {
    $dateRangeSet = DateRangeSet::fromArray($ranges)
        ->subtract(DateRangeSet::fromArray($subtract));

    expect($dateRangeSet->toArray())->toEqual($set);
})->with([
    'simple' => [
        [
            ['2021-01-01', '2021-02-15'],
        ],
        [
            ['2021-01-01', '2021-01-15'],
        ],
        [
            ['2021-01-16', '2021-02-15'],
        ],
    ],
    'null' => [
        [
            ['2021-01-01', '2021-02-15'],
        ],
        [
            ['2021-01-01', null],
        ],
        [],
    ],
    'null 2' => [
        [
            ['2021-01-01', '2021-02-15'],
        ],
        [
            ['2021-02-01', null],
        ],
        [
            ['2021-01-01', '2021-01-31'],
        ],
    ],
    'multiple' => [
        [
            ['2021-01-01', '2021-02-15'],
            ['2021-03-01', '2021-03-31'],
        ],
        [
            ['2021-01-01', '2021-01-15'],
            ['2021-03-01', '2021-03-31'],
        ],
        [
            ['2021-01-16', '2021-02-15'],
        ],
    ],
    'multiple 2' => [
        [
            ['2021-01-01', '2021-02-15'],
            ['2021-03-01', '2021-03-31'],
        ],
        [
            ['2021-02-01', '2021-03-10'],
            ['2021-03-20', null],
        ],
        [
            ['2021-01-01', '2021-01-31'],
            ['2021-03-11', '2021-03-19'],
        ],
    ],
]);

it('intersects', function (array $ranges, array $intersect, array $set) {
    $dateRangeSet = DateRangeSet::fromArray($ranges)
        ->intersect(DateRangeSet::fromArray($intersect));

    expect($dateRangeSet->toArray())->toEqual($set);
})->with([
    'simple' => [
        [
            ['2021-01-01', '2021-02-15'],
        ],
        [
            ['2021-01-15', '2021-03-01'],
        ],
        [
            ['2021-01-15', '2021-02-15'],
        ],
    ],
    'no overlap' => [
        [
            ['2021-01-01', '2021-01-31'],
        ],
        [
            ['2021-02-01', '2021-02-28'],
        ],
        [],
    ],
    'multiple' => [
        [
            ['2021-01-01', '2021-02-15'],
            ['2021-03-01', '2021-03-31'],
        ],
        [
            ['2021-01-15', '2021-03-10'],
        ],
        [
            ['2021-01-15', '2021-02-15'],
            ['2021-03-01', '2021-03-10'],
        ],
    ],
    'many' => [
        [
            ['2021-01-01', '2021-02-15'],
            ['2021-03-01', '2021-03-31'],
            ['2021-04-01', '2021-04-30'],
        ],
        [
            ['2021-01-10', '2021-01-20'],
            ['2021-03-15', '2021-03-25'],
            ['2021-05-01', '2021-05-31'],
        ],
        [
            ['2021-01-10', '2021-01-20'],
            ['2021-03-15', '2021-03-25'],
        ],
    ],
    'complex overlap' => [
        [
            ['2021-01-01', '2021-02-15'],
            ['2021-03-01', '2021-03-31'],
            ['2021-04-01', '2021-04-30'],
        ],
        [
            ['2021-01-10', '2021-01-20'],
            ['2021-02-01', '2021-03-25'],
        ],
        [
            ['2021-01-10', '2021-01-20'],
            ['2021-02-01', '2021-02-15'],
            ['2021-03-01', '2021-03-25'],
        ],
    ],
    'half open ranges' => [
        [
            [null, '2021-02-15'],
        ],
        [
            ['2021-01-15', null],
        ],
        [
            ['2021-01-15', '2021-02-15'],
        ],
    ],
    'open range' => [
        [
            [null, null],
        ],
        [
            ['2021-01-15', '2021-02-15'],
        ],
        [
            ['2021-01-15', '2021-02-15'],
        ],
    ],
]);
