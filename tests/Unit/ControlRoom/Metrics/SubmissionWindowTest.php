<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom\Metrics;

use App\Enums\ControlRoomShiftCode;
use App\Services\ControlRoom\Metrics\SubmissionWindow;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class SubmissionWindowTest extends TestCase
{
    public function test_submit_sebelum_akhir_shift_dianggap_tepat_waktu(): void
    {
        $window = new SubmissionWindow(24);
        $observationDate = CarbonImmutable::parse('2026-01-27');
        $submittedAt = CarbonImmutable::parse('2026-01-27 10:00:00');

        $this->assertTrue($window->isOnTime($observationDate, ControlRoomShiftCode::S1, $submittedAt));
    }

    public function test_submit_tepat_di_batas_deadline_masih_tepat_waktu(): void
    {
        $window = new SubmissionWindow(24);
        $observationDate = CarbonImmutable::parse('2026-01-27');
        // Shift 1 berakhir 18:00, + 24 jam = 2026-01-28 18:00:00 tepat.
        $submittedAt = CarbonImmutable::parse('2026-01-28 18:00:00');

        $this->assertTrue($window->isOnTime($observationDate, ControlRoomShiftCode::S1, $submittedAt));
    }

    public function test_submit_1_detik_lewat_deadline_dianggap_terlambat(): void
    {
        $window = new SubmissionWindow(24);
        $observationDate = CarbonImmutable::parse('2026-01-27');
        $submittedAt = CarbonImmutable::parse('2026-01-28 18:00:01');

        $this->assertFalse($window->isOnTime($observationDate, ControlRoomShiftCode::S1, $submittedAt));
    }

    public function test_shift_lewat_tengah_malam_deadline_dihitung_dari_akhir_shift_yang_benar(): void
    {
        $window = new SubmissionWindow(24);
        $observationDate = CarbonImmutable::parse('2026-01-27');
        // Shift 2 (18:00-06:00) berakhir 2026-01-28 06:00, + 24 jam = 2026-01-29 06:00.
        $onTime = CarbonImmutable::parse('2026-01-29 05:59:59');
        $late = CarbonImmutable::parse('2026-01-29 06:00:01');

        $this->assertTrue($window->isOnTime($observationDate, ControlRoomShiftCode::S2, $onTime));
        $this->assertFalse($window->isOnTime($observationDate, ControlRoomShiftCode::S2, $late));
    }
}
