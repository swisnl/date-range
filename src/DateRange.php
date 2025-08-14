<?php

namespace Swis\DateRange;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @implements Arrayable<int, string|null>
 */
class DateRange implements Arrayable
{
    protected function __construct(
        protected ?CarbonImmutable $startDate = null,
        protected ?CarbonImmutable $endDate = null,
    ) {}

    public static function make(
        DateTimeInterface|string|null $startDate = null,
        DateTimeInterface|string|null $endDate = null,
    ): self {
        $startDate = $startDate ? CarbonImmutable::parse($startDate)->startOfDay() : null;
        $endDate = $endDate ? CarbonImmutable::parse($endDate)->startOfDay() : null;

        if ($startDate && $endDate && $endDate->lessThan($startDate)) {
            throw new InvalidArgumentException('End date must be after start date');
        }

        return new self($startDate, $endDate);
    }

    public static function year(int $year): self
    {
        return self::make(
            CarbonImmutable::createFromDate($year, 1, 1),
            CarbonImmutable::createFromDate($year, 12, 31)
        );
    }

    public function clone(): self
    {
        return self::make(
            $this->getStartDate()?->clone(),
            $this->getEndDate()?->clone()
        );
    }

    /**
     * @return array<int, string|null>
     */
    public function toArray(): array
    {
        return [$this->getStartDate()?->toDateString(), $this->getEndDate()?->toDateString()];
    }

    /**
     * @param  array<int, string|null>  $array
     */
    public static function fromArray(array $array): self
    {
        return self::make(
            $array[0] ?: null,
            $array[1] ?: null
        );
    }

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

    public function getStartDate(): ?CarbonImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): ?CarbonImmutable
    {
        return $this->endDate;
    }

    public function setStartDate(?DateTimeInterface $startDate): self
    {
        return self::make($startDate, $this->getEndDate());
    }

    public function setEndDate(?DateTimeInterface $endDate): self
    {
        return self::make($this->getStartDate(), $endDate);
    }

    public function hasStartDate(): bool
    {
        return (bool) $this->getStartDate();
    }

    public function hasEndDate(): bool
    {
        return (bool) $this->getEndDate();
    }

    public function isClosed(): bool
    {
        return $this->hasStartDate() && $this->hasEndDate();
    }

    public function isHalfOpen(): bool
    {
        return $this->hasStartDate() xor $this->hasEndDate();
    }

    public function isOpen(): bool
    {
        return ! $this->hasStartDate() && ! $this->hasEndDate();
    }

    public function inRange(DateTimeInterface $date): bool
    {
        $date = CarbonImmutable::instance($date)->startOfDay();

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

    public function subtract(DateRange $dateRange): DateRangeSet
    {
        $intersection = $this->intersect($dateRange);

        if (! $intersection) {
            return DateRangeSet::make([$this]);
        }

        $before = null;
        $after = null;
        if (! $this->getStartDate()) {
            if ($intersection->getStartDate()) {
                // If the intersection has a start date, we keep the range before the intersection.
                $before = new self(null, $intersection->getStartDate()->subDay());
            }
        } else {
            // If this range has a start date, the intersection must have a start date as well.
            if ($this->getStartDate() < $intersection->getStartDate()) {
                $before = new self($this->getStartDate(), $intersection->getStartDate()->subDay());
            }
        }

        if (! $this->getEndDate()) {
            if ($intersection->getEndDate()) {
                // If the intersection has an end date, we keep the range after the intersection.
                $after = new self($intersection->getEndDate()->addDay(), null);
            }
        } else {
            // If this range has an end date, the intersection must have an end date as well.
            if ($this->getEndDate() > $intersection->getEndDate()) {
                // @phpstan-ignore-next-line
                $after = new self($intersection->getEndDate()->addDay(), $this->getEndDate());
            }
        }

        return DateRangeSet::make(array_filter([$before, $after]));
    }

    public function equals(self $dateRange): bool
    {
        return
            $this->getStartDate() == $dateRange->getStartDate()
            && $this->getEndDate() == $dateRange->getEndDate();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    public function toDates(): Collection
    {
        if (! $this->getStartDate() || ! $this->getEndDate()) {
            throw new InvalidArgumentException('Date range is not fully defined');
        }

        $dates = [];
        $date = $this->getStartDate();
        while ($date->lessThanOrEqualTo($this->getEndDate())) {
            $dates[] = $date;
            $date = $date->addDay();
        }

        return collect($dates);
    }

    public function lengthInDays(): int
    {
        if (! $this->getStartDate() || ! $this->getEndDate()) {
            throw new InvalidArgumentException('Date range is not fully defined');
        }

        return ((int) $this->getStartDate()->diffInDays($this->getEndDate())) + 1;
    }
}
