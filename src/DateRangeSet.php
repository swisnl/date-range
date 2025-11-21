<?php

namespace Swis\DateRange;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * DateRangeSet is a collection of DateRange objects.
 *
 * It keeps the date ranges in order and merges overlapping date ranges, so we
 * can easily find the range that a date falls into. That also means that only
 * the first range can have a null start date, and only the last range can have
 * a null end date.
 *
 * @implements Arrayable<int, array<int, string|null>>
 */
class DateRangeSet implements Arrayable
{
    /**
     * Creates a new DateRangeSet instance from a collection of DateRange
     * objects.
     *
     * This constructor is protected, use the `make` method to create a new
     * instance.
     *
     * @param  Collection<int, DateRange>  $dateRanges
     */
    protected function __construct(
        protected Collection $dateRanges,
    ) {}

    /**
     * Make a new DateRangeSet instance.
     *
     * This method accepts an array (or collection) of date ranges (where each
     * can either be a DateRange object or an array that can be converted to a
     * DateRange object), a single DateRange object, or null.
     *
     * @param  Arrayable<array-key, DateRange|array<int, string|DateTimeInterface|null>>|iterable<array-key, DateRange|array<int, string|DateTimeInterface|null>>|DateRange|null  $ranges
     *
     * @throws InvalidArgumentException if the input is invalid.
     */
    public static function make(Arrayable|iterable|DateRange|null $ranges = []): self
    {
        // To keep the order of the date ranges, we start with an empty set and add each range one by one.

        if ($ranges instanceof DateRange) {
            $ranges = collect([$ranges]);
        }

        /** @var Collection<int, DateRange> $ranges */
        $ranges = collect($ranges)->map(function (mixed $range): DateRange {
            if ($range instanceof DateRange) {
                return $range;
            }

            // @phpstan-ignore-next-line
            if (is_array($range)) {
                return DateRange::fromArray($range);
            }

            // @phpstan-ignore-next-line
            throw new InvalidArgumentException('Invalid date range provided. Expected DateRange object or array.');
        });

        $set = new self(collect());
        foreach ($ranges as $range) {
            $set = $set->addDateRange($range);
        }

        return $set;
    }

    /**
     * Make a new DateRangeSet instance from an array of date ranges.
     *
     * @param  array<array-key, array<int, string|DateTimeInterface|null>|DateRange>  $array
     *                                                                                        Array of date ranges, where each date range can be an array that is
     *                                                                                        accepted by DateRange::fromArray or a DateRange object.
     */
    public static function fromArray(array $array): self
    {
        return self::make($array);
    }

    /**
     * Convert the DateRangeSet to an array of date ranges arrays.
     *
     * @return array<int, array<int, string|null>>
     */
    public function toArray(): array
    {
        return $this->dateRanges->map(fn (DateRange $range) => $range->toArray())->all();
    }

    /**
     * Add a date range to the set.
     *
     * This method will merge overlapping or touching date ranges and keep the
     * set ordered. It returns a new DateRangeSet instance with the updated
     * ranges.
     */
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

    /**
     * Add a DateRangeSet to the current set.
     *
     * This method will add all date ranges from the given DateRangeSet to the
     * current set, merging overlapping or touching date ranges and keeping the
     * set ordered. It returns a new DateRangeSet instance with the updated
     * ranges.
     */
    public function add(DateRangeSet $dateRangeSet): self
    {
        $result = $this;
        foreach ($dateRangeSet->getDateRanges() as $dateRange) {
            $result = $result->addDateRange($dateRange);
        }

        return $result;
    }

    /**
     * Subtract a DateRange from the set.
     *
     * This method will remove the given date range from the set, splitting
     * existing date ranges that overlap with the given range if necessary. It
     * returns a new DateRangeSet instance with the updated ranges.
     */
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

    /**
     * Subtract a DateRangeSet from the current set.
     *
     * This method will subtract all date ranges from the given DateRangeSet
     * from the current set, splitting existing date ranges that overlap with
     * the given ranges if necessary. It returns a new DateRangeSet instance
     * with the updated ranges.
     */
    public function subtract(DateRangeSet $dateRangeSet): self
    {
        $result = $this;
        foreach ($dateRangeSet->getDateRanges() as $dateRange) {
            $result = $result->subtractDateRange($dateRange);
        }

        return $result;
    }

    /**
     * Intersect the current set with another DateRangeSet.
     *
     * This method will return a new DateRangeSet instance containing the
     * intersection of the current set and the given DateRangeSet. It will
     * return an empty set if there are no overlapping ranges.
     */
    public function intersect(DateRangeSet $dateRangeSet): self
    {
        $result = collect();

        $thisRanges = $this->getDateRanges();
        $otherRanges = $dateRangeSet->getDateRanges();

        // Start with the first range in both sets.
        $thisRange = $thisRanges->shift();
        $otherRange = $otherRanges->shift();

        // Loop until we reach the end of either set.
        while ($thisRange && $otherRange) {
            [
                'intersection' => $intersection,
                'after' => $after,
                'after_from_this' => $afterFromThis,
            ] = $thisRange->compare($otherRange);

            if ($intersection) {
                $result = $result->push($intersection);
            }

            // If there is an after range, we need to determine which set it comes from, so we can continue the loop
            // with the correct ranges.
            if ($after) {
                // If the after range comes from this set, we need to consider the potential overlap of the after with
                // the next range in the other set. If it comes from the other set, the same applies vice versa.
                if ($afterFromThis) {
                    $thisRange = $after;
                    $otherRange = $otherRanges->shift();
                } else {
                    $otherRange = $after;
                    $thisRange = $thisRanges->shift();
                }
            } else {
                $thisRange = $thisRanges->shift();
                $otherRange = $otherRanges->shift();
            }
        }

        return new self($result);
    }

    /**
     * Get the date ranges in the set.
     *
     * @return Collection<int, DateRange>
     */
    public function getDateRanges(): Collection
    {
        return $this->dateRanges->values();
    }

    /**
     * Check if the set contains the given date.
     */
    public function inRange(DateTimeInterface|string $date): bool
    {
        return $this->dateRanges->contains(fn (DateRange $range) => $range->inRange($date));
    }

    /**
     * Check if the set is empty.
     */
    public function isEmpty(): bool
    {
        return $this->dateRanges->isEmpty();
    }

    /**
     * Check if the set is not empty.
     *
     * This is a convenience method that returns the opposite of `isEmpty()`.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Convert the set to a collection of CarbonImmutable dates.
     *
     * This method generates a collection of dates that fall within the date
     * range set, including both the start and end dates. If the date range set
     * is not closed (i.e., the first range doesn't have a start date or the
     * last range doesn't have an end date), it will throw an exception.
     *
     * @return Collection<int, CarbonImmutable>
     *
     * @throws InvalidArgumentException if the date range set is not closed.
     */
    public function toDates(): Collection
    {
        return $this->dateRanges->flatMap->toDates();
    }
}
