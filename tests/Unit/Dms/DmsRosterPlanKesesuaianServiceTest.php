<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterPlanKesesuaianService;
use App\Services\Dms\Roster\DmsRosterRfidReader;
use App\Services\Dms\Roster\DmsRosterRuleEngine;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Bagian murni Plan Kesesuaian Roster: RFID ke belakang disambung plan ke
 * depan, dinilai dengan aturan > 70 hari on-site / > 13 hari berturut-turut.
 * Jendela mulai 1 Jan 2026; offset 99 = hari ini (10 Apr 2026).
 */
class DmsRosterPlanKesesuaianServiceTest extends TestCase
{
    private DmsRosterPlanKesesuaianService $svc;

    private CarbonImmutable $awal;

    private CarbonImmutable $hariIni;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DmsRosterPlanKesesuaianService(
            new DmsRosterRuleEngine(),
            new DmsRosterRfidReader(new PembatasanLVOlapQuery()),
        );
        $this->awal = CarbonImmutable::parse('2026-01-01');
        $this->hariIni = $this->awal->addDays(99);
    }

    public function test_plan_membuat_onsite_lewat_70_hari(): void
    {
        // On-site mulai offset 40 (sebelumnya 40 hari tanpa tap = cuti), off tiap kelipatan 7.
        $hasil = $this->nilai($this->scan(40, 99), $this->plan(100, 125));

        $this->assertSame(60, $hasil['hari_onsite']);
        $this->assertSame('AKAN_70', $hasil['status']);
        $this->assertSame($this->tgl(110), $hasil['tgl_71']);
        $this->assertSame($this->tgl(110), $hasil['tgl_langgar']);
    }

    public function test_plan_membuat_kerja_beruntun_lewat_13_hari(): void
    {
        // Kerja tanpa off 88..99 = 12 hari; plan D di 100 & 101 → hari ke-14 di 101.
        $hasil = $this->nilai($this->scan(88, 99, 0), $this->plan(100, 120, 0));

        $this->assertSame(12, $hasil['hari_beruntun']);
        $this->assertSame('AKAN_13', $hasil['status']);
        $this->assertSame($this->tgl(101), $hasil['tgl_14']);
    }

    public function test_off_pendek_memutus_beruntun_tapi_tidak_onsite(): void
    {
        $plan = $this->plan(100, 130, 0);
        foreach ([100, 101, 102] as $i) {
            $plan[$this->tgl($i)] = 'OFF';
        }
        $hasil = $this->nilai($this->scan(88, 99, 0), $plan);

        // Run kerja mulai lagi di 103 → hari ke-14 di 116; on-site tetap tersambung dari 88.
        $this->assertSame($this->tgl(116), $hasil['tgl_14']);
        $this->assertSame(12 + 31, $hasil['onsite_akhir']);
    }

    public function test_sakit_dan_izin_dihitung_seperti_off(): void
    {
        $plan = $this->plan(100, 130, 0);
        $plan[$this->tgl(100)] = 'S';
        $plan[$this->tgl(101)] = 'I';
        $hasil = $this->nilai($this->scan(88, 99, 0), $plan);

        $this->assertSame($this->tgl(115), $hasil['tgl_14']);
        $this->assertSame(12 + 31, $hasil['onsite_akhir']);
    }

    public function test_cuti_di_plan_mereset_onsite(): void
    {
        $plan = $this->plan(100, 140);
        foreach (range(100, 113) as $i) {
            $plan[$this->tgl($i)] = 'CT';
        }
        $hasil = $this->nilai($this->scan(40, 99), $plan);

        $this->assertNull($hasil['tgl_71']);
        $this->assertSame('AMAN', $hasil['status']);
        $this->assertSame(27, $hasil['onsite_akhir']);
    }

    public function test_off_lebih_dari_5_hari_di_plan_dianggap_cuti(): void
    {
        $plan = $this->plan(100, 140);
        foreach (range(100, 105) as $i) {
            $plan[$this->tgl($i)] = 'OFF';
        }
        $hasil = $this->nilai($this->scan(40, 99), $plan);

        $this->assertNull($hasil['tgl_71']);
        $this->assertSame(35, $hasil['onsite_akhir']);
    }

    public function test_plan_yang_segera_habis(): void
    {
        $hasil = $this->nilai($this->scan(80, 99), $this->plan(100, 102));

        $this->assertSame('PLAN_HABIS', $hasil['status']);
        $this->assertSame($this->tgl(102), $hasil['proyeksi_sampai']);
    }

    public function test_proyeksi_berhenti_di_tanggal_plan_yang_kosong(): void
    {
        $plan = $this->plan(100, 140);
        unset($plan[$this->tgl(103)]);
        $hasil = $this->nilai($this->scan(80, 99), $plan);

        $this->assertSame($this->tgl(102), $hasil['proyeksi_sampai']);
    }

    public function test_sudah_melanggar_menurut_rfid(): void
    {
        $hasil = $this->nilai($this->scan(20, 99), $this->plan(100, 130));

        $this->assertSame(80, $hasil['hari_onsite']);
        $this->assertTrue($hasil['sudah_onsite']);
        $this->assertSame('SUDAH_MELANGGAR', $hasil['status']);
    }

    public function test_kategori_longgar_tidak_dinilai(): void
    {
        $hasil = $this->nilai($this->scan(20, 99), $this->plan(100, 130), true);

        $this->assertSame('LONGGAR', $hasil['status']);
        $this->assertNull($hasil['tgl_71']);
    }

    public function test_belum_tap_hari_ini_memakai_plan_hari_ini(): void
    {
        $scan = $this->scan(88, 98, 0);
        $hasil = $this->nilai($scan, $this->plan(99, 120, 0));

        // 88..98 = 11 hari + plan hari ini = 12.
        $this->assertSame(12, $hasil['hari_beruntun']);
        $this->assertSame('p', substr($hasil['tl_sumber'], $hasil['tl_kini'], 1));
    }

    public function test_kepatuhan_plan_vs_tap(): void
    {
        $plan = [
            $this->tgl(95) => 'D1',   // tap pagi → sesuai
            $this->tgl(96) => 'N1',   // tap pagi → sesuai, beda shift
            $this->tgl(97) => 'D2',   // tidak tap → kerja tanpa tap
            $this->tgl(98) => 'OFF',  // tap → tap saat off
            $this->tgl(99) => 'OFF',  // hari ini belum tap → tidak dinilai
        ];
        $k = DmsRosterPlanKesesuaianService::kepatuhan([95 => 'P', 96 => 'P', 98 => 'P'], $this->awal, $this->hariIni, $plan);

        $this->assertSame(['dinilai' => 4, 'sesuai' => 2, 'kerja_tanpa_tap' => 1, 'tap_saat_off' => 1, 'beda_shift' => 1, 'persen' => 50.0], $k);
    }

    public function test_kode_plan_ke_deret(): void
    {
        $this->assertSame('P', DmsRosterPlanKesesuaianService::kodeDeret('D13'));
        $this->assertSame('P', DmsRosterPlanKesesuaianService::kodeDeret('ds'));
        $this->assertSame('M', DmsRosterPlanKesesuaianService::kodeDeret('N7'));
        $this->assertSame('c', DmsRosterPlanKesesuaianService::kodeDeret('CT'));
        $this->assertSame('o', DmsRosterPlanKesesuaianService::kodeDeret('OFF'));
        $this->assertSame('o', DmsRosterPlanKesesuaianService::kodeDeret('I'));
        $this->assertNull(DmsRosterPlanKesesuaianService::kodeDeret(''));
        $this->assertNull(DmsRosterPlanKesesuaianService::kodeDeret('X'));
    }

    /**
     * Tap pagi di offset $dari..$sampai; tiap kelipatan $offTiap tidak tap (0 = tanpa off).
     *
     * @return array<int, string>
     */
    private function scan(int $dari, int $sampai, int $offTiap = 7): array
    {
        $out = [];
        foreach (range($dari, $sampai) as $i) {
            if ($offTiap === 0 || $i % $offTiap !== 0) {
                $out[$i] = 'P';
            }
        }

        return $out;
    }

    /**
     * Plan D1 di offset $dari..$sampai; tiap kelipatan $offTiap = OFF (0 = tanpa off).
     *
     * @return array<string, string>
     */
    private function plan(int $dari, int $sampai, int $offTiap = 7): array
    {
        $out = [];
        foreach (range($dari, $sampai) as $i) {
            $out[$this->tgl($i)] = $offTiap !== 0 && $i % $offTiap === 0 ? 'OFF' : 'D1';
        }

        return $out;
    }

    private function tgl(int $i): string
    {
        return $this->awal->addDays($i)->toDateString();
    }

    /**
     * @param  array<int, string>  $scan
     * @param  array<string, string>  $plan
     * @return array<string, mixed>
     */
    private function nilai(array $scan, array $plan, bool $longgar = false): array
    {
        return $this->svc->nilai($scan, $this->awal, $this->hariIni, $plan, $longgar);
    }
}
