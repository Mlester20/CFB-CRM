<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The date range of the dashboard (inclusive, whole days). Defaults to the
 * current year.
 */
final class DateRange
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly bool $isDefault = false,
    ) {}

    public static function thisYear(?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now();

        return new self($now->startOfYear()->startOfDay(), $now->endOfYear()->startOfDay(), isDefault: true);
    }

    /** Both ends, or neither for the default. Dates are already validated (Y-m-d). */
    public static function fromStrings(?string $from, ?string $to): self
    {
        if (! $from || ! $to) {
            return self::thisYear();
        }

        $range = new self(CarbonImmutable::createFromFormat('Y-m-d', $from)->startOfDay(), CarbonImmutable::createFromFormat('Y-m-d', $to)->startOfDay());

        return $range->isFullCurrentYear() ? self::thisYear() : $range;
    }

    /** First instant of the first day. */
    public function start(): CarbonImmutable
    {
        return $this->from->startOfDay();
    }

    /** Last instant of the last day. */
    public function end(): CarbonImmutable
    {
        return $this->to->endOfDay();
    }

    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /** The period of the same length that ends the day before this one starts. */
    public function previous(): self
    {
        $days = $this->days();
        $to = $this->from->subDay();

        return new self($to->subDays($days - 1), $to);
    }

    /**
     * The first day of each month the range touches, oldest first, at most the
     * last $max of them.
     *
     * @return list<CarbonImmutable>
     */
    public function months(int $max = 12): array
    {
        $months = [];

        for ($month = $this->from->startOfMonth(); $month <= $this->to; $month = $month->addMonth()) {
            $months[] = $month;
        }

        return array_slice($months, -$max);
    }

    /** @return array{from: string, to: string, is_default: bool} */
    public function toArray(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString(), 'is_default' => $this->isDefault];
    }

    private function isFullCurrentYear(): bool
    {
        $year = CarbonImmutable::now();

        return $this->from->equalTo($year->startOfYear()->startOfDay()) && $this->to->startOfDay()->equalTo($year->endOfYear()->startOfDay());
    }
}
