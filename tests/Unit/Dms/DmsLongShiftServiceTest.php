<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsLongShiftReader;
use App\Services\Dms\Roster\DmsLongShiftService;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Tests\TestCase;

/**
 * Bagian murni DmsLongShiftService: status per pengawas, penggabungan
 * baseline + hasil deteksi, saring & paginasi. Deteksinya sendiri (SQL di
 * DmsLongShiftReader) divalidasi terhadap data demo docs/overshift.md §4.1.
 */
class DmsLongShiftServiceTest extends TestCase
{
    private DmsLongShiftService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DmsLongShiftService(new DmsLongShiftReader(new PembatasanLVOlapQuery()));
    }

    public function test_status_long_shift_menang_atas_anomali(): void
    {
        $this->assertSame('LONG_SHIFT', DmsLongShiftService::status(['ada_tap' => true, 'n_shift' => 3, 'n_long' => 1, 'n_anomali' => 4]));
    }

    public function test_anomali_tidak_menutupi_shift_yang_bisa_dinilai(): void
    {
        $this->assertSame('NORMAL', DmsLongShiftService::status(['ada_tap' => true, 'n_shift' => 2, 'n_long' => 0, 'n_anomali' => 5]));
        $this->assertSame('DATA_ANOMALY', DmsLongShiftService::status(['ada_tap' => true, 'n_shift' => 0, 'n_long' => 0, 'n_anomali' => 2]));
    }

    public function test_tanpa_tap_dan_tanpa_shift(): void
    {
        $this->assertSame('NO_DATA', DmsLongShiftService::status(['ada_tap' => false, 'n_shift' => 0, 'n_long' => 0, 'n_anomali' => 0]));
        $this->assertSame('TIDAK_TERKLASIFIKASI', DmsLongShiftService::status(['ada_tap' => true, 'n_shift' => 0, 'n_long' => 0, 'n_anomali' => 0]));
    }

    public function test_gabung_baseline_dengan_deteksi_dan_urut_long_shift_terbanyak(): void
    {
        $pengawas = [
            (object) ['sid' => 'A1', 'nama' => 'Budi', 'perusahaan' => 'PT X', 'jabatan' => 'Foreman', 'departemen' => 'Prod'],
            (object) ['sid' => 'B2', 'nama' => 'Andi', 'perusahaan' => 'PT X', 'jabatan' => 'Foreman', 'departemen' => 'Prod'],
            (object) ['sid' => 'C3', 'nama' => 'Caca', 'perusahaan' => 'PT Y', 'jabatan' => 'SPV', 'departemen' => 'HSE'],
            (object) ['sid' => 'D4', 'nama' => 'Dedi', 'perusahaan' => 'PT Y', 'jabatan' => 'SPV', 'departemen' => 'HSE'],
        ];
        $deteksi = [
            (object) ['sid' => 'A1', 'n_shift' => 5, 'n_long' => 1, 'n_anomali' => 0,
                'long_shift' => '[{"shift":"2026-10-20 07:00","m_shift":660,"m_lanjut":330}]', 'anomali' => null],
            (object) ['sid' => 'C3', 'n_shift' => 4, 'n_long' => 2, 'n_anomali' => 1, 'long_shift' => '[{},{}]',
                'anomali' => '[{"waktu":"2026-10-19 07:00","jenis":"IN tanpa OUT","gate":"G"}]'],
            (object) ['sid' => 'D4', 'n_shift' => 3, 'n_long' => 0, 'n_anomali' => 0, 'long_shift' => null, 'anomali' => null],
        ];

        $baris = $this->svc->gabung($pengawas, $deteksi);

        $this->assertSame(['C3', 'A1', 'D4', 'B2'], array_column($baris, 'sid'));
        $this->assertSame(['LONG_SHIFT', 'LONG_SHIFT', 'NORMAL', 'NO_DATA'], array_column($baris, 'status'));
        $this->assertSame(330, $baris[1]['long_shift'][0]['m_lanjut']);
        $this->assertSame('IN tanpa OUT', $baris[0]['anomali'][0]['jenis']);
        $this->assertSame([], $baris[3]['long_shift']);
    }

    public function test_saring_status_cari_dan_paginasi(): void
    {
        $baris = [];
        for ($i = 1; $i <= 30; $i++) {
            $baris[] = ['sid' => 'S'.$i, 'nama' => 'Orang '.$i, 'perusahaan' => $i <= 3 ? 'PT Cari' : 'PT Lain',
                'jabatan' => '', 'departemen' => '', 'status' => $i % 2 ? 'LONG_SHIFT' : 'NORMAL'];
        }

        $hal2 = $this->svc->saring($baris, 'LONG_SHIFT', '', 2, 10);
        $this->assertSame(15, $hal2['total']);
        $this->assertSame(2, $hal2['total_halaman']);
        $this->assertCount(5, $hal2['baris']);

        $cari = $this->svc->saring($baris, '', 'pt cari', 9, 10);
        $this->assertSame(3, $cari['total']);
        $this->assertSame(1, $cari['halaman']);
    }
}
