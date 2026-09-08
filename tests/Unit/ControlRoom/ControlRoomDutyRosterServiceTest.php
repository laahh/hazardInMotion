<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Enums\ControlRoomSiteCode;
use App\Models\ControlRoom\SchedulePlan;
use App\Services\ControlRoom\ControlRoomDutyRosterService;
use App\Services\ControlRoom\Reference\ShiftResolver;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class ControlRoomDutyRosterServiceTest extends TestCase
{
    public function test_satu_slot_dipakai_apa_adanya(): void
    {
        $plan = $this->plan(1, ControlRoomShiftCode::S2, 'C5BXK');
        $picked = $this->service()->pickPlan(collect([$plan]), ControlRoomShiftCode::S1);

        $this->assertSame($plan, $picked);
    }

    public function test_dua_slot_memilih_shift_yang_sedang_berjalan(): void
    {
        $s1 = $this->plan(1, ControlRoomShiftCode::S1, 'C5BXK');
        $s2 = $this->plan(2, ControlRoomShiftCode::S2, 'C5BXK');
        $picked = $this->service()->pickPlan(collect([$s1, $s2]), ControlRoomShiftCode::S2);

        $this->assertSame($s2, $picked);
    }

    public function test_tanpa_slot_mengembalikan_null(): void
    {
        $this->assertNull($this->service()->pickPlan(collect(), ControlRoomShiftCode::S1));
    }

    public function test_pesan_tidak_dijadwalkan(): void
    {
        $this->assertSame(
            'Anda tidak dijadwalkan hari ini jadi tidak bisa absen. Jika ada perubahan, hubungi admin.',
            ControlRoomDutyRosterService::NOT_SCHEDULED_MESSAGE,
        );
    }

    public function test_label_tanggal_bahasa_indonesia(): void
    {
        $label = $this->service()->dutyDateLabel(CarbonImmutable::parse('2026-09-08'));

        $this->assertSame('Selasa, 8 September 2026', $label);
    }

    private function service(): ControlRoomDutyRosterService
    {
        return new ControlRoomDutyRosterService(new ShiftResolver());
    }

    private function plan(int $id, ControlRoomShiftCode $shift, string $sid): SchedulePlan
    {
        $plan = new SchedulePlan();
        $plan->id = $id;
        $plan->forceFill([
            'site_code' => ControlRoomSiteCode::HeadOffice,
            'date' => '2026-09-08',
            'shift_code' => $shift,
            'personnel_source_key' => $sid,
            'personnel_name_snapshot' => 'IFA APRILLIANTO',
        ]);

        return $plan;
    }
}
