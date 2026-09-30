<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterJabatanKategori;
use App\Services\Dms\Roster\DmsRosterRfidSyncService;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Tests\TestCase;

/**
 * Penyaringan populasi roster dari bcsid.bep_vw_safety_karyawan_aktif.
 *
 * petakanPopulasi() sengaja dipisah dari query supaya bisa diuji tanpa
 * jaringan: metode ini tidak pernah memanggil PembatasanLVOlapQuery.
 */
class DmsRosterPopulasiTest extends TestCase
{
    private DmsRosterRfidSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sync = new DmsRosterRfidSyncService(
            app(PembatasanLVOlapQuery::class),
            new DmsRosterJabatanKategori(),
        );
    }

    public function test_hanya_karyawan_aktif_wp_passed_dan_jabatan_relevan_yang_masuk(): void
    {
        $hasil = $this->petakan([
            ['kode_sid' => 'AA01', 'jabatan_struktural' => 'OPERATOR DT'],
            ['kode_sid' => 'AA02', 'jabatan_struktural' => 'OPERATOR DT', 'status_permit' => 'NOT PASSED'],
            ['kode_sid' => 'AA03', 'jabatan_struktural' => 'OPERATOR DT', 'status_karyawan' => 'NON AKTIF'],
            ['kode_sid' => 'AA04', 'jabatan_struktural' => 'STAFF ADMINISTRASI'],
        ]);

        $this->assertSame(['AA01'], array_column($hasil, 'kode_sid'));
    }

    public function test_kode_sid_ganda_diambil_baris_pertama(): void
    {
        // View safety memuat ±666 kode_sid ganda; tanpa dedup jumlah populasi
        // akan lebih besar dari jumlah SID unik.
        $hasil = $this->petakan([
            ['kode_sid' => 'bb01', 'nama' => 'PERTAMA', 'jabatan_struktural' => 'OPERATOR DT',
                'nama_perusahaan' => 'PT Pamapersada Nusantara'],
            ['kode_sid' => 'BB01', 'nama' => 'KEDUA', 'jabatan_struktural' => 'MECHANIC',
                'nama_perusahaan' => 'PT Kaltim Diamond Coal'],
        ]);

        $this->assertCount(1, $hasil);
        $this->assertSame('BB01', $hasil[0]['kode_sid'], 'kode_sid dinormalkan ke huruf besar');
        $this->assertSame('PERTAMA', $hasil[0]['nama']);
        $this->assertSame('PAMA', $hasil[0]['kode_pt']);
    }

    public function test_kode_sid_kosong_dibuang(): void
    {
        $hasil = $this->petakan([
            ['kode_sid' => '   ', 'jabatan_struktural' => 'OPERATOR DT'],
            ['kode_sid' => '', 'jabatan_struktural' => 'OPERATOR DT'],
        ]);

        $this->assertSame([], $hasil);
    }

    public function test_jabatan_fungsional_dipakai_saat_struktural_kosong(): void
    {
        $hasil = $this->petakan([
            ['kode_sid' => 'CC01', 'jabatan_struktural' => '', 'jabatan_fungsional' => 'OPERATOR EXCAVATOR 200T'],
        ]);

        $this->assertCount(1, $hasil);
        $this->assertSame('OPERATOR EXCAVATOR 200T', $hasil[0]['jabatan']);
        $this->assertSame(DmsRosterJabatanKategori::A2B, $hasil[0]['kategori']);
    }

    public function test_perusahaan_dan_site_kosong_diberi_nilai_aman(): void
    {
        $hasil = $this->petakan([
            ['kode_sid' => 'DD01', 'jabatan_struktural' => 'MEKANIK', 'nama_perusahaan' => '', 'site_dedicated' => '  '],
        ]);

        $this->assertSame('(Tidak diketahui)', $hasil[0]['perusahaan']);
        $this->assertNull($hasil[0]['site']);
        $this->assertSame(DmsRosterJabatanKategori::MEKANIK, $hasil[0]['kategori']);
    }

    public function test_filter_wp_bisa_dimatikan_lewat_config(): void
    {
        $baris = [
            ['kode_sid' => 'EE01', 'jabatan_struktural' => 'OPERATOR DT'],
            ['kode_sid' => 'EE02', 'jabatan_struktural' => 'OPERATOR DT', 'status_permit' => 'NOT PASSED'],
        ];

        $this->assertCount(1, $this->petakan($baris));

        config(['dms_roster.populasi.status_permit' => '']);

        $this->assertCount(2, $this->petakan($baris), 'Ambang kosong berarti filter WP tidak diterapkan.');
    }

    public function test_pola_awal_sepanjang_tahun_dan_tahun_kabisat_dihitung_benar(): void
    {
        $biasa = $this->petakan([['kode_sid' => 'FF01', 'jabatan_struktural' => 'OPERATOR DT']], 2026);
        $kabisat = $this->petakan([['kode_sid' => 'FF01', 'jabatan_struktural' => 'OPERATOR DT']], 2024);

        $this->assertSame(365, strlen($biasa[0]['pola']));
        $this->assertSame(366, strlen($kabisat[0]['pola']));
        $this->assertSame(str_repeat('o', 365), $biasa[0]['pola'], 'Pola awal semuanya off sebelum kompilasi.');
    }

    public function test_nama_dan_jabatan_dipotong_sesuai_lebar_kolom(): void
    {
        $hasil = $this->petakan([[
            'kode_sid' => 'GG01',
            'nama' => str_repeat('A', 200),
            'jabatan_struktural' => 'OPERATOR '.str_repeat('B', 300),
        ]]);

        $this->assertSame(150, mb_strlen($hasil[0]['nama']));
        $this->assertSame(255, mb_strlen($hasil[0]['jabatan']));
    }

    public function test_kode_pt_dipetakan_dari_config_atau_diinisialkan(): void
    {
        $this->assertSame('PAMA', $this->sync->kodePt('PT Pamapersada Nusantara'));
        $this->assertSame('BUMA', $this->sync->kodePt('PT Bukit Makmur Mandiri Utama'));
        $this->assertSame('SHJ', $this->sync->kodePt('PT Sinar Harapan Jaya'));
        $this->assertSame('TH', $this->sync->kodePt('CV Teguh Harapan'));
        $this->assertSame('NA', $this->sync->kodePt(''));
    }

    /**
     * @param  list<array<string, string>>  $baris
     * @return list<array<string, mixed>>
     */
    private function petakan(array $baris, int $tahun = 2026): array
    {
        $rows = array_map(static fn (array $a): object => (object) array_merge([
            'kode_sid' => '',
            'nama' => 'NAMA UJI',
            'jabatan_struktural' => '',
            'jabatan_fungsional' => '',
            'nama_perusahaan' => 'PT Pamapersada Nusantara',
            'site_dedicated' => 'BMO 1',
            'status_karyawan' => 'AKTIF',
            'status_permit' => 'PASSED',
        ], $a), $baris);

        return $this->sync->petakanPopulasi($rows, $tahun, $tahun.'-01-01');
    }
}
