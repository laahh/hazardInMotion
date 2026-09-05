<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Metrics;

use App\Enums\ControlRoomShiftCode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * plan-OCR.md T2.1 — "SAP H+1". Laporan diakui tepat waktu jika
 * submitted_at <= akhir_shift(tanggal_pengawasan) + window jam (default 24).
 *
 * CATATAN: apakah H+1 dihitung dari akhir shift atau dari tanggal pengawasan
 * itu sendiri masih open question (plan-OCR.md Lampiran D #18). Implementasi
 * ini memakai "akhir shift" sesuai teks asli T2.1 — sesuaikan
 * $windowStartsFrom kalau user mengonfirmasi sebaliknya.
 */
final class SubmissionWindow
{
    public function __construct(
        private readonly int $windowHours = 24,
    ) {}

    public function isOnTime(CarbonInterface $observationDate, ControlRoomShiftCode $shift, CarbonInterface $submittedAt): bool
    {
        $deadline = $this->deadline($observationDate, $shift);

        return CarbonImmutable::parse($submittedAt)->lessThanOrEqualTo($deadline);
    }

    public function deadline(CarbonInterface $observationDate, ControlRoomShiftCode $shift): CarbonImmutable
    {
        return $this->shiftEnd($observationDate, $shift)->addHours($this->windowHours);
    }

    private function shiftEnd(CarbonInterface $observationDate, ControlRoomShiftCode $shift): CarbonImmutable
    {
        $end = CarbonImmutable::parse(CarbonImmutable::parse($observationDate)->toDateString().' '.$shift->end());

        if ($shift->crossesMidnight()) {
            $end = $end->addDay();
        }

        return $end;
    }
}
