<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomIsoWeekPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ControlRoomIsoWeekPeriodTest extends TestCase
{
    public function test_minggu_filter_selalu_minggu_sampai_sabtu(): void
    {
        $period = ControlRoomIsoWeekPeriod::of(2026, 36);

        $this->assertSame(7, $period->start->dayOfWeekIso);
        $this->assertSame(6, $period->end->dayOfWeekIso);
        $this->assertSame('2026-08-30 00:00:00', $period->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-05 23:59:59', $period->end->format('Y-m-d H:i:s'));
        $this->assertSame(2026, $period->year);
        $this->assertSame(36, $period->week);
        $this->assertSame('2026-W36', $period->isoWeekValue());
        $this->assertSame('Minggu 30 Agt – Sabtu 05 Sep 2026', $period->rangeLabel());
    }

    public function test_iso_week_dari_query_string(): void
    {
        $period = ControlRoomIsoWeekPeriod::fromRequest(Request::create('/', 'GET', [
            'iso_week' => '2026-W36',
        ]));

        $this->assertSame(2026, $period->year);
        $this->assertSame(36, $period->week);
        $this->assertSame(7, $period->start->dayOfWeekIso);
        $this->assertSame('2026-08-30', $period->start->toDateString());
        $this->assertSame('2026-09-05', $period->end->toDateString());
    }

    public function test_year_week_dari_query_string(): void
    {
        $period = ControlRoomIsoWeekPeriod::fromRequest(Request::create('/', 'GET', [
            'year' => 2026,
            'week' => 36,
        ]));

        $this->assertSame('2026-08-30', $period->start->toDateString());
        $this->assertSame('2026-09-05', $period->end->toDateString());
    }

    public function test_default_tanpa_query_adalah_minggu_lalu_minggu_ke_sabtu(): void
    {
        $default = CarbonImmutable::parse('2026-09-10')
            ->setISODate(2026, 37, 1)
            ->subWeek();
        $period = ControlRoomIsoWeekPeriod::fromRequest(Request::create('/', 'GET'), $default);

        $this->assertSame(7, $period->start->dayOfWeekIso);
        $this->assertSame('2026-08-30', $period->start->toDateString());
        $this->assertSame('2026-09-05', $period->end->toDateString());
    }

    public function test_prev_next_tetap_minggu_ke_sabtu(): void
    {
        $period = ControlRoomIsoWeekPeriod::of(2026, 36);

        $this->assertSame(7, $period->previous()->start->dayOfWeekIso);
        $this->assertSame(7, $period->next()->start->dayOfWeekIso);
        $this->assertSame(6, $period->previous()->end->dayOfWeekIso);
        $this->assertSame(6, $period->next()->end->dayOfWeekIso);
        $this->assertSame('2026-08-23', $period->previous()->start->toDateString());
        $this->assertSame('2026-09-06', $period->next()->start->toDateString());
    }
}
