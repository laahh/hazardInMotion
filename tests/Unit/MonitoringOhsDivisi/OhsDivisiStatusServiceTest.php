<?php

declare(strict_types=1);

namespace Tests\Unit\MonitoringOhsDivisi;

use App\Services\MonitoringOhsDivisi\OhsDivisiStatusService as Status;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Status RFID karyawan OHS Divisi: onsite (check-in tanpa check-out
 * sesudahnya), sudah keluar, atau belum masuk, beserta lama di site.
 */
class OhsDivisiStatusServiceTest extends TestCase
{
    public function test_status_onsite_keluar_belum_dan_lama_di_site(): void
    {
        $kini = CarbonImmutable::parse('2026-10-29 15:00:00');
        $karyawan = [
            'A1' => $this->k('A1', 'Ani'),
            'B2' => $this->k('B2', 'Budi'),
            'C3' => $this->k('C3', 'Cici'),
            'D4' => $this->k('D4', 'Dedi'),
        ];
        $masuk = [
            // Check-in pagi, belum keluar → onsite 8 jam.
            'A1' => $this->tap('2026-10-29 07:00:00', 'Pos Mess'),
            // Masuk lalu keluar → sudah keluar, 9 jam di site.
            'B2' => $this->tap('2026-10-29 06:00:00', 'Pos A'),
            // Shift malam kemarin, check-out lama SEBELUM check-in → masih onsite.
            'C3' => $this->tap('2026-10-28 18:00:00', 'Pos B'),
        ];
        $keluar = [
            'B2' => $this->tap('2026-10-29 15:00:00', 'Pos A'),
            'C3' => $this->tap('2026-10-28 06:00:00', 'Pos B'),
        ];

        $h = Status::susun($karyawan, $masuk, $keluar, $kini);
        $per = array_column($h['baris'], null, 'sid');

        $this->assertSame(['onsite' => 2, 'keluar' => 1, 'belum' => 1], $h['ringkas']);
        $this->assertSame(['onsite', 480], [$per['A1']['status'], $per['A1']['durasi_menit']]);
        $this->assertSame(['keluar', 540, '2026-10-29 15:00'], [$per['B2']['status'], $per['B2']['durasi_menit'], $per['B2']['keluar']]);
        $this->assertSame(['onsite', 1260, null], [$per['C3']['status'], $per['C3']['durasi_menit'], $per['C3']['keluar']]);
        $this->assertSame(['belum', null], [$per['D4']['status'], $per['D4']['durasi_menit']]);
        // Urutan: onsite terlama dulu, lalu keluar, lalu belum masuk.
        $this->assertSame(['C3', 'A1', 'B2', 'D4'], array_column($h['baris'], 'sid'));
    }

    /**
     * @return array{sid:string,nama:string,departemen:string,perusahaan:string,jabatan:string}
     */
    private function k(string $sid, string $nama): array
    {
        return ['sid' => $sid, 'nama' => $nama, 'departemen' => 'SDCER', 'perusahaan' => 'PT X', 'jabatan' => 'Officer'];
    }

    /**
     * @return array{checked_in_at:string,gate:string}
     */
    private function tap(string $waktu, string $gate): array
    {
        return ['checked_in_at' => $waktu, 'gate' => $gate];
    }
}
