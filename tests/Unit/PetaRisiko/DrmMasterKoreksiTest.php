<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmMaster;
use App\Services\PetaRisiko\Drm\DrmSql;
use App\Services\PetaRisiko\Drm\Master\DrmMasterSkema;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Master dari database (tabel drm_master_*, bisa dikoreksi): koreksi manual harus menimpa aturan regex config.
 */
class DrmMasterKoreksiTest extends TestCase
{
    private function master(array $tambahan = []): DrmMaster
    {
        return DrmMaster::dariArray($tambahan + [
            'baris' => [
                ['ri' => 11, 'aid' => 3, 'aktivitas' => 'Jalan Tambang', 'sub' => 'Grading', 'bahaya' => 'Jalan licin', 'kb' => 'High', 'kelompokM01' => '', 'umum' => false, 'idh' => [7]],
                ['ri' => 12, 'aid' => 4, 'aktivitas' => 'Dumping OB', 'sub' => 'Disposal', 'bahaya' => 'Unit terguling', 'kb' => 'High', 'kelompokM01' => '', 'umum' => false, 'idh' => [8]],
            ],
            'grup' => [1 => 'Loading Point'], 'grupBaris' => [1 => [11, 12]],
            'dl' => [['id' => 1, 'site' => 'LMO', 'lokasi' => 'Pit A', 'detail' => 'Front', 'gid' => 1]],
        ]);
    }

    public function test_kelompok_dan_tema_dari_database_menimpa_regex(): void
    {
        $cfg = config('peta_risiko_drm');
        // Tanpa koreksi: regex config (aktivitas "Jalan Tambang" → K13).
        $this->assertSame('K13', $this->master()->kelompokBaris($cfg['proses_bisnis'])[11]);

        $m = $this->master([
            'kelompokAkt' => [3 => 'K16', 4 => 'K16'],
            'kelompokDaftar' => ['K16' => ['Dumping & Penimbunan', 'inti', 'dump'], 'K0' => ['Lainnya', 'inti', '']],
            'tema' => [5 => ['Jalan/berm', 'jalan', 'jalan']],
            'temaBarisDb' => [5 => [12]],
            'lokasiPja' => [['LMO', 'Pit A', 'PJA Pit BC']],
        ]);
        $this->assertTrue($m->dariDatabase());
        $this->assertSame([11 => 'K16', 12 => 'K16'], $m->kelompokBaris($cfg['proses_bisnis']));
        $this->assertSame([5 => [12]], $m->temaBaris($cfg['tema_checklist']));

        $efektif = $m->cfgEfektif($cfg);
        $this->assertSame(['K16', 'K0'], array_keys($efektif['proses_bisnis']));
        $this->assertSame([5], array_keys($efektif['tema_checklist']));

        // Lokasi → PJA dari database dikirim sebagai literal lp dan menggantikan aaj_lokasi_dan_pja.
        $lit = $m->literal($efektif);
        $this->assertStringContainsString('['.DrmMaster::crcLokasi('LMO', 'Pit A').','.DrmMaster::crc('PJA Pit BC').']', $lit['lp']);
        $sql = (new DrmSql($efektif, CarbonImmutable::parse('2026-11-14'), $lit))->detailLokasi();
        $this->assertStringNotContainsString('aaj_lokasi_dan_pja', $sql);
        $this->assertStringContainsString('JOIN mpn p ON p.nk = aj.nk', $sql);
    }

    public function test_tanpa_koreksi_pja_tetap_dari_aaj(): void
    {
        $cfg = config('peta_risiko_drm');
        $m = $this->master();
        $this->assertFalse($m->dariDatabase());
        $this->assertArrayNotHasKey('lp', $m->literal($cfg));
        $this->assertStringContainsString('FROM obds.aaj_lokasi_dan_pja', (new DrmSql($cfg, CarbonImmutable::parse('2026-11-14'), $m->literal($cfg)))->detailLokasi());
    }

    public function test_skema_master_konsisten(): void
    {
        foreach (DrmMasterSkema::semua() as $kode => $s) {
            $this->assertArrayHasKey($s['urut'], $s['kolom'] + [$s['kunci'] => 1], "urut {$kode}");
            foreach ($s['cari'] as $c) {
                $this->assertArrayHasKey($c, $s['kolom'], "cari {$kode}.{$c}");
            }
            if ($s['kunciManual']) {
                $this->assertArrayHasKey($s['kunci'], $s['kolom'], "kunci manual {$kode}");
            }
        }
    }
}
