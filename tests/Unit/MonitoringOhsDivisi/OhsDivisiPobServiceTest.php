<?php

declare(strict_types=1);

namespace Tests\Unit\MonitoringOhsDivisi;

use App\Services\MonitoringOhsDivisi\OhsDivisiKaryawanReader;
use App\Services\MonitoringOhsDivisi\OhsDivisiPobService;
use Tests\TestCase;

/**
 * Monitoring OHS Divisi: bagian RFID snapshot POB hanya karyawan departemen
 * OHS Divisi; data GPS/Besigma tidak disentuh.
 */
class OhsDivisiPobServiceTest extends TestCase
{
    public function test_checkin_hanya_departemen_ohs_dan_angka_dihitung_ulang(): void
    {
        $data = [
            'checkins' => [
                ['sid' => 'a1', 'name' => 'Ani', 'site_code' => 'BMO'],
                ['sid' => 'B2', 'name' => 'Budi', 'site_code' => 'LMO'],
                ['sid' => 'C3', 'name' => 'Cici', 'site_code' => 'BMO'],
            ],
            'summary' => [
                'in' => 7,
                'checkin_total' => 3,
                'checkin_by_site' => [
                    ['code' => 'BMO', 'label' => 'Binungan', 'count' => 2],
                    ['code' => 'LMO', 'label' => 'Lati', 'count' => 1],
                ],
            ],
            'reconcile' => ['rfid_count' => 3, 'current_count' => 0, 'gap_rfid_minus_besigma_count' => 3],
            'people' => [['sid' => 'Z9']],
        ];

        $hasil = OhsDivisiPobService::saring($data, ['A1' => 'SAFETY OPERATIONS DEPARTMENT', 'C3' => 'SDCER']);

        $this->assertSame(['a1', 'C3'], array_column($hasil['checkins'], 'sid'));
        $this->assertSame('SDCER', $hasil['checkins'][1]['department']);
        $this->assertSame(2, $hasil['summary']['checkin_total']);
        $this->assertSame([2, 0], array_column($hasil['summary']['checkin_by_site'], 'count'));
        $this->assertSame(2, $hasil['reconcile']['rfid_count']);
        $this->assertSame(2, $hasil['reconcile']['gap_rfid_minus_besigma_count']);
        // GPS/Besigma tetap utuh.
        $this->assertSame(7, $hasil['summary']['in']);
        $this->assertSame([['sid' => 'Z9']], $hasil['people']);
    }

    public function test_daftar_departemen_tidak_terbaca_berarti_nol_bukan_semua(): void
    {
        $hasil = OhsDivisiPobService::saring(['checkins' => [['sid' => 'A1', 'site_code' => 'BMO']], 'summary' => [], 'reconcile' => []], []);

        $this->assertSame([], $hasil['checkins']);
        $this->assertSame(0, $hasil['summary']['checkin_total']);
    }

    public function test_nama_departemen_dari_config_dirapikan(): void
    {
        config(['monitoring_ohs_divisi.departemen' => [' sdcer ', 'SDCER', 'Safety Operations Department']]);

        $this->assertSame(['SDCER', 'SAFETY OPERATIONS DEPARTMENT'], OhsDivisiKaryawanReader::departemen());
    }
}
