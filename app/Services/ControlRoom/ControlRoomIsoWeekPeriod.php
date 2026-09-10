<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Periode filter Control Room: Minggu–Sabtu.
 * Nomor minggu tetap ISO (picker HTML type=week), rentang operasional
 * dimulai Minggu sebelum Senin ISO sampai Sabtu.
 */
final class ControlRoomIsoWeekPeriod
{
    public function __construct(
        public readonly int $year,
        public readonly int $week,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    public static function fromRequest(Request $request, ?CarbonImmutable $defaultIsoMonday = null): self
    {
        $defaultIsoMonday ??= CarbonImmutable::now()
            ->setISODate((int) now()->isoWeekYear(), (int) now()->isoWeek(), 1)
            ->subWeek()
            ->startOfDay();

        $isoWeek = strtoupper(trim((string) $request->input('iso_week', '')));
        if (preg_match('/^(\d{4})-W(\d{1,2})$/', $isoWeek, $matches) === 1) {
            return self::of((int) $matches[1], (int) $matches[2]);
        }

        $year = (int) $request->integer('year', (int) $defaultIsoMonday->isoWeekYear());
        $week = (int) $request->integer('week', (int) $defaultIsoMonday->isoWeek());

        return self::of($year, $week);
    }

    public static function of(int $year, int $week): self
    {
        $week = max(1, min(53, $week));
        $monday = CarbonImmutable::now()->setISODate($year, $week, 1)->startOfDay();

        return self::fromSunday($monday->subDay());
    }

    public function previous(): self
    {
        return self::fromSunday($this->start->subWeek());
    }

    public function next(): self
    {
        return self::fromSunday($this->start->addWeek());
    }

    public function isoWeekValue(): string
    {
        return sprintf('%04d-W%02d', $this->year, $this->week);
    }

    public function rangeLabel(): string
    {
        return $this->start->locale('id')->translatedFormat('l d M')
            .' – '.$this->end->locale('id')->translatedFormat('l d M Y');
    }

    /**
     * @return array{
     *     year: int,
     *     week: int,
     *     isoWeekValue: string,
     *     weekRangeLabel: string,
     *     weekStart: CarbonImmutable,
     *     weekEnd: CarbonImmutable,
     *     prevYear: int,
     *     prevWeek: int,
     *     nextYear: int,
     *     nextWeek: int
     * }
     */
    public function viewData(): array
    {
        $prev = $this->previous();
        $next = $this->next();

        return [
            'year' => $this->year,
            'week' => $this->week,
            'isoWeekValue' => $this->isoWeekValue(),
            'weekRangeLabel' => $this->rangeLabel(),
            'weekStart' => $this->start,
            'weekEnd' => $this->end,
            'prevYear' => $prev->year,
            'prevWeek' => $prev->week,
            'nextYear' => $next->year,
            'nextWeek' => $next->week,
        ];
    }

    private static function fromSunday(CarbonImmutable $sunday): self
    {
        $sunday = $sunday->startOfDay();
        $monday = $sunday->addDay();

        return new self(
            (int) $monday->isoWeekYear(),
            (int) $monday->isoWeek(),
            $sunday,
            $sunday->addDays(6)->endOfDay(),
        );
    }
}
