<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterMasterEditService as Edit;
use Tests\TestCase;

/**
 * Rencana perubahan Edit Roster (murni): kode per tanggal, kosongkan/tambah,
 * jam kerja per karyawan, dan penolakan input yang tidak sah.
 */
class DmsRosterMasterEditServiceTest extends TestCase
{
    private const DARI = '2026-11-01';

    private const SAMPAI = '2026-11-30';

    public function test_ubah_kode_memakai_jam_bawaan_kode_baru(): void
    {
        $r = Edit::rencana($this->baris(), [['sid' => 'A1', 'tanggal' => '2026-11-02', 'kode' => 'N1']], [], self::DARI, self::SAMPAI);

        $this->assertSame([], $r['galat']);
        $this->assertSame([['ubah', 'A1', '2026-11-02', ['kode' => 'N1', 'masuk' => '18:00', 'keluar' => '06:00', 'menit' => 720, 'total' => 720]]], $r['ops']);
        $this->assertSame('D2', $r['log'][0]['nilai_lama']);
        $this->assertSame('N1', $r['log'][0]['nilai_baru']);
    }

    public function test_kosongkan_menghapus_dan_sel_kosong_diisi_menambah(): void
    {
        $r = Edit::rencana($this->baris(), [
            ['sid' => 'A1', 'tanggal' => '2026-11-03', 'kode' => ''],
            ['sid' => 'A1', 'tanggal' => '2026-11-05', 'kode' => 'off'],
        ], [], self::DARI, self::SAMPAI);

        $this->assertSame(['hapus', 'A1', '2026-11-03', null], $r['ops'][0]);
        $this->assertSame(['tambah', 'A1', '2026-11-05', ['kode' => 'OFF', 'masuk' => null, 'keluar' => null, 'menit' => null, 'total' => null]], $r['ops'][1]);
    }

    public function test_kode_sama_tidak_menghasilkan_perubahan(): void
    {
        $r = Edit::rencana($this->baris(), [['sid' => 'A1', 'tanggal' => '2026-11-01', 'kode' => 'D1']], [], self::DARI, self::SAMPAI);

        $this->assertSame([], $r['ops']);
    }

    public function test_jam_karyawan_berlaku_untuk_semua_hari_kerja(): void
    {
        $r = Edit::rencana($this->baris(), [], [['sid' => 'A1', 'masuk' => '05:30', 'keluar' => '17:30', 'total' => '11']], self::DARI, self::SAMPAI);

        $this->assertSame([], $r['galat']);
        // D1 & D2 berubah jam; OFF tidak punya jam.
        $this->assertCount(2, $r['ops']);
        $this->assertSame(['kode' => 'D1', 'masuk' => '05:30', 'keluar' => '17:30', 'menit' => 720, 'total' => 660], $r['ops'][0][3]);
        $this->assertSame('jam', end($r['log'])['kolom']);
    }

    public function test_hari_kerja_baru_ikut_jam_khusus_yang_sudah_ada(): void
    {
        $baris = ['B2' => [
            '2026-11-01' => ['kode' => 'D1', 'masuk' => '07:00', 'keluar' => '16:00', 'menit' => 540, 'total' => 480],
        ]];
        $r = Edit::rencana($baris, [['sid' => 'B2', 'tanggal' => '2026-11-02', 'kode' => 'D2']], [], self::DARI, self::SAMPAI);

        $this->assertSame(['kode' => 'D2', 'masuk' => '07:00', 'keluar' => '16:00', 'menit' => 540, 'total' => 480], $r['ops'][0][3]);
    }

    public function test_input_tidak_sah_ditolak_seluruhnya(): void
    {
        $r = Edit::rencana($this->baris(), [
            ['sid' => 'A1', 'tanggal' => '2026-12-01', 'kode' => 'D1'],
            ['sid' => 'A1', 'tanggal' => '2026-11-04', 'kode' => 'XX'],
            ['sid' => 'Z9', 'tanggal' => '2026-11-04', 'kode' => 'D1'],
        ], [['sid' => 'A1', 'masuk' => '06:00', 'keluar' => '18:00', 'total' => '13']], self::DARI, self::SAMPAI);

        $this->assertCount(4, $r['galat']);
        $this->assertSame([], $r['ops']);
    }

    public function test_sid_angka_tetap_string(): void
    {
        $baris = ['10016025' => ['2026-11-01' => ['kode' => 'D1', 'masuk' => '06:00', 'keluar' => '18:00', 'menit' => 720, 'total' => 720]]];
        $r = Edit::rencana($baris, [['sid' => '10016025', 'tanggal' => '2026-11-01', 'kode' => 'OFF']], [['sid' => '10016025', 'masuk' => '07:00', 'keluar' => '17:00', 'total' => '']], self::DARI, self::SAMPAI);

        $this->assertSame('10016025', $r['ops'][0][1]);
        $this->assertSame('10016025', $r['log'][0]['kode_sid']);
    }

    public function test_jam_dari_form(): void
    {
        $this->assertNull(Edit::jamDariForm(['masuk' => '', 'keluar' => '', 'total' => '']));
        $this->assertSame(['masuk' => '18:00', 'keluar' => '06:00', 'menit' => 720, 'total' => 690], Edit::jamDariForm(['masuk' => '18:00', 'keluar' => '06:00', 'total' => '11,5']));
        $this->assertIsString(Edit::jamDariForm(['masuk' => '06:00', 'keluar' => '', 'total' => '']));
        $this->assertIsString(Edit::jamDariForm(['masuk' => '', 'keluar' => '', 'total' => '8']));
    }

    /**
     * @return array<string, array<string, array{kode:string,masuk:?string,keluar:?string,menit:?int,total:?int}>>
     */
    private function baris(): array
    {
        return ['A1' => [
            '2026-11-01' => ['kode' => 'D1', 'masuk' => '06:00', 'keluar' => '18:00', 'menit' => 720, 'total' => 720],
            '2026-11-02' => ['kode' => 'D2', 'masuk' => '06:00', 'keluar' => '18:00', 'menit' => 720, 'total' => 720],
            '2026-11-03' => ['kode' => 'OFF', 'masuk' => null, 'keluar' => null, 'menit' => null, 'total' => null],
        ]];
    }
}
