<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmSapDetailLokasi;
use PHPUnit\Framework\TestCase;

/** Pengelompokan laporan SAP satu detail lokasi per PJA pelapornya (tanpa ClickHouse). */
class DrmSapDetailLokasiTest extends TestCase
{
    public function test_laporan_dikelompokkan_per_pja_pelapor(): void
    {
        $lap = fn (string $sid, string $nama, string $jenis, string $pemetaan = 'lolos', bool $belum = false, bool $stop = false) => [
            'sid' => $sid, 'pelapor' => $nama, 'jenis' => $jenis, 'pemetaan' => $pemetaan, 'belum_closed' => $belum, 'oak_stop' => $stop];
        $laporan = [
            $lap('AAA01', 'Budi', 'HAZARD', 'lolos', true),
            $lap('AAA01', 'Budi', 'OBSERVASI', 'tak_terpetakan'),
            $lap('BBB02', 'Sari', 'OBSERVASI AREA KRITIS', 'lolos', false, true),
            $lap('CCC03', 'Tamu', 'INSPEKSI'),
            $lap('', 'Tanpa SID', 'HAZARD', 'luar_grup'),
        ];
        $anggota = [
            'AAA01' => [['id' => '10', 'nama' => 'PJA Hauling', 'tipe' => 'BC', 'kategori' => 'Superior', 'induk' => '5', 'induk_nama' => 'PJA Induk BC']],
            // Sari terdaftar di dua PJA → dihitung di keduanya.
            'BBB02' => [['id' => '10', 'nama' => 'PJA Hauling', 'tipe' => 'BC', 'kategori' => 'Layer 1'],
                ['id' => '20', 'nama' => 'PJA Workshop', 'tipe' => 'Mitra Kerja', 'kategori' => 'Layer 1']],
        ];

        $g = DrmSapDetailLokasi::perPja($laporan, $anggota);

        $this->assertSame(['10', '20', ''], array_column($g, 'id'));
        [$hauling, $workshop, $bukan] = $g;
        $this->assertSame([3, 2, 1, 1], [$hauling['n'], $hauling['lolos'], $hauling['belum_closed'], $hauling['oak_stop']]);
        $this->assertSame(['HAZARD' => 1, 'OBSERVASI' => 1, 'OBSERVASI AREA KRITIS' => 1], $hauling['jenis']);
        $this->assertSame(['Budi', 'Sari'], array_column($hauling['pelapor'], 'nama'));
        $this->assertSame([2, 1], [$hauling['pelapor'][0]['n'], $hauling['pelapor'][0]['lolos']]);
        $this->assertSame(1, $workshop['n']);
        // Parent PJA dibawa ke kelompok (untuk penggabungan child di tampilan); tanpa data induk = ''.
        $this->assertSame(['5', 'PJA Induk BC'], [$hauling['induk'], $hauling['induk_nama']]);
        $this->assertSame('', $workshop['induk']);
        // Bukan personil PJA: pelapor dengan SID tak terdaftar & tanpa SID, dipisah per orang.
        $this->assertSame('Bukan personil PJA', $bukan['nama']);
        $this->assertSame([2, ['Tamu', 'Tanpa SID']], [$bukan['n'], array_column($bukan['pelapor'], 'nama')]);
    }

    public function test_tanpa_laporan_kosong(): void
    {
        $this->assertSame([], DrmSapDetailLokasi::perPja([], []));
    }
}
