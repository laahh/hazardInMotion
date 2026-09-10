<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomIsoWeekPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ControlRoomIsoWeekPeriodTest extends TestCase
{
    public function test_minggu_iso_selalu_senin_sampai_minggu(): void
    {
        $period = ControlRoomIsoWeekPeriod::of(2026, 36);

        $this->assertSame(1, $period->start->dayOfWeekIso);
        $this->assertSame(7, $period->end->dayOfWeekIso);
        $this->assertSame('2026-08-31 00:00:00', $period->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-06 23:59:59', $period->end->format('Y-m-d H:i:s'));
        $this->assertSame('2026-W36', $period->isoWeekValue());
        $this->assertSame('Senin 31 Agt – Minggu 06 Sep 2026', $period->rangeLabel());
    }

    public function test_iso_week_dari_query_string(): void
    {
        $period = ControlRoomIsoWeekPeriod::fromRequest(Request::create('/', 'GET', [
            'iso_week' => '2026-W36',
        ]));

        $this->assertSame(2026, $period->year);
        $this->assertSame(36, $period->week);
        $this->assertSame(1, $period->start->dayOfWeekIso);
    }

    public function test_year_week_dari_query_string(): void
    {
        $period = ControlRoomIsoWeekPeriod::fromRequest(Request::create('/', 'GET', [
            'year' => 2026,
            'week' => 36,
        ]));

        $this->assertSame('2026-08-31', $period->start->toDateString());
        $this->assertSame('2026-09-06', $period->end->toDateString());
    }

    public function test_default_tanpa_query_adalah_minggu_lalu_senin(): void
    {
        $default = CarbonImmutable::parse('2026-09-10')
            ->setISODate(2026, 37, 1)
            ->subWeek();
        $period = ControlRoomIsoWeekPeriod::fromRequest(Request::create('/', 'GET'), $default);

        $this->assertSame(1, $period->start->dayOfWeekIso);
        $this->assertSame('2026-08-31', $period->start->toDateString());
    }

    public function test_prev_next_tetap_senin(): void
    {
        $period = ControlRoomIsoWeekPeriod::of(2026, 36);

        $this->assertSame(1, $period->previous()->start->dayOfWeekIso);
        $this->assertSame(1, $period->next()->start->dayOfWeekIso);
        $this->assertSame('2026-08-24', $period->previous()->start->toDateString());
        $this->assertSame('2026-09-07', $period->next()->start->toDateString());
    }
}
