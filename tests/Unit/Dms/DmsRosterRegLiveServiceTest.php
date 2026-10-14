<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterRegLiveService;
use App\Services\Dms\Roster\DmsRosterRuleEngine;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Tests\TestCase;

/**
 * Hanya bagian murni (nilai): deret hari scan RFID → REG-1/2/3 per hari ini.
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
        $hasil = $this->svc->nilai(range(80, 99), self::JENDELA, 'Operator Hauler');

        $this->assertSame(['hari' => 20, 'mulai' => 80, 'selesai' => 99, 'terpotong' => false], $hasil['reg1']);
    }

    public function test_belum_scan_hari_ini_tidak_memutus_run_kerja(): void
    {
        // Scan sampai kemarin saja; hari ini belum ada scan.
        $hasil = $this->svc->nilai(range(80, 98), self::JENDELA, 'Operator Hauler');

        $this->assertSame(['hari' => 19, 'mulai' => 80, 'selesai' => 98, 'terpotong' => false], $hasil['reg1']);
    }

    public function test_reg2_onsite_lebih_dari_ambang_sejak_cuti_terakhir(): void
    {
        // Cuti 10..19 (10 hari tanpa scan), lalu on-site 20..99 = 80 hari
        // dengan off pendek tiap hari ke-7 (tidak memutus on-site).
        $scan = array_values(array_filter(range(20, 99), static fn (int $i): bool => $i % 7 !== 0 || $i === 99));
        $hasil = $this->svc->nilai(array_merge(range(0, 9), $scan), self::JENDELA, 'Operator Hauler');

        $this->assertSame(['hari' => 80, 'mulai' => 20, 'selesai' => 99, 'terpotong' => false], $hasil['reg2']);
        $this->assertSame(['hari' => 10, 'mulai' => 10, 'selesai' => 19, 'terpotong' => false], $hasil['reg3']);
    }

    public function test_onsite_yang_menyentuh_awal_jendela_ditandai_terpotong(): void
    {
        $scan = array_values(array_filter(range(0, 99), static fn (int $i): bool => $i % 5 !== 0));
        $hasil = $this->svc->nilai($scan, self::JENDELA, 'Operator Hauler');

        $this->assertTrue($hasil['reg2']['terpotong']);
        $this->assertSame(100, $hasil['reg2']['hari']);
    }

    public function test_cuti_terpotong_di_awal_jendela_tidak_dinilai_reg3(): void
    {
        // Hari 0..7 tanpa scan: cuti yang mulainya di luar jendela.
        $hasil = $this->svc->nilai(range(8, 99), self::JENDELA, 'Operator Hauler');

        $this->assertNull($hasil['reg3']);
    }

    public function test_kategori_longgar_tidak_kena_reg(): void
    {
        $hasil = $this->svc->nilai(range(0, 99), self::JENDELA, 'Mekanik');

        $this->assertSame(['reg1' => null, 'reg2' => null, 'reg3' => null], $hasil);
    }
}
