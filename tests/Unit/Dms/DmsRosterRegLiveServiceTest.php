<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterRegLiveService;
use App\Services\Dms\Roster\DmsRosterRuleEngine;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
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

    public function test_susun_hari_memasangkan_checkout_shift_malam_di_hari_berikutnya(): void
    {
        $awal = CarbonImmutable::parse('2026-10-10');
        $tap = [
            $this->tap('2026-10-10 17:25:49', 'CHECK IN', 'Pos BPL'),
            $this->tap('2026-10-11 06:29:43', 'CHECK OUT', 'Pos Office'),
            $this->tap('2026-10-11 17:22:44', 'CHECK IN', 'Pos BPL'),
        ];

        $hari = $this->svc->susunHari($tap, $awal, 3);

        $this->assertSame('M', $hari[0]['kode']);
        $this->assertSame(['jam' => '17:25', 'gate' => 'Pos BPL'], $hari[0]['masuk']);
        $this->assertSame(['jam' => '06:29', 'tanggal' => '11 Oct', 'gate' => 'Pos Office'], $hari[0]['keluar']);
        $this->assertSame('13j 3m', $hari[0]['durasi']);
        // Check-out 11 Okt pagi milik shift 10 Okt, bukan shift 11 Okt.
        $this->assertNull($hari[1]['keluar']);
        $this->assertSame('o', $hari[2]['kode']);
    }

    public function test_susun_hari_tidak_memakai_checkout_sesudah_checkin_berikutnya(): void
    {
        $awal = CarbonImmutable::parse('2026-10-09');
        $tap = [
            $this->tap('2026-10-09 05:27:36', 'CHECK IN', 'A'),
            $this->tap('2026-10-10 17:25:49', 'CHECK IN', 'A'),
            $this->tap('2026-10-11 06:29:43', 'CHECK OUT', 'B'),
        ];

        $hari = $this->svc->susunHari($tap, $awal, 3);

        $this->assertSame('P', $hari[0]['kode']);
        $this->assertNull($hari[0]['keluar']);
        $this->assertNull($hari[0]['durasi']);
        $this->assertSame('B', $hari[1]['keluar']['gate']);
    }

    public function test_susun_hari_tap_tidak_lolos_tampil_tapi_tidak_dihitung_hadir(): void
    {
        $awal = CarbonImmutable::parse('2026-10-09');
        $tap = [$this->tap('2026-10-09 06:00:00', 'CHECK IN', 'A', false)];

        $hari = $this->svc->susunHari($tap, $awal, 1);

        $this->assertSame('o', $hari[0]['kode']);
        $this->assertCount(1, $hari[0]['tap']);
        $this->assertFalse($hari[0]['tap'][0]['lolos']);
    }

    /**
     * @return array{waktu:string,jenis:string,gate:string,lolos:bool}
     */
    private function tap(string $waktu, string $jenis, string $gate, bool $lolos = true): array
    {
        return ['waktu' => $waktu, 'jenis' => $jenis, 'gate' => $gate, 'lolos' => $lolos];
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
