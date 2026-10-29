<?php

namespace Tests\Unit;

use App\Support\DateRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    public function test_the_default_is_the_current_year(): void
    {
        $range = DateRange::thisYear(CarbonImmutable::parse('2026-10-30'));

        $this->assertSame(['from' => '2026-01-01', 'to' => '2026-12-31', 'is_default' => true], $range->toArray());
        $this->assertSame(365, $range->days());
    }

    public function test_no_dates_means_the_default_and_both_dates_make_a_custom_range(): void
    {
        $this->assertTrue(DateRange::fromStrings(null, null)->isDefault);
        $this->assertTrue(DateRange::fromStrings('2026-01-01', null)->isDefault);

        $custom = DateRange::fromStrings('2026-03-10', '2026-04-20');
        $this->assertFalse($custom->isDefault);
        $this->assertSame('2026-03-10', $custom->from->toDateString());
        $this->assertSame('2026-04-20', $custom->to->toDateString());
    }

    public function test_the_range_is_inclusive_whole_days(): void
    {
        $range = DateRange::fromStrings('2026-03-01', '2026-03-31');

        $this->assertSame('2026-03-01 00:00:00', $range->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-31 23:59:59', $range->end()->format('Y-m-d H:i:s'));
        $this->assertSame(31, $range->days());
        $this->assertSame(1, DateRange::fromStrings('2026-03-05', '2026-03-05')->days());
    }

    public function test_the_previous_period_has_the_same_length_and_ends_the_day_before(): void
    {
        $previous = DateRange::fromStrings('2026-03-11', '2026-03-20')->previous(); // 10 days

        $this->assertSame('2026-03-01', $previous->from->toDateString());
        $this->assertSame('2026-03-10', $previous->to->toDateString());
        $this->assertSame(10, $previous->days());
    }

    public function test_months_lists_the_months_touched_and_keeps_the_last_twelve(): void
    {
        $range = DateRange::fromStrings('2026-02-15', '2026-04-02');
        $this->assertSame(['2026-02', '2026-03', '2026-04'], array_map(fn ($m) => $m->format('Y-m'), $range->months()));

        $long = DateRange::fromStrings('2024-01-01', '2026-06-30');
        $months = array_map(fn ($m) => $m->format('Y-m'), $long->months(12));
        $this->assertCount(12, $months);
        $this->assertSame('2025-07', $months[0]);
        $this->assertSame('2026-06', $months[11]);
    }
}
