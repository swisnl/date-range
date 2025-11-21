<?php

namespace Swis\DateRange;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * DateRange represents a range of dates, with optional start and end dates.
 *
 * @implements Arrayable<int, string|null>
 */
class DateRange implements Arrayable
{
    /**
     * Create a new DateRange instance from CarbonImmutable instances.
     *
     * This constructor is protected, use the `make` method to create a new
     * instance.
     */
    protected function __construct(
        protected ?CarbonImmutable $startDate = null,
        protected ?CarbonImmutable $endDate = null,
    ) {}

    /**
     * Make a new DateRange instance.
     *
     * This method allows you to create a DateRange instance with optional start
     * and end dates. The dates can be provided as DateTimeInterface instances
     * (that includes PHP's DateTime and DateTimeImmutable, and the classes from
     * Carbon), or strings that can be parsed by Carbon.
     *
     *
     * @throws InvalidArgumentException if the end date is before the start date
     *                                  or if the date format is invalid.
     */
    public static function make(
        DateTimeInterface|string|null $startDate = null,
        DateTimeInterface|string|null $endDate = null,
    ): self {
        try {
            $startDate = $startDate ? CarbonImmutable::parse($startDate)->startOfDay() : null;
        } catch (InvalidFormatException $e) {
            throw new InvalidArgumentException('Invalid start date format: '.$e->getMessage(), 0, $e);
        }

        try {
            $endDate = $endDate ? CarbonImmutable::parse($endDate)->startOfDay() : null;
        } catch (InvalidFormatException $e) {
            throw new InvalidArgumentException('Invalid end date format: '.$e->getMessage(), 0, $e);
        }

        if ($startDate && $endDate && $endDate->lessThan($startDate)) {
            throw new InvalidArgumentException('End date must be after start date');
        }

        return new self($startDate, $endDate);
    }

    /**
     * Make new DateRange instance that covers a specific year.
     */
    public static function year(int $year): self
    {
        return self::make(
            CarbonImmutable::createFromDate($year, 1, 1),
            CarbonImmutable::createFromDate($year, 12, 31)
        );
    }

    /**
     * Make new DateRange instance that covers a specific month.
     *
     * @param  string|null  $month  The month in 'Y-m' format, e.g. '2023-10'. If
     *                              null, the current month is used.
     *
     * @throws InvalidArgumentException if the month string is invalid.
     */
    public static function fromMonthString(?string $month = null): DateRange
    {
        if ($month) {
            $start = CarbonImmutable::createFromFormat('Y-m', $month);
            if (! $start) {
                throw new InvalidArgumentException('Invalid month string');
            }
        } else {
            $start = CarbonImmutable::now();
        }

        return DateRange::make(
            $start->startOfMonth(),
            $start->endOfMonth()->startOfDay(),
        );
    }

    /**
     * Make a new DateRange instance from an array.
     *
     * This method expects an array with two elements, where the first element
     * is the start date and the second element is the end date. If an element
     * is null or an empty string, it will be treated as a null value. The dates
     * can be provided as strings that can be parsed by Carbon.
     *
     * @param  array<int, string|DateTimeInterface|null>  $array
     *
     * @throws InvalidArgumentException if the end date is before the start date
     *                                  or if the date format is invalid.
     */
    public static function fromArray(array $array): self
    {
        return self::make(
            $array[0] ?: null,
            $array[1] ?: null
        );
    }

    /**
     * Clone the DateRange instance.
     *
     * This method creates a new DateRange instance with cloned start and end
     * dates.
     */
    public function clone(): self
    {
        return self::make(
            $this->getStartDate()?->clone(),
            $this->getEndDate()?->clone()
        );
    }

    /**
     * Convert the DateRange instance to an array.
     *
     * This method returns an array with two elements: the start date and the
     * end date, both formatted as strings in 'Y-m-d' format. If a date is null,
     * it will be represented as null in the array. This array representation
     * can be converted back to a DateRange instance using the `fromArray`
     * method.
     *
     * @return array<int, string|null>
     */
    public function toArray(): array
    {
        return [$this->getStartDate()?->toDateString(), $this->getEndDate()?->toDateString()];
    }

    /**
     * Get the start date of the date range.
     */
    public function getStartDate(): ?CarbonImmutable
    {
        return $this->startDate;
    }

    /**
     * Get the end date of the date range.
     */
    public function getEndDate(): ?CarbonImmutable
    {
        return $this->endDate;
    }

    /**
     * Set the start date of the date range.
     *
     * This method returns a new DateRange instance with the specified start
     * date and the current end date.
     *
     *
     * @throws InvalidArgumentException if the start date is after the end date or if the date format is invalid.
     */
    public function setStartDate(DateTimeInterface|string|null $startDate): self
    {
        return self::make($startDate, $this->getEndDate());
    }

    /**
     * Set the end date of the date range.
     *
     * This method returns a new DateRange instance with the specified end date
     * and the current start date.
     *
     *
     * @throws InvalidArgumentException if the end date is before the start date or if the date format is invalid.
     */
    public function setEndDate(DateTimeInterface|string|null $endDate): self
    {
        return self::make($this->getStartDate(), $endDate);
    }

    /**
     * Check if the date range has a start date.
     *
     * This method returns true if the start date is set, false otherwise.
     */
    public function hasStartDate(): bool
    {
        return (bool) $this->getStartDate();
    }

    /**
     * Check if the date range has an end date.
     *
     * This method returns true if the end date is set, false otherwise.
     */
    public function hasEndDate(): bool
    {
        return (bool) $this->getEndDate();
    }

    /**
     * Check if the date range is closed (has both start and end dates).
     *
     * This method returns true if both start and end dates are set, false
     * otherwise.
     */
    public function isClosed(): bool
    {
        return $this->hasStartDate() && $this->hasEndDate();
    }

    /**
     * Check if the date range is half-open (has either a start or an end date,
     * but not both).
     *
     * This method returns true if one of the dates is set and the other is not,
     * false otherwise.
     */
    public function isHalfOpen(): bool
    {
        return $this->hasStartDate() xor $this->hasEndDate();
    }

    /**
     * Check if the date range is open (has no start and no end date).
     *
     * This method returns true if both start and end dates are not set, false
     * otherwise.
     */
    public function isOpen(): bool
    {
        return ! $this->hasStartDate() && ! $this->hasEndDate();
    }

    /**
     * Check if the date range includes a specific date.
     *
     * This method checks if the given date falls within the date range, taking
     * into account whether the start and end dates are set. If the date range
     * is open (no start and no end date), it always includes any specific date.
     *
     * @param  DateTimeInterface|string  $date  The date to check.
     * @return bool True if the date is included in the range, false otherwise.
     *
     * @throws InvalidArgumentException if the date format is invalid.
     */
    public function inRange(DateTimeInterface|string $date): bool
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        if (! $this->getStartDate()) {
            if (! $this->getEndDate()) {
                // This is an open date range, so it always includes any specific date.
                return true;
            }

            // This is a date range with an end date, but no start date, so it includes the specific date if it's before
            // the end date.
            return $date->lessThanOrEqualTo($this->getEndDate());
        }

        if (! $this->getEndDate()) {
            // This is a date range with a start date, but no end date, so it includes the specific date if it's after
            // the start date.
            return $date->greaterThanOrEqualTo($this->getStartDate());
        }

        // This is a date range with both a start and an end date, so it includes the specific date if it's between the
        // start and end date.
        return $date->betweenIncluded($this->getStartDate(), $this->getEndDate());
    }

    /**
     * Check if this date range overlaps with another date range.
     *
     * This method checks if the two date ranges overlap, taking into account
     * whether they are open, half-open, or closed.
     *
     * @param  self  $dateRange  The other date range to check for overlap.
     * @return bool True if the ranges overlap, false otherwise.
     */
    public function overlaps(self $dateRange): bool
    {
        if (! $this->getStartDate()) {
            if (! $this->getEndDate()) {
                // This is an open date range, so it always overlaps with any other date range.
                return true;
            }

            if (! $dateRange->getStartDate()) {
                // Both ranges are open at the start, so they overlap.
                return true;
            }

            // This range is open at the start, but has an end date, so it overlaps with the other range if the other
            // range starts before the end date of this range.
            return $dateRange->getStartDate()->lessThanOrEqualTo($this->getEndDate());
        }

        if (! $this->getEndDate()) {
            if (! $dateRange->getEndDate()) {
                // Both ranges are open at the end, so they overlap.
                return true;
            }

            // This range is open at the end, but has a start date, so it overlaps with the other range if the other
            // range ends after the start date of this range.
            return $dateRange->getEndDate()->greaterThanOrEqualTo($this->getStartDate());
        }

        if (! $dateRange->getStartDate()) {
            if (! $dateRange->getEndDate()) {
                // The other range is an open date range, so it always overlaps with this date range.
                return true;
            }

            // This range has a start and end date, but the other range is open at the start, so it overlaps if the
            // start date of this range is before the end date of the other range.
            return $this->getStartDate()->lessThanOrEqualTo($dateRange->getEndDate());
        }

        if (! $dateRange->getEndDate()) {
            // This range has a start and end date, but the other range is open at the end, so it overlaps if the end
            // date of this range is after the start date of the other range.
            return $this->getEndDate()->greaterThanOrEqualTo($dateRange->getStartDate());
        }

        // Both ranges have start and end dates, so they overlap if the start date of this range is before the end date
        // of the other range, and the end date of this range is after the start date of the other range.
        return $this->getStartDate()->lessThanOrEqualTo($dateRange->getEndDate())
            && $this->getEndDate()->greaterThanOrEqualTo($dateRange->getStartDate());
    }

    /**
     * Compare this date range and another date range.
     *
     * This method compares two date ranges and returns an array containing the
     * following keys:
     * - 'before': A DateRange representing the part of one of the ranges that
     *   is before the intersection, or null if there is no such part.
     * - 'before_from_this': A boolean indicating whether the 'before' range
     *   belongs to this date range (true) or the other date range (false) or
     *   null if there is no 'before' range.
     * - 'intersection': A DateRange representing the intersection of the two
     *   ranges, or null if there is no intersection.
     * - 'after': A DateRange representing the part of one of the ranges that is
     *   after the intersection, or null if there is no such part.
     * - 'after_from_this': A boolean indicating whether the 'after' range
     *   belongs to this date range (true) or the other date range (false) or
     *   null if there is no 'after' range.
     *
     * This method is most useful with array destructuring to easily access the
     * parts of the comparison result that you need. Look at
     * DateRange::subtract() and DateRangeSet::intersect() for examples of how
     * to use this method.
     *
     * This operation is mostly symmetric, so comparing A with B yields the same
     * result as comparing B with A, except for the before_from_this and
     * before_from_this flags (they are inverted).
     *
     * @param  DateRange  $dateRange  The other date range to compare with.
     * @return array{
     *     before: ?DateRange,
     *     before_from_this: ?bool,
     *     intersection: ?DateRange,
     *     after: ?DateRange,
     *     after_from_this: ?bool
     * } The comparison result.
     */
    public function compare(self $dateRange): array
    {
        if (! $this->getStartDate()) {
            if (! $dateRange->getStartDate()) {
                // Both ranges are open at the start, so the intersection is open at the start. That means there is no
                // 'before' range. This also means that there will definitely be an intersection and the order between
                // the two ranges does not matter.
                $startDate = null;
                $before = null;
                $startDateOrder = 0;
            } else {
                // This range is open at the start, and the other range has a start date, so the intersection starts at
                // the start date of the other range. The 'before' range is from open to the day before the start date.
                // This range starts before the other range, so we keep track of the order.
                $startDate = $dateRange->getStartDate();
                $before = new self(null, $startDate->subDay()->startOfDay());
                $startDateOrder = -1;
            }
        } else {
            if (! $dateRange->getStartDate()) {
                // This range has a start date, and the other range is open at the start, so the intersection starts at
                // the start date of this range. The 'before' range is from open to the day before the start date. The
                // other range starts before this range, so we keep track of the order.
                $startDate = $this->getStartDate();
                $before = new self(null, $startDate->subDay()->startOfDay());
                $startDateOrder = 1;
            } else {
                // Both ranges have a start date, so the intersection starts at the latest of the two start dates. The
                // 'before' range is from the earliest start date to the day before the intersection start date. We keep
                // track the order based on which range has the earlier start date.
                $startDateOrder = $this->getStartDate() <=> $dateRange->getStartDate();
                if ($startDateOrder < 0) {
                    $startDate = $dateRange->getStartDate();
                    $before = new self($this->getStartDate(), $startDate->subDay()->startOfDay());
                } elseif ($startDateOrder > 0) {
                    $startDate = $this->getStartDate();
                    $before = new self($dateRange->getStartDate(), $startDate->subDay()->startOfDay());
                } else {
                    $startDate = $this->getStartDate();
                    $before = null;
                }
            }
        }

        if (! $this->getEndDate()) {
            if (! $dateRange->getEndDate()) {
                // Both ranges are open at the end, so the intersection is open at the end. That means there is no
                // 'after' range.
                $endDate = null;
                $after = null;
                $endDateOrder = 0;
            } else {
                // This range is open at the end, and the other range has an end date, so the intersection ends at the
                // end date of the other range. The 'after' range is from the day after the end date to open.
                $endDate = $dateRange->getEndDate();
                $after = new self($endDate->addDay()->startOfDay(), null);
                $endDateOrder = 1;
            }
        } else {
            if (! $dateRange->getEndDate()) {
                // This range has an end date, and the other range is open at the end, so the intersection ends at the
                // end date of this range. The 'after' range is from the day after the end date to open.
                $endDate = $this->getEndDate();
                $after = new self($endDate->addDay()->startOfDay(), null);
                $endDateOrder = -1;
            } else {
                // Both ranges have an end date, so the intersection ends at the earliest of the two end dates. The
                // 'after' range is from the day after the intersection end date to the latest end date.
                $endDateOrder = $this->getEndDate() <=> $dateRange->getEndDate();
                if ($endDateOrder < 0) {
                    $endDate = $this->getEndDate();
                    $after = new self($endDate->addDay()->startOfDay(), $dateRange->getEndDate());
                } elseif ($endDateOrder > 0) {
                    $endDate = $dateRange->getEndDate();
                    $after = new self($endDate->addDay()->startOfDay(), $this->getEndDate());
                } else {
                    $endDate = $dateRange->getEndDate();
                    $after = null;
                }
            }
        }

        if ($startDate && $endDate && $endDate->lessThan($startDate)) {
            if ($startDateOrder < 0) {
                // No intersection, this range is before the other range.
                return [
                    'before' => $this->clone(),
                    'before_from_this' => true,
                    'intersection' => null,
                    'after' => $dateRange->clone(),
                    'after_from_this' => false,
                ];
            } else {
                // No intersection, this range is after the other range.
                return [
                    'before' => $dateRange->clone(),
                    'before_from_this' => false,
                    'intersection' => null,
                    'after' => $this->clone(),
                    'after_from_this' => true,
                ];
            }
        }

        return [
            'before' => $before,
            'before_from_this' => $before ? ($startDateOrder < 0) : null,
            'intersection' => new self($startDate, $endDate),
            'after' => $after,
            'after_from_this' => $after ? ($endDateOrder > 0) : null,
        ];
    }

    /**
     * Get the intersection of this date range with another date range.
     *
     * This method returns a new DateRange instance that represents the overlap
     * between the two date ranges. If there is no overlap, it returns null.
     *
     * @param  self  $dateRange  The other date range to intersect with.
     * @return self|null The intersection of the two date ranges, or null if
     *                   there is no overlap.
     */
    public function intersect(self $dateRange): ?self
    {
        if (! $this->getStartDate()) {
            if (! $dateRange->getStartDate()) {
                // Both ranges are open at the start, so the intersection is open at the start.
                $startDate = null;
            } else {
                // This range is open at the start, and the other range has a start date, so the intersection starts at
                // the start date of the other range.
                $startDate = $dateRange->getStartDate();
            }
        } else {
            if (! $dateRange->getStartDate()) {
                // This range has a start date, and the other range is open at the start, so the intersection starts at
                // the start date of this range.
                $startDate = $this->getStartDate();
            } else {
                // Both ranges have a start date, so the intersection starts at the latest of the two start dates.
                $startDate = max($this->getStartDate(), $dateRange->getStartDate());
            }
        }

        if (! $this->getEndDate()) {
            if (! $dateRange->getEndDate()) {
                // Both ranges are open at the end, so the intersection is open at the end.
                $endDate = null;
            } else {
                // This range is open at the end, and the other range has an end date, so the intersection ends at the
                // end date of the other range.
                $endDate = $dateRange->getEndDate();
            }
        } else {
            if (! $dateRange->getEndDate()) {
                // This range has an end date, and the other range is open at the end, so the intersection ends at the
                // end date of this range.
                $endDate = $this->getEndDate();
            } else {
                // Both ranges have an end date, so the intersection ends at the earliest of the two end dates.
                $endDate = min($this->getEndDate(), $dateRange->getEndDate());
            }
        }

        if ($startDate && $endDate && $endDate->lessThan($startDate)) {
            return null;
        }

        return new self($startDate, $endDate);
    }

    /**
     * Subtract another date range from this date range.
     *
     * This method returns a DateRangeSet containing the parts of this date
     * range that do not overlap with the given date range. If there is no
     * overlap, it returns a DateRangeSet containing this date range. The
     * DataRangeSet can contain zero, one, or two date ranges.
     *
     * @param  DateRange  $dateRange  The date range to subtract.
     * @return DateRangeSet The resulting set of date ranges after subtraction.
     */
    public function subtract(DateRange $dateRange): DateRangeSet
    {
        [
            'before' => $before,
            'before_from_this' => $beforeFromThis,
            'after' => $after,
            'after_from_this' => $afterFromThis,
        ] = $this->compare($dateRange);

        return DateRangeSet::make(array_filter([
            $beforeFromThis ? $before : null,
            $afterFromThis ? $after : null,
        ]));
    }

    /**
     * Check if this date range is equal to another date range.
     *
     * This method checks if both the start and end dates of the two date ranges
     * are equal. If both dates are null, the ranges are considered equal.
     *
     * @param  self  $dateRange  The other date range to compare with.
     */
    public function equals(self $dateRange): bool
    {
        return
            $this->getStartDate() == $dateRange->getStartDate()
            && $this->getEndDate() == $dateRange->getEndDate();
    }

    /**
     * Convert the date range to a collection of CarbonImmutable dates.
     *
     * This method generates a collection of dates that fall within the date
     * range, including both the start and end dates. If the date range is not
     * closed (i.e., it does not have both a start and an end date), it will
     * throw an exception.
     *
     * @return Collection<int, CarbonImmutable>
     *
     * @throws InvalidArgumentException if the date range is not closed.
     */
    public function toDates(): Collection
    {
        if (! $this->getStartDate() || ! $this->getEndDate()) {
            throw new InvalidArgumentException('Date range is not closed.');
        }

        $dates = [];
        $date = $this->getStartDate();
        while ($date->lessThanOrEqualTo($this->getEndDate())) {
            $dates[] = $date;
            $date = $date->addDay();
        }

        return collect($dates);
    }

    /**
     * Get the length of the date range in days.
     *
     * This method calculates the number of days in the date range, including
     * both the start and end dates. If the date range is not closed (i.e., it
     * does not have both a start and an end date), it will throw an exception.
     *
     * @return int The length of the date range in days.
     *
     * @throws InvalidArgumentException if the date range is not closed.
     */
    public function lengthInDays(): int
    {
        if (! $this->getStartDate() || ! $this->getEndDate()) {
            throw new InvalidArgumentException('Date range is not closed.');
        }

        return ((int) $this->getStartDate()->diffInDays($this->getEndDate())) + 1;
    }
}
