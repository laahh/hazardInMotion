<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterJabatanKategori;
use App\Services\Dms\Roster\DmsRosterSyncService;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Tests\TestCase;

/**
 * Bagian DmsRosterSyncService yang bisa diuji tanpa jaringan.
 *
 * Penyaringan populasi kini dikerjakan di dalam sinkronKaryawan() yang
 * menarik dari OLAP, jadi tidak bisa diuji unit di sini. Yang tersisa dan
 * tetap penting: pemetaan nama perusahaan ke kode PT, plus daftar jabatan
 * yang menentukan siapa yang masuk master.
 */
class DmsRosterPopulasiTest extends TestCase
{
    private DmsRosterSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();
        // PembatasanLVOlapQuery bersifat final sehingga tidak bisa di-stub,
        // tapi metode yang diuji di bawah tidak pernah menyentuhnya.
        $this->sync = new DmsRosterSyncService(
            app(PembatasanLVOlapQuery::class),
            new DmsRosterJabatanKategori(),
        );
    }

    public function test_kode_pt_dipetakan_dari_config(): void
    {
        $this->assertSame('PAMA', $this->sync->kodePt('PT Pamapersada Nusantara'));
        $this->assertSame('BUMA', $this->sync->kodePt('PT Bukit Makmur Mandiri Utama'));
        $this->assertSame('KDC', $this->sync->kodePt('PT Kaltim Diamond Coal'));
    }

    public function test_kode_pt_diinisialkan_untuk_perusahaan_tak_dikenal(): void
    {
        $this->assertSame('SHJ', $this->sync->kodePt('PT Sinar Harapan Jaya'));
        $this->assertSame('TH', $this->sync->kodePt('CV Teguh Harapan'));
        $this->assertSame('NA', $this->sync->kodePt(''));
    }

    public function test_daftar_jabatan_wajib_tidak_kosong_dan_unik(): void
    {
        /** @var list<string> $jabatan */
        $jabatan = config('dms_roster.total_karyawan.jabatan', []);
        $ternormalisasi = array_map(static fn (string $j): string => strtoupper(trim($j)), $jabatan);

        $this->assertNotEmpty($jabatan, 'Daftar jabatan menentukan isi master; tidak boleh kosong.');
        $this->assertSame(
            count($ternormalisasi),
            count(array_unique($ternormalisasi)),
            'Duplikat setelah UPPER+TRIM berarti ada baris mubazir di config.',
        );
    }

    public function test_kelompok_wp_punya_prioritas_dan_id_yang_lengkap(): void
    {
        /** @var array<string, array{label:string,ids:list<int>}> $grup */
        $grup = config('dms_roster.total_karyawan.wp_grup', []);
        /** @var list<string> $prioritas */
        $prioritas = config('dms_roster.total_karyawan.wp_prioritas', []);

        $this->assertNotEmpty($grup);

        foreach ($grup as $kunci => $isi) {
            $this->assertArrayHasKey('label', $isi, "Kelompok {$kunci} tanpa label.");
            $this->assertNotEmpty($isi['ids'], "Kelompok {$kunci} tanpa id_work_permit.");
            $this->assertContains(
                $kunci,
                $prioritas,
                "Kelompok {$kunci} tidak ada di wp_prioritas — urutannya jadi tidak pasti "
                .'padahal 442 orang memegang lebih dari satu WP unit.',
            );
        }

        // Satu id tidak boleh masuk dua kelompok; kalau iya, hasilnya
        // bergantung urutan dan jumlah per kelompok jadi tidak bisa dipercaya.
        $semua = [];
        foreach ($grup as $isi) {
            foreach ($isi['ids'] as $id) {
                $this->assertNotContains($id, $semua, "id_work_permit {$id} masuk lebih dari satu kelompok.");
                $semua[] = $id;
            }
        }
    }
}
