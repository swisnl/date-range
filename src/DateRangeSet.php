<?php

namespace Swis\DateRange;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * @implements Arrayable<int, array<int, string|null>>
 */
class DateRangeSet implements Arrayable
{
    /**
     * @param  Collection<int, DateRange>  $dateRanges
     */
    protected function __construct(
        protected Collection $dateRanges,
    ) {}

    /**
     * @param  Arrayable<array-key, DateRange>|iterable<array-key, DateRange>|DateRange|null  $ranges
     */
    public static function make(Arrayable|iterable|DateRange|null $ranges = []): self
    {
        // We want to keep the date ranges in order, so we can easily find the range that a date falls into. Also, we
        // want to make sure that there are no overlapping ranges. This also means that only the first range can have a
        // null start date, and only the last range can have a null end date.

        if ($ranges instanceof DateRange) {
            $ranges = collect([$ranges]);
        }

        $ranges = collect($ranges);

        $set = new self(collect());
        /** @var Collection<int, DateRange> $ranges */
        foreach ($ranges as $range) {
            $set = $set->addDateRange($range);
        }

        return $set;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    public function toArray()
    {
        return $this->dateRanges->map(fn (DateRange $range) => $range->toArray())->all();
    }

    /**
     * @param  array<array-key, array<int, string|null>>  $array
     */
    public static function fromArray(array $array): self
    {
        return self::make(collect($array)->map(fn (array $range) => DateRange::fromArray($range))->all());
    }

    public function addDateRange(DateRange $dateRange): self
    {
        // We split all the ranges in two parts: the ones that start before the new range and the ones that start after
        // the start of the new range. The new range can only overlap with the last range in the first part and the
        // ranges in the second part. If the new range has no start date, there are no ranges before it.
        /** @var Collection<int, DateRange> $before */
        /** @var Collection<int, DateRange> $after */
        [$before, $after] = $this->dateRanges->partition(function (DateRange $range) use ($dateRange) {
            return $dateRange->getStartDate() && (! $range->getStartDate() || ($range->getStartDate() < $dateRange->getStartDate()));
        });

        if ($before->isNotEmpty()) {
            /** @var DateRange $lastBefore */
            $lastBefore = $before->last();

            if (! $lastBefore->getEndDate()) {
                // If the last range before the new range has no end date, the new range is already covered by the
                // existing ranges, so we don't need to add it.
                return $this;
            }

            // We know the new range has a start date (otherwise there wouldn't be any ranges before it).
            // @phpstan-ignore-next-line
            if ($lastBefore->getEndDate() >= $dateRange->getStartDate()->subDay()) {
                // The new range overlaps with the last range before it, so we merge them into a single range.
                $before->pop();

                // The last range before the new range stars before the new range (otherwise it wouldn't be in the
                // $before partition), so we can safely use its start date.
                $dateRange = $dateRange->setStartDate($lastBefore->getStartDate());

                // If the new range has no end date, we don't need to set an end date. Otherwise, we set the end date to
                // the last end date from both ranges.
                if ($dateRange->getEndDate() && $lastBefore->getEndDate() > $dateRange->getEndDate()) {
                    $dateRange = $dateRange->setEndDate($lastBefore->getEndDate());
                }
            }
        }

        if (! $dateRange->getEndDate()) {
            // If the new range is open-ended, we overlap with all the ranges in the $after partition, so we can drop
            // them all.
            return new self($before->concat([$dateRange]));
        }

        // We keep looping as long as the first range in the $after partition overlaps with the new range.
        while ($after->isNotEmpty() && (! $after->first()->getStartDate() || ($after->first()->getStartDate()->subDay() <= $dateRange->getEndDate()))) {
            // We remove the first range from the $after partition, so we can merge it with the new range.
            /** @var DateRange $firstAfter */
            $firstAfter = $after->shift();

            if (! $firstAfter->getEndDate()) {
                // If the first range after the new range is open-ended, we make the new range open-ended as well. We
                // don't need to check the other ranges in the $after partition (there shouldn't be any).
                $dateRange = $dateRange->setEndDate(null);

                return new self($before->concat([$dateRange]));
            }

            // The new range overlaps with the first range after it, so we merge them into a single range. If the first
            // range after the new range end after the new range ends, we extend the new range to the end of the first
            // range after it.
            if ($firstAfter->getEndDate() > $dateRange->getEndDate()) {
                $dateRange = $dateRange->setEndDate($firstAfter->getEndDate());
            }
        }

        // At this point the new range is not open-ended, and we removed all overlapping ranges from the $after. So we
        // can add it to the $before partition, and we add the remaining ranges from the $after partition.
        return new self($before->concat([$dateRange])->concat($after->all()));
    }

    public function add(DateRangeSet $dateRangeSet): self
    {
        $result = $this;
        foreach ($dateRangeSet->getDateRanges() as $dateRange) {
            $result = $result->addDateRange($dateRange);
        }

        return $result;
    }

    public function subtractDateRange(DateRange $dateRange): self
    {
        // We split all the ranges in two parts: the ones that start before the range to remove and the ones that start
        // after the start of the range to remove. The range to remove can only overlap with the last range in the first
        // part and the ranges in the second part. If the range to remove has no start date, there are no ranges before
        // it.
        /** @var Collection<int, DateRange> $before */
        /** @var Collection<int, DateRange> $after */
        [$before, $after] = $this->dateRanges->partition(function (DateRange $range) use ($dateRange) {
            return $dateRange->getStartDate() && (! $range->getStartDate() || ($range->getStartDate() < $dateRange->getStartDate()));
        });

        $insert = collect();
        if ($before->isNotEmpty()) {
            /** @var DateRange $lastBefore */
            $lastBefore = $before->pop();

            $insert = $insert->concat($lastBefore->subtract($dateRange)->getDateRanges()->all());
        }

        if (! $dateRange->getEndDate()) {
            // If the range to remove has no end date, we don't need to check the ranges in the $after partition,
            // because they are all covered by the range to remove.
            return new self($before->concat($insert->all()));
        }

        // We keep looping as long as the first range in the $after partition overlaps with the range to remove.
        while ($after->isNotEmpty() && (! $after->first()->getStartDate() || ($after->first()->getStartDate() <= $dateRange->getEndDate()))) {
            // We remove the first range from the $after partition, so we can subtract it from the range to remove.
            /** @var DateRange $firstAfter */
            $firstAfter = $after->shift();

            $insert = $insert->concat($firstAfter->subtract($dateRange)->getDateRanges()->all());
        }

        return new self($before->concat($insert->all())->concat($after->all()));
    }

    public function subtract(DateRangeSet $dateRangeSet): self
    {
        $result = $this;
        foreach ($dateRangeSet->getDateRanges() as $dateRange) {
            $result = $result->subtractDateRange($dateRange);
        }

        return $result;
    }

    public function intersect(DateRangeSet $dateRangeSet): self
    {
        $result = new self(collect());

        // There is probably a more efficient way to do this (because both sets are ordered), but this is the easiest
        // way to implement it. Since usually there are only a few ranges in a set, this should be fine for now.
        foreach ($dateRangeSet->getDateRanges() as $dateRange) {
            foreach ($this->getDateRanges() as $range) {
                $intersection = $range->intersect($dateRange);
                if ($intersection) {
                    $result = $result->addDateRange($intersection);
                }
            }
        }

        return $result;
    }

    /**
     * @return Collection<int, DateRange>
     */
    public function getDateRanges(): Collection
    {
        return $this->dateRanges->values();
    }

    public function inRange(CarbonImmutable $date): bool
    {
        return $this->dateRanges->contains(fn (DateRange $range) => $range->inRange($date));
    }

    public function isEmpty(): bool
    {
        return $this->dateRanges->isEmpty();
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    public function toDates(): Collection
    {
        return $this->dateRanges->flatMap->toDates();
    }
}
