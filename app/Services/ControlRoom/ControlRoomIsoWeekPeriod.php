<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Periode filter Control Room: ISO week Senin–Minggu.
 */
final class ControlRoomIsoWeekPeriod
{
    public function __construct(
        public readonly int $year,
        public readonly int $week,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    public static function fromRequest(Request $request, ?CarbonImmutable $defaultMonday = null): self
    {
        $defaultMonday ??= CarbonImmutable::now()
            ->setISODate((int) now()->isoWeekYear(), (int) now()->isoWeek(), 1)
            ->subWeek()
            ->startOfDay();

        $isoWeek = strtoupper(trim((string) $request->input('iso_week', '')));
        if (preg_match('/^(\d{4})-W(\d{1,2})$/', $isoWeek, $matches) === 1) {
            return self::of((int) $matches[1], (int) $matches[2]);
        }

        $year = (int) $request->integer('year', (int) $defaultMonday->isoWeekYear());
        $week = (int) $request->integer('week', (int) $defaultMonday->isoWeek());

        return self::of($year, $week);
    }

    public static function of(int $year, int $week): self
    {
        $week = max(1, min(53, $week));
        $start = CarbonImmutable::now()->setISODate($year, $week, 1)->startOfDay();

        return new self(
            (int) $start->isoWeekYear(),
            (int) $start->isoWeek(),
            $start,
            $start->addDays(6)->endOfDay(),
        );
    }

    public function previous(): self
    {
        return self::fromMonday($this->start->subWeek());
    }

    public function next(): self
    {
        return self::fromMonday($this->start->addWeek());
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

    private static function fromMonday(CarbonImmutable $monday): self
    {
        $monday = $monday->startOfDay();

        return new self(
            (int) $monday->isoWeekYear(),
            (int) $monday->isoWeek(),
            $monday,
            $monday->addDays(6)->endOfDay(),
        );
    }
}
