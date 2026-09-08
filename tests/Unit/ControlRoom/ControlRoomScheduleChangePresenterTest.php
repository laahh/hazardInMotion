<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Models\ControlRoom\ScheduleChange;
use App\Models\User;
use App\Services\ControlRoom\ControlRoomScheduleChangePresenter;
use Carbon\Carbon;
use Tests\TestCase;

final class ControlRoomScheduleChangePresenterTest extends TestCase
{
    public function test_menyusun_sebelumnya_siapa_jadi_siapa(): void
    {
        $user = new User(['name' => 'Admin Jadwal']);
        $at = Carbon::parse('2026-09-09 10:15:00');

        $timeline = (new ControlRoomScheduleChangePresenter())->timeline(collect([
            $this->change('personnel_source_key', 'FJAVJ', 'C5BXK', 'Cuti', $at, $user),
            $this->change('personnel_name_snapshot', 'AGUNG NUGROHO', 'IFA APRILLIANTO', 'Cuti', $at, $user),
        ]));

        $this->assertCount(1, $timeline);
        $this->assertSame('AGUNG NUGROHO (FJAVJ)', $timeline[0]['from']);
        $this->assertSame('IFA APRILLIANTO (C5BXK)', $timeline[0]['to']);
        $this->assertSame('AGUNG NUGROHO (FJAVJ) → IFA APRILLIANTO (C5BXK)', $timeline[0]['summary']);
        $this->assertSame('Cuti', $timeline[0]['reason']);
        $this->assertSame('Admin Jadwal', $timeline[0]['by']);
    }

    public function test_alasan_kosong_jadi_ganti_personil_harian(): void
    {
        $at = Carbon::parse('2026-09-09 11:00:00');
        $timeline = (new ControlRoomScheduleChangePresenter())->timeline(collect([
            $this->change('personnel_source_key', 'AAA01', 'BBB02', '  ', $at, null),
        ]));

        $this->assertSame('Ganti personil harian', $timeline[0]['reason']);
        $this->assertSame('AAA01 → BBB02', $timeline[0]['summary']);
        $this->assertSame('—', $timeline[0]['by']);
    }

    public function test_riwayat_terbaru_ditampilkan_lebih_dulu(): void
    {
        $user = new User(['name' => 'Admin']);
        $first = Carbon::parse('2026-09-09 08:00:00');
        $second = Carbon::parse('2026-09-09 09:00:00');

        $timeline = (new ControlRoomScheduleChangePresenter())->timeline(collect([
            $this->change('personnel_source_key', 'AAA01', 'BBB02', 'Pertama', $first, $user),
            $this->change('personnel_source_key', 'BBB02', 'CCC03', 'Kedua', $second, $user),
        ]));

        $this->assertCount(2, $timeline);
        $this->assertSame('BBB02 → CCC03', $timeline[0]['summary']);
        $this->assertSame('AAA01 → BBB02', $timeline[1]['summary']);
        $this->assertSame('Kedua', $timeline[0]['reason']);
        $this->assertSame('Pertama', $timeline[1]['reason']);
    }

    public function test_nama_dan_sid_selisih_detik_tetap_satu_baris(): void
    {
        $user = new User(['name' => 'OHS DIVISI']);
        $nameAt = Carbon::parse('2026-09-08 09:24:01');
        $sidAt = Carbon::parse('2026-09-08 09:24:02');

        $timeline = (new ControlRoomScheduleChangePresenter())->timeline(collect([
            $this->change('personnel_name_snapshot', 'IFA APRILLIANTO', 'INDRA NUR SIDIQ', 'Ifa ada LK3 Meeting', $nameAt, $user),
            $this->change('personnel_source_key', 'C5BXK', 'S69PK', 'Ifa ada LK3 Meeting', $sidAt, $user),
        ]));

        $this->assertCount(1, $timeline);
        $this->assertSame(
            'IFA APRILLIANTO (C5BXK) → INDRA NUR SIDIQ (S69PK)',
            $timeline[0]['summary'],
        );
    }

    public function test_person_label_menggabungkan_nama_dan_sid(): void
    {
        $presenter = new ControlRoomScheduleChangePresenter();

        $this->assertSame('IFA APRILLIANTO (C5BXK)', $presenter->personLabel('IFA APRILLIANTO', 'c5bxk'));
        $this->assertSame('C5BXK', $presenter->personLabel('', 'C5BXK'));
        $this->assertSame('—', $presenter->personLabel(null, null));
    }

    private function change(
        string $field,
        string $old,
        string $new,
        string $reason,
        Carbon $at,
        ?User $user,
    ): ScheduleChange {
        $change = new ScheduleChange([
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'reason' => $reason,
            'changed_by' => $user?->id ?? 0,
        ]);
        $change->changed_at = $at;
        $change->setRelation('changedBy', $user);

        return $change;
    }
}
