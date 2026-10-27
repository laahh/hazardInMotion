<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterPeringatanStore;
use PHPUnit\Framework\TestCase;

/**
 * Pemetaan baris 'mendekati' (periode kerja 60–70 hari) ke kolom
 * dms_roster_peringatan. Bagian upsert menyentuh DB dan tidak diuji di sini.
 */
class DmsRosterPeringatanStoreTest extends TestCase
{
    private function mendekati(array $ubah = []): array
    {
        return $ubah + [
            'sid' => ' we3ck ',
            'nama' => 'TAJUDDIN',
            'jabatan' => 'PJO',
            'kategori' => 'Operator A2B',
            'perusahaan' => 'CV Maju Makmur Tehnik',
            'pt' => 'CMMT',
            'site' => '—',
            'hari_ke' => 65,
            'sisa_hari' => 5,
            'mulai_iso' => '2026-08-24',
            'tgl_melanggar_iso' => '2026-11-02',
            'tgl_banned_iso' => '2026-11-05',
            'scan_terakhir_iso' => '2026-10-27',
        ];
    }

    public function test_baris_dipetakan_ke_kolom_tabel(): void
    {
        $baris = DmsRosterPeringatanStore::baris([$this->mendekati()], '2026-10-27', '2026-10-27 10:00:00');

        $this->assertCount(1, $baris);
        $this->assertSame([
            'tanggal' => '2026-10-27',
            'kode' => 'onsite',
            'kode_sid' => 'WE3CK',
            'nama' => 'TAJUDDIN',
            'jabatan' => 'PJO',
            'kategori' => 'Operator A2B',
            'perusahaan' => 'CV Maju Makmur Tehnik',
            'kode_pt' => 'CMMT',
            'site' => null,
            'hari_ke' => 65,
            'sisa_hari' => 5,
            'mulai_periode' => '2026-08-24',
            'tgl_melanggar' => '2026-11-02',
            'tgl_banned' => '2026-11-05',
            'scan_terakhir' => '2026-10-27',
            'created_at' => '2026-10-27 10:00:00',
            'updated_at' => '2026-10-27 10:00:00',
        ], $baris[0]);
    }

    public function test_sid_kosong_dan_ganda_dilewati(): void
    {
        $baris = DmsRosterPeringatanStore::baris([
            $this->mendekati(),
            $this->mendekati(['sid' => 'WE3CK', 'hari_ke' => 66]),
            $this->mendekati(['sid' => '']),
        ], '2026-10-27', '2026-10-27 10:00:00');

        $this->assertCount(1, $baris);
        $this->assertSame(65, $baris[0]['hari_ke']);
    }
}
