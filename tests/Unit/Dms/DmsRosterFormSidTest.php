<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterMasterEditService as Edit;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Form Edit Roster per SID: rentang hari ini ± 7 hari, tanggal yang boleh
 * diisi, dan penolakan input sebelum menyentuh database.
 */
class DmsRosterFormSidTest extends TestCase
{
    public function test_rentang_seminggu_sebelum_dan_sesudah_hari_ini(): void
    {
        $this->assertSame(['2026-10-29', '2026-11-12'], Edit::rentangForm(CarbonImmutable::parse('2026-11-05')));
    }

    public function test_hari_form_berisi_15_tanggal_dengan_jadwal_sekarang(): void
    {
        $hari = Edit::hariForm(CarbonImmutable::parse('2026-11-05'), ['2026-11-05' => ['kode' => 'D1', 'menunggu' => true]], [['2026-11-01', '2026-11-30']]);

        $this->assertCount(15, $hari);
        $this->assertSame('2026-10-29', $hari[0]['tanggal']);
        $this->assertSame('2026-11-12', $hari[14]['tanggal']);
        $this->assertSame(['tanggal' => '2026-11-05', 'kode' => 'D1', 'menunggu' => true, 'boleh' => true], $hari[7]);
    }

    public function test_tanggal_kosong_hanya_boleh_bila_masuk_periode_roster(): void
    {
        $hari = Edit::hariForm(CarbonImmutable::parse('2026-11-05'), [], [['2026-11-01', '2026-11-30']]);

        $this->assertFalse($hari[0]['boleh'], '29 Okt di luar periode');
        $this->assertTrue($hari[3]['boleh'], '1 Nov awal periode');
        $this->assertSame('', $hari[3]['kode']);
    }

    public function test_jadwal_yang_sudah_ada_tetap_boleh_walau_di_luar_periode(): void
    {
        $hari = Edit::hariForm(CarbonImmutable::parse('2026-11-05'), ['2026-10-29' => ['kode' => 'OFF', 'menunggu' => false]], []);

        $this->assertTrue($hari[0]['boleh']);
    }

    public function test_tanggal_di_luar_rentang_ditolak_rencana(): void
    {
        [$dari, $sampai] = Edit::rentangForm(CarbonImmutable::parse('2026-11-05'));
        $baris = ['A1' => ['2026-11-20' => ['kode' => 'D1', 'masuk' => '06:00', 'keluar' => '18:00', 'menit' => 720, 'total' => 720]]];

        $r = Edit::rencana($baris, [['sid' => 'A1', 'tanggal' => '2026-11-20', 'kode' => 'OFF']], [], $dari, $sampai);

        $this->assertSame([], $r['ops']);
        $this->assertStringContainsString('di luar periode', $r['galat'][0]);
    }

    public function test_form_terbuka_tanpa_login_dan_kode_kosong_ditolak(): void
    {
        $this->postJson(route('dms.master-roster.edit-form.simpan'), [
            'sid' => 'A1',
            'sel' => [['tanggal' => '2026-11-05', 'kode' => '']],
        ])->assertStatus(422)->assertJsonValidationErrors('sel.0.kode');
    }

    public function test_kode_tidak_dikenal_dan_tanpa_perubahan_ditolak(): void
    {
        $this->postJson(route('dms.master-roster.edit-form.simpan'), [
            'sid' => 'A1',
            'sel' => [['tanggal' => '2026-11-05', 'kode' => 'XX']],
        ])->assertStatus(422)->assertJsonValidationErrors('sel.0.kode');

        $this->postJson(route('dms.master-roster.edit-form.simpan'), ['sid' => 'A1', 'sel' => []])
            ->assertStatus(422)->assertJsonValidationErrors('sel');
    }

    public function test_cari_tanpa_sid_ditolak(): void
    {
        $this->getJson(route('dms.master-roster.edit-form.cari'))
            ->assertStatus(422)->assertJsonValidationErrors('sid');
    }

    public function test_jumlah_tanggal_lebih_dari_rentang_ditolak(): void
    {
        $this->postJson(route('dms.master-roster.edit-form.simpan'), [
            'sid' => 'A1',
            'sel' => array_fill(0, 16, ['tanggal' => '2026-11-05', 'kode' => 'OFF']),
        ])->assertStatus(422)->assertJsonValidationErrors('sel');
    }
}
