<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterRuleEngine;
use Tests\TestCase;

/**
 * Ambang yang diuji di sini mengikuti config/dms_roster.php yang mereplikasi
 * kode referensi safety-roster: REG-1 run > 13, REG-2 on-site > 71,
 * REG-3 cuti < 12, Overshift >= 8, off > 5 jadi Cuti.
 */
class DmsRosterRuleEngineTest extends TestCase
{
    private DmsRosterRuleEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new DmsRosterRuleEngine();
    }

    public function test_off_lima_hari_tetap_off_dan_enam_hari_jadi_cuti(): void
    {
        // Arrange
        $limaHari = 'PPP'.str_repeat('o', 5).'PPP';
        $enamHari = 'PPP'.str_repeat('o', 6).'PPP';

        // Act & Assert
        $this->assertSame($limaHari, $this->engine->normalisasiPola($limaHari));
        $this->assertSame('PPP'.str_repeat('c', 6).'PPP', $this->engine->normalisasiPola($enamHari));
    }

    public function test_contoh_pola_dari_dokumentasi_referensi_dinormalisasi_sama(): void
    {
        $this->assertSame(
            'PPPPPPoMMMMMMoPPcccccccPPP',
            $this->engine->normalisasiPola('PPPPPPoMMMMMMoPPoooooooPPP'),
        );
    }

    public function test_reg1_menyala_pada_empat_belas_hari_kerja_beruntun(): void
    {
        $e = $this->evaluasi(str_repeat('P', 14).str_repeat('o', 12));

        $this->assertTrue($e->redK1);
        $this->assertStringContainsString('Kerja beruntun 14 hr', $e->red[0]);
    }

    public function test_reg1_tidak_menyala_pada_tiga_belas_hari_kerja_beruntun(): void
    {
        $e = $this->evaluasi(str_repeat('P', 13).str_repeat('o', 12));

        $this->assertFalse($e->redK1);
    }

    public function test_reg1_menomori_ulang_hari_ke_n_sepanjang_run_kerja(): void
    {
        $e = $this->evaluasi(str_repeat('P', 14));

        // Tanpa REG-1 dayno hanya menghitung run karakter sama; saat REG-1
        // menyala, dayno diganti posisi dalam run kerja (lihat referensi).
        $this->assertSame(1, $e->dayno[0]);
        $this->assertSame(14, $e->dayno[13]);
    }

    public function test_reg2_memakai_ambang_tujuh_puluh_satu_seperti_kode_referensi(): void
    {
        $tujuhPuluhSatu = $this->evaluasi(str_repeat('P', 71));
        $tujuhPuluhDua = $this->evaluasi(str_repeat('P', 72));

        $this->assertFalse($tujuhPuluhSatu->redK2, 'On-site 71 hari belum dianggap pelanggaran oleh kode referensi.');
        $this->assertTrue($tujuhPuluhDua->redK2);
    }

    public function test_reg2_menghitung_off_pendek_sebagai_hari_on_site(): void
    {
        // 71 hari kerja + 1 hari off = run on-site 72 hari, belum ada cuti.
        $e = $this->evaluasi(str_repeat('P', 71).'o');

        $this->assertTrue($e->redK2);
        $this->assertSame(72, $e->onAll);
    }

    public function test_reg2_diputus_oleh_blok_cuti(): void
    {
        $e = $this->evaluasi(str_repeat('P', 60).str_repeat('o', 14).str_repeat('P', 60));

        $this->assertFalse($e->redK2);
        $this->assertSame(60, $e->onAll);
    }

    public function test_reg3_memakai_ambang_dua_belas_seperti_kode_referensi(): void
    {
        $sebelas = $this->evaluasi('PPPPP'.str_repeat('o', 11).'PPPPP');
        $duaBelas = $this->evaluasi('PPPPP'.str_repeat('o', 12).'PPPPP');

        $this->assertTrue($sebelas->redK3);
        $this->assertFalse($duaBelas->redK3, 'Cuti 12 hari belum dianggap pelanggaran oleh kode referensi.');
        $this->assertSame(11, $sebelas->cutiMin);
    }

    public function test_kategori_longgar_dibebaskan_dari_semua_rule_merah(): void
    {
        $pola = str_repeat('P', 80).str_repeat('o', 8).str_repeat('P', 20);

        $ketat = $this->evaluasi($pola, 'Operator Hauler');
        $longgar = $this->evaluasi($pola, 'Mekanik');

        $this->assertTrue($ketat->everRed);
        $this->assertFalse($longgar->everRed);
        $this->assertFalse($longgar->redK1);
        $this->assertFalse($longgar->redK2);
        $this->assertFalse($longgar->redK3);
        $this->assertTrue($longgar->longgar);
    }

    public function test_status_overshift_mulai_delapan_hari_kerja_beruntun(): void
    {
        $this->assertSame(
            DmsRosterRuleEngine::STATUS_PAGI,
            $this->evaluasi(str_repeat('P', 7))->status,
        );
        $this->assertSame(
            DmsRosterRuleEngine::STATUS_OVERSHIFT,
            $this->evaluasi(str_repeat('P', 8))->status,
        );
    }

    public function test_status_mengikuti_karakter_hari_terakhir(): void
    {
        $this->assertSame(DmsRosterRuleEngine::STATUS_MALAM, $this->evaluasi('PPMMM')->status);
        $this->assertSame(DmsRosterRuleEngine::STATUS_OFF, $this->evaluasi('PPPoo')->status);
        $this->assertSame(DmsRosterRuleEngine::STATUS_CUTI, $this->evaluasi('PPP'.str_repeat('o', 8))->status);
    }

    public function test_nomor_roster_naik_setiap_blok_cuti_selesai(): void
    {
        $e = $this->evaluasi(str_repeat('P', 6).str_repeat('o', 6).str_repeat('P', 6));

        $this->assertSame(2, $e->roster);
        // Kolom roster berjalan hanya menghitung cycle terakhir.
        $this->assertSame(6, $e->pagi);
        $this->assertSame(0, $e->off);
        $this->assertSame(6, $e->hadir);
        $this->assertSame(6, $e->onNoOff);
    }

    public function test_metrik_ytd_mengambil_run_terpanjang_dan_cuti_terpendek(): void
    {
        // roster 1: on-site 6 + cuti 14 · roster 2: on-site 20 + cuti 8
        $e = $this->evaluasi(
            str_repeat('P', 6).str_repeat('o', 14).
            str_repeat('P', 20).str_repeat('o', 8)
        );

        $this->assertSame(20, $e->onAll);
        $this->assertSame(2, $e->onAllR);
        $this->assertSame(8, $e->cutiMin);
        $this->assertSame(2, $e->cutiMinR);
    }

    public function test_onsite_berjalan_dihitung_balik_dari_hari_terakhir(): void
    {
        $e = $this->evaluasi(str_repeat('P', 10).str_repeat('o', 14).str_repeat('P', 30));

        $this->assertSame(30, $e->onCur);
        $this->assertFalse($this->engine->wajibCuti($e));
    }

    public function test_wajib_cuti_menyala_di_atas_tujuh_puluh_satu_hari_berjalan(): void
    {
        $belum = $this->evaluasi(str_repeat('P', 71));
        $sudah = $this->evaluasi(str_repeat('P', 72));

        $this->assertFalse($this->engine->wajibCuti($belum));
        $this->assertTrue($this->engine->wajibCuti($sudah));
        $this->assertFalse(
            $this->engine->wajibCuti($this->evaluasi(str_repeat('P', 72), 'Mekanik')),
            'Kategori longgar tidak pernah masuk daftar wajib cuti.',
        );
    }

    public function test_map1_menyala_saat_run_kerja_melebihi_blok_roster(): void
    {
        // thr 7 → blok roster 6, run 7 hari masih di bawah REG-1.
        $e = $this->evaluasi(str_repeat('P', 7), 'Operator Hauler', 7);

        $this->assertFalse($e->everRed);
        $this->assertTrue($e->everYel);
        $this->assertStringContainsString('melebihi blok roster (6)', $e->yel[0]);
    }

    public function test_map2_menandai_pergantian_shift_tanpa_off_untuk_pama(): void
    {
        $e = $this->evaluasi('PPPMMM', 'Operator Hauler', 7, 'pama');

        $this->assertSame('', $e->yel[2]);
        $this->assertStringContainsString('Wajib OFF saat ganti shift', $e->yel[3]);
    }

    public function test_map3_menandai_pagi_setelah_malam_untuk_non_pama(): void
    {
        $e = $this->evaluasi('MMMPPP', 'Operator Hauler', 7, 'block');

        $this->assertSame('', $e->yel[0]);
        $this->assertStringContainsString('Pagi setelah Malam', $e->yel[3]);
    }

    public function test_map_tidak_diuji_pada_run_yang_sudah_kena_reg1(): void
    {
        // Run 14 hari dengan pergantian shift: REG-1 menang, kuning tidak ditulis.
        $e = $this->evaluasi(str_repeat('P', 7).str_repeat('M', 7), 'Operator Hauler', 7, 'pama');

        $this->assertTrue($e->redK1);
        $this->assertFalse($e->everYel);
    }

    public function test_periode_membatasi_flag_yang_dilaporkan_tanpa_mengubah_metrik_ytd(): void
    {
        // Pelanggaran cuti pendek ada di awal tahun, periode dipilih di akhir.
        // Ekor pola sengaja patuh (run kerja 6 hari + 1 off) supaya tidak ada
        // flag REG-1/REG-2 yang jatuh di dalam periode terpilih.
        $pola = str_repeat('P', 5).str_repeat('o', 8).str_repeat('PPPPPPo', 3);
        $n = strlen($this->engine->normalisasiPola($pola));

        $penuh = $this->engine->evaluasi($pola, 'Operator Hauler', 7, 'block');
        $akhir = $this->engine->evaluasi($pola, 'Operator Hauler', 7, 'block', $n - 5, $n - 1);

        $this->assertTrue($penuh->adaMerahDiPeriode);
        $this->assertFalse($akhir->adaMerahDiPeriode, 'Flag di luar periode tidak masuk kolom Notes.');
        // everRed & cutiMin tetap YTD, lepas dari periode.
        $this->assertTrue($akhir->everRed);
        $this->assertSame(8, $akhir->cutiMin);
    }

    public function test_pola_kosong_tidak_melempar_error(): void
    {
        $e = $this->evaluasi('');

        $this->assertSame('', $e->pola);
        $this->assertSame(0, $e->onAll);
        $this->assertFalse($e->everRed);
    }

    private function evaluasi(
        string $pola,
        string $kategori = 'Operator Hauler',
        int $thr = 7,
        string $map = 'block',
    ) {
        return $this->engine->evaluasi($pola, $kategori, $thr, $map);
    }
}
