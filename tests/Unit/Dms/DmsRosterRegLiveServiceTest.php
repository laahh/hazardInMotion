<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterRegLiveService;
use App\Services\Dms\Roster\DmsRosterRuleEngine;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Tests\TestCase;

/**
 * Hanya bagian murni (nilai): deret hari scan RFID → REG/MAP per hari ini.
 * Jendela 100 hari; offset 99 = hari ini.
 */
class DmsRosterRegLiveServiceTest extends TestCase
{
    private const JENDELA = 100;

    private DmsRosterRegLiveService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DmsRosterRegLiveService(new DmsRosterRuleEngine(), new PembatasanLVOlapQuery());
    }

    public function test_reg1_kerja_beruntun_sampai_hari_ini(): void
    {
        $hasil = $this->nilai($this->pagi(range(80, 99)));

        $this->assertSame(['hari' => 20, 'mulai' => 80, 'selesai' => 99, 'terpotong' => false], $hasil['reg1']);
    }

    public function test_belum_scan_hari_ini_tidak_memutus_run_kerja(): void
    {
        $hasil = $this->nilai($this->pagi(range(80, 98)));

        $this->assertSame(['hari' => 19, 'mulai' => 80, 'selesai' => 98, 'terpotong' => false], $hasil['reg1']);
    }

    public function test_reg2_onsite_lebih_dari_ambang_sejak_cuti_terakhir(): void
    {
        // Kerja 0..9, cuti 10..19, lalu on-site 20..99 = 80 hari dengan off
        // pendek tiap kelipatan 7 (tidak memutus on-site).
        $onsite = array_filter(range(20, 99), static fn (int $i): bool => $i % 7 !== 0 || $i === 99);
        $hasil = $this->nilai($this->pagi(array_merge(range(0, 9), $onsite)));

        $this->assertSame(['hari' => 80, 'mulai' => 20, 'selesai' => 99, 'terpotong' => false], $hasil['reg2']);
        $this->assertSame(['hari' => 10, 'mulai' => 10, 'selesai' => 19, 'terpotong' => false], $hasil['reg3']);
    }

    public function test_onsite_yang_menyentuh_awal_jendela_ditandai_terpotong(): void
    {
        $hasil = $this->nilai($this->pagi(array_filter(range(0, 99), static fn (int $i): bool => $i % 5 !== 0)));

        $this->assertTrue($hasil['reg2']['terpotong']);
        $this->assertSame(100, $hasil['reg2']['hari']);
    }

    public function test_cuti_terpotong_di_awal_jendela_tidak_dinilai_reg3(): void
    {
        $hasil = $this->nilai($this->pagi(range(8, 99)));

        $this->assertNull($hasil['reg3']);
    }

    public function test_map3_pagi_setelah_malam_di_blok_kerja_terakhir(): void
    {
        // Cuti 80..89, lalu blok kerja MMMPP sampai hari ini.
        $scan = $this->pagi(range(70, 79)) + [90 => 'M', 91 => 'M', 92 => 'M', 93 => 'P', 94 => 'P'];

        $hasil = $this->nilai($scan, 'Operator Hauler', 'PT Bukit Makmur Mandiri Utama');

        $this->assertSame(93, $hasil['map3']['pada']);
        $this->assertNull($hasil['map2']);
    }

    public function test_kategori_longgar_tidak_kena_reg(): void
    {
        $hasil = $this->nilai($this->pagi(range(0, 99)), 'Mekanik');

        $this->assertNull($hasil['reg1']);
        $this->assertNull($hasil['reg2']);
        $this->assertNull($hasil['reg3']);
    }

    /**
     * @param  iterable<int>  $offset
     * @return array<int, string>
     */
    private function pagi(iterable $offset): array
    {
        $out = [];
        foreach ($offset as $i) {
            $out[$i] = 'P';
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $scan
     * @return array<string, mixed>
     */
    private function nilai(array $scan, string $kategori = 'Operator Hauler', string $perusahaan = ''): array
    {
        return $this->svc->nilai($scan, self::JENDELA, $kategori, $perusahaan);
    }
}
