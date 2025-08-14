<?php

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;

function createDate(string $value): CarbonImmutable
{
    $date = CarbonImmutable::createFromFormat('Y-m-d', $value);

    if (! $date) {
        throw new InvalidArgumentException('Invalid date');
    }

    return $date->startOfDay();
}

function constraintFromBoolean(bool $value): Constraint
{
    return $value ? Assert::isTrue() : Assert::isFalse();
}
