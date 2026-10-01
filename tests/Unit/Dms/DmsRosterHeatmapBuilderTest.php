<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterHeatmapBuilder;
use PHPUnit\Framework\TestCase;

final class DmsRosterHeatmapBuilderTest extends TestCase
{
    private DmsRosterHeatmapBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new DmsRosterHeatmapBuilder;
    }

    public function test_mengembalikan_null_saat_seri_kosong(): void
    {
        $this->assertNull($this->builder->bangun([], 100, '2026-01-01'));
    }

    public function test_mengembalikan_null_saat_populasi_nol(): void
    {
        $this->assertNull($this->builder->bangun([1, 2, 3], 0, '2026-01-01'));
    }

    public function test_kolom_bertambah_setiap_hari_senin(): void
    {
        // 1 Jan 2026 jatuh hari Kamis, jadi 11 hari pertama menyentuh dua Senin
        // (5 dan 12 Jan) — hari ke-12 sudah masuk kolom ketiga.
        $hasil = $this->builder->bangun(array_fill(0, 12, 10), 100, '2026-01-01');

        $this->assertSame(3, $hasil['kolom']);
    }

    public function test_hari_pertama_jatuh_di_baris_sesuai_nama_harinya(): void
    {
        // Baris 0 = Senin … baris 6 = Minggu. 1 Jan 2026 = Kamis = baris 3.
        $hasil = $this->builder->bangun([10], 100, '2026-01-01');

        $this->assertSame([], $hasil['grid'][0]);
        $this->assertArrayHasKey(0, $hasil['grid'][3]);
        $this->assertStringContainsString('1 Jan 2026', $hasil['grid'][3][0]['judul']);
    }

    public function test_sel_kolom_sebelum_hari_pertama_dibiarkan_kosong(): void
    {
        // Senin/Selasa/Rabu di kolom 0 tidak punya tanggal karena periode baru
        // mulai hari Kamis — view menggambarnya sebagai sel lvl-0 is-empty.
        $hasil = $this->builder->bangun(array_fill(0, 30, 10), 100, '2026-01-01');

        foreach ([0, 1, 2] as $baris) {
            $this->assertArrayNotHasKey(0, $hasil['grid'][$baris]);
        }
    }

    public function test_kuintil_membagi_hari_hampir_rata(): void
    {
        // Seri naik monoton: tiap tingkat harus kebagian seperlima dari 100 hari.
        $harian = range(1, 100);
        $hasil = $this->builder->bangun($harian, 1000, '2026-01-01');

        $cacah = array_fill(1, 5, 0);
        foreach ($hasil['grid'] as $baris) {
            foreach ($baris as $sel) {
                $cacah[$sel['level']]++;
            }
        }

        $this->assertSame(100, array_sum($cacah));
        foreach ($cacah as $level => $n) {
            $this->assertGreaterThanOrEqual(19, $n, "tingkat {$level} terlalu sedikit");
            $this->assertLessThanOrEqual(21, $n, "tingkat {$level} terlalu banyak");
        }
    }

    public function test_pelanggaran_lebih_sedikit_berarti_tingkat_lebih_tinggi(): void
    {
        // Level 5 = paling patuh; pastikan arah skalanya tidak terbalik.
        $hasil = $this->builder->bangun(range(100, 1), 1000, '2026-01-01');

        $pertama = $hasil['grid'][3][0]['level']; // 100 pelanggaran — terburuk
        $this->assertSame(1, $pertama);
    }

    public function test_seri_datar_hanya_menghasilkan_satu_tingkat(): void
    {
        $hasil = $this->builder->bangun(array_fill(0, 40, 50), 100, '2026-01-01');

        // Semua hari punya kepatuhan sama, jadi legendanya cuma
        // 'tidak ada data' + satu tingkat — tanpa baris yang tak terpakai.
        $this->assertCount(2, $hasil['legenda']);
        $this->assertSame('50,0%', $hasil['legenda'][1]['teks']);
        $this->assertSame(0.0, $hasil['sebaranHari']);
    }

    public function test_legenda_hanya_memuat_tingkat_yang_terpakai(): void
    {
        $hasil = $this->builder->bangun(range(1, 100), 1000, '2026-01-01');

        $terpakai = [];
        foreach ($hasil['grid'] as $baris) {
            foreach ($baris as $sel) {
                $terpakai[$sel['level']] = true;
            }
        }

        // Baris legenda (minus 'tidak ada data') harus persis sebanyak tingkat
        // yang muncul di grid, tidak kurang dan tidak lebih.
        $this->assertCount(count($terpakai) + 1, $hasil['legenda']);
        $this->assertSame('Tidak ada data', $hasil['legenda'][0]['teks']);
        foreach (array_slice($hasil['legenda'], 1) as $l) {
            $this->assertArrayHasKey($l['level'], $terpakai);
        }
    }

    public function test_legenda_mencetak_persen_asli_bukan_ambang_tetap(): void
    {
        $hasil = $this->builder->bangun(range(1, 100), 1000, '2026-01-01');

        // Ambang tetap gaya "<50% / 50–74% / 75–89%" tidak boleh muncul lagi.
        foreach (array_slice($hasil['legenda'], 1) as $l) {
            $this->assertMatchesRegularExpression('/^\d+,\d%(–\d+,\d%)?$/u', $l['teks']);
        }
    }

    public function test_puncak_menunjuk_hari_dengan_pelanggaran_terbanyak(): void
    {
        $hasil = $this->builder->bangun([5, 5, 99, 5, 5], 100, '2026-01-01');

        $this->assertSame(99, $hasil['puncak']['jumlah']);
        $this->assertSame('3 Jan 2026', $hasil['puncak']['tanggal']);
    }

    public function test_sebaran_hari_nol_saat_tiap_hari_sama_padat(): void
    {
        // 28 hari = 4 minggu penuh, tiap hari dalam seminggu dapat nilai sama.
        $hasil = $this->builder->bangun(array_fill(0, 28, 7), 100, '2026-01-01');

        $this->assertSame(0.0, $hasil['sebaranHari']);
    }

    public function test_sebaran_hari_terbaca_saat_satu_hari_jauh_lebih_padat(): void
    {
        // Senin (indeks 4, 11, 18, 25 dari 1 Jan) dibuat jauh lebih padat.
        $harian = array_fill(0, 28, 10);
        foreach ([4, 11, 18, 25] as $i) {
            $harian[$i] = 100;
        }
        $hasil = $this->builder->bangun($harian, 1000, '2026-01-01');

        $this->assertSame('Senin', $hasil['perHari'][0]['nama']);
        $this->assertSame(100, $hasil['perHari'][0]['rata']);
        $this->assertGreaterThan(0.05, $hasil['sebaranHari']);
    }

    public function test_label_bulan_dicetak_sekali_per_bulan(): void
    {
        $hasil = $this->builder->bangun(array_fill(0, 90, 10), 100, '2026-01-01');

        $terisi = array_values(array_filter($hasil['labelKolom'], static fn (string $v): bool => $v !== ''));

        $this->assertSame(count($terisi), count(array_unique($terisi)));
        $this->assertCount(count($hasil['labelKolom']), $hasil['labelKolom']);
        $this->assertSame($hasil['kolom'], count($hasil['labelKolom']));
    }

    public function test_rata_harian_dibulatkan_dari_seluruh_seri(): void
    {
        $hasil = $this->builder->bangun([10, 20, 31], 100, '2026-01-01');

        $this->assertSame(20, $hasil['rata']);
    }
}
