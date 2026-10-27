<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterRegLiveService;
use App\Services\Dms\Roster\DmsRosterRfidReader;
use App\Services\Dms\Roster\DmsRosterRuleEngine;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Bagian murni DmsRosterRegLiveService: aturan banned dari deret hari scan
 * RFID, aturan jam kerja, dan pemasangan tap. Jendela 100 hari; offset 99 =
 * hari ini.
 */
class DmsRosterRegLiveServiceTest extends TestCase
{
    private const JENDELA = 100;

    private DmsRosterRegLiveService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DmsRosterRegLiveService(
            new DmsRosterRuleEngine(),
            new DmsRosterRfidReader(new PembatasanLVOlapQuery()),
        );
    }

    public function test_beruntun_lebih_dari_13_hari_sampai_hari_ini(): void
    {
        $hasil = $this->nilai($this->pagi(range(80, 99)));

        $this->assertSame(['hari' => 20, 'mulai' => 80, 'selesai' => 99, 'terpotong' => false], $hasil['beruntun']);
    }

    public function test_belum_scan_hari_ini_tidak_memutus_run_kerja(): void
    {
        $hasil = $this->nilai($this->pagi(range(80, 98)));

        $this->assertSame(19, $hasil['beruntun']['hari']);
        $this->assertSame(98, $hasil['beruntun']['selesai']);
    }

    public function test_onsite_memakai_ambang_aturan_lebih_dari_70_hari(): void
    {
        // Cuti 0..19 lalu on-site 20..90 = 71 hari, off pendek tiap kelipatan 7.
        $onsite = array_filter(range(20, 90), static fn (int $i): bool => $i % 7 !== 0 || $i === 90);
        $hasil = $this->svc->nilai($this->pagi($onsite), 91, 'Operator Hauler');

        $this->assertSame(['hari' => 71, 'mulai' => 20, 'selesai' => 90, 'terpotong' => false], $hasil['onsite']);
    }

    public function test_onsite_tepat_70_hari_belum_pelanggaran(): void
    {
        $onsite = array_filter(range(20, 89), static fn (int $i): bool => $i % 7 !== 0 || $i === 89);
        $hasil = $this->svc->nilai($this->pagi($onsite), 90, 'Operator Hauler');

        $this->assertNull($hasil['onsite']);
    }

    public function test_mendekati_hari_ke_60_sampai_70_dengan_off_pendek_ikut_dihitung(): void
    {
        // Cuti 0..19 lalu on-site 20..84 = 65 hari, off pendek tiap kelipatan 7.
        $onsite = array_filter(range(20, 84), static fn (int $i): bool => $i % 7 !== 0 || $i === 84);

        $this->assertSame(['hari' => 65, 'mulai' => 20, 'selesai' => 84], $this->svc->mendekati($this->pagi($onsite), 85, 'Operator A2B'));
    }

    public function test_mendekati_batas_bawah_60_dan_batas_atas_70(): void
    {
        $this->assertSame(60, $this->svc->mendekati($this->pagi(range(20, 79)), 80, 'Operator A2B')['hari']);
        $this->assertNull($this->svc->mendekati($this->pagi(range(20, 78)), 79, 'Operator A2B'));   // 59 hari
        $this->assertSame(70, $this->svc->mendekati($this->pagi(range(20, 89)), 90, 'Operator A2B')['hari']);
        $this->assertNull($this->svc->mendekati($this->pagi(range(20, 90)), 91, 'Operator A2B'));   // 71 = sudah melanggar
    }

    public function test_mendekati_tidak_berlaku_untuk_kategori_longgar(): void
    {
        $this->assertNull($this->svc->mendekati($this->pagi(range(20, 84)), 85, 'Mekanik'));
    }

    public function test_onsite_yang_menyentuh_awal_jendela_ditandai_terpotong(): void
    {
        $hasil = $this->nilai($this->pagi(array_filter(range(0, 99), static fn (int $i): bool => $i % 5 !== 0)));

        $this->assertTrue($hasil['onsite']['terpotong']);
        $this->assertSame(100, $hasil['onsite']['hari']);
    }

    public function test_map3_pagi_setelah_malam_di_blok_kerja_terakhir(): void
    {
        $scan = $this->pagi(range(70, 79)) + [90 => 'M', 91 => 'M', 92 => 'M', 93 => 'P', 94 => 'P'];

        $hasil = $this->nilai($scan, 'Operator Hauler', 'PT Bukit Makmur Mandiri Utama');

        $this->assertSame(93, $hasil['map3']['pada']);
        $this->assertNull($hasil['map2']);
    }

    public function test_riwayat_cari_manual_mencatat_run_aturan_dengan_tanggal(): void
    {
        // Jendela 75 hari dari 1 Agu: on-site penuh sejak awal (terpotong),
        // off tiap hari ke-5, lalu 15 hari tanpa off di ujung.
        $pola = substr(str_repeat('PPPPo', 12), 0, 60).str_repeat('P', 15);
        $eval = (new DmsRosterRuleEngine())->evaluasi($pola, 'Operator Hauler', 7, 'block');

        $awal = CarbonImmutable::create(2026, 8, 1);
        $riwayat = $this->svc->riwayat($eval, $awal);

        $this->assertSame(['Periode kerja', 'Bekerja berturut-turut'], array_column($riwayat, 'jenis'));
        $this->assertSame('≥ 75 hari on-site tanpa cuti (maks 70)', $riwayat[0]['teks']);
        $this->assertSame(
            [$awal->translatedFormat('j M'), $awal->addDays(74)->translatedFormat('j M')],
            [$riwayat[0]['mulai'], $riwayat[0]['selesai']],
        );
        $this->assertSame('15 hari tanpa off (maks 13)', $riwayat[1]['teks']);
    }

    public function test_riwayat_mencatat_mapping_dan_shift_lebih_dari_12_jam(): void
    {
        // BUMA (bukan PAMA): Pagi setelah Malam dalam satu blok = MAP-3.
        $pola = str_repeat('o', 20).'MMMPP'.'oo';
        $eval = (new DmsRosterRuleEngine())->evaluasi($pola, 'Operator Hauler', 7, 'block');
        $awal = CarbonImmutable::create(2026, 8, 1);
        $hari = $this->svc->susunHari([
            ['waktu' => '2026-08-21 05:30:00', 'jenis' => 'CHECK IN', 'gate' => 'G1', 'lolos' => true],
            ['waktu' => '2026-08-21 19:00:00', 'jenis' => 'CHECK OUT', 'gate' => 'G2', 'lolos' => true],
        ], $awal, strlen($pola));

        $riwayat = $this->svc->riwayat($eval, $awal, $hari);
        $jenis = array_column($riwayat, 'jenis', 'hari');

        $this->assertSame('Waktu kerja > 12 jam', $jenis[20]);
        $this->assertStringStartsWith('13j 30m', $riwayat[array_search(20, array_column($riwayat, 'hari'), true)]['teks']);
        $this->assertContains('Tidak sesuai mapping', array_column($riwayat, 'jenis'));
        $this->assertSame(['warning'], array_values(array_unique(array_column($riwayat, 'warna'))));
    }

    public function test_riwayat_kategori_longgar_kosong(): void
    {
        $eval = (new DmsRosterRuleEngine())->evaluasi(str_repeat('P', 75), 'Mekanik', 7, 'block');

        $this->assertSame([], $this->svc->riwayat($eval, CarbonImmutable::create(2026, 8, 1)));
    }

    public function test_kategori_longgar_tidak_kena_banned(): void
    {
        $hasil = $this->nilai($this->pagi(range(0, 99)), 'Mekanik');

        $this->assertNull($hasil['onsite']);
        $this->assertNull($hasil['beruntun']);
    }

    public function test_jam_kerja_lebih_dari_12_jam_pada_shift_terkini(): void
    {
        $hariIni = CarbonImmutable::parse('2026-10-14');

        $kena = $this->svc->nilaiJamKerja($this->shift('2026-10-13 05:20:00', '2026-10-13 18:30:00'), $hariIni);
        $pas = $this->svc->nilaiJamKerja($this->shift('2026-10-13 06:00:00', '2026-10-13 18:00:00'), $hariIni);
        $lama = $this->svc->nilaiJamKerja($this->shift('2026-10-08 05:20:00', '2026-10-08 18:30:00'), $hariIni);

        $this->assertSame(790, $kena['menit']);
        $this->assertNull($pas, 'tepat 12 jam bukan pelanggaran');
        $this->assertNull($lama, 'shift di luar jendela terkini tidak dinilai');
    }

    public function test_susun_hari_memasangkan_checkout_shift_malam_di_hari_berikutnya(): void
    {
        $hari = $this->svc->susunHari([
            $this->tap('2026-10-10 17:25:49', 'CHECK IN', 'Pos BPL'),
            $this->tap('2026-10-11 06:29:43', 'CHECK OUT', 'Pos Office'),
            $this->tap('2026-10-11 17:22:44', 'CHECK IN', 'Pos BPL'),
        ], CarbonImmutable::parse('2026-10-10'), 3);

        $this->assertSame('M', $hari[0]['kode']);
        $this->assertSame(['jam' => '17:25', 'gate' => 'Pos BPL'], $hari[0]['masuk']);
        $this->assertSame('06:29', $hari[0]['keluar']['jam']);
        $this->assertTrue($hari[0]['keluar']['beda_hari']);
        $this->assertSame(783, $hari[0]['durasi_menit']);
        $this->assertSame('13j 3m', $hari[0]['durasi']);
        // Check-out 11 Okt pagi milik shift 10 Okt, bukan shift 11 Okt.
        $this->assertNull($hari[1]['keluar']);
        $this->assertSame('o', $hari[2]['kode']);
    }

    public function test_susun_hari_tidak_memakai_checkout_sesudah_checkin_berikutnya(): void
    {
        $hari = $this->svc->susunHari([
            $this->tap('2026-10-09 05:27:36', 'CHECK IN', 'A'),
            $this->tap('2026-10-10 17:25:49', 'CHECK IN', 'A'),
            $this->tap('2026-10-11 06:29:43', 'CHECK OUT', 'B'),
        ], CarbonImmutable::parse('2026-10-09'), 3);

        $this->assertSame('P', $hari[0]['kode']);
        $this->assertNull($hari[0]['keluar']);
        $this->assertNull($hari[0]['durasi']);
        $this->assertSame('B', $hari[1]['keluar']['gate']);
    }

    public function test_susun_hari_tap_ganda_memakai_checkin_yang_berpasangan(): void
    {
        $hari = $this->svc->susunHari([
            $this->tap('2026-10-09 05:20:00', 'CHECK IN', 'A'),
            $this->tap('2026-10-09 05:22:00', 'CHECK IN', 'A'),
            $this->tap('2026-10-09 17:22:00', 'CHECK OUT', 'B'),
        ], CarbonImmutable::parse('2026-10-09'), 1);

        $this->assertSame('05:22', $hari[0]['masuk']['jam']);
        $this->assertSame('12j 0m', $hari[0]['durasi']);
        $this->assertCount(3, $hari[0]['tap']);
    }

    public function test_susun_hari_tap_tidak_lolos_tampil_tapi_tidak_dihitung_hadir(): void
    {
        $hari = $this->svc->susunHari(
            [$this->tap('2026-10-09 06:00:00', 'CHECK IN', 'A', false)],
            CarbonImmutable::parse('2026-10-09'),
            1,
        );

        $this->assertSame('o', $hari[0]['kode']);
        $this->assertCount(1, $hari[0]['tap']);
        $this->assertFalse($hari[0]['tap'][0]['lolos']);
    }

    public function test_ambil_memakai_hasil_terakhir_saat_rfid_baru_saja_gagal(): void
    {
        config(['dms_roster.reg_live.kecualikan_master_banned' => false]);
        Cache::put('dms_roster:aturan_live:terakhir:v1', ['basi' => false, 'diambil' => '18 Oct 2026 23:30', 'daftar' => []]);
        Cache::put('dms_roster:aturan_live:gagal:v1', 'Postgres OLAP tidak terjangkau');

        $hasil = $this->svc->ambil();

        $this->assertTrue($hasil['basi']);
        $this->assertSame('18 Oct 2026 23:30', $hasil['diambil']);
        $this->assertSame('Postgres OLAP tidak terjangkau', $hasil['galat']);
        $this->assertSame('Postgres OLAP tidak terjangkau', $this->svc->galatTerakhir());
    }

    public function test_ambil_null_bila_gagal_dan_belum_pernah_ada_hasil(): void
    {
        config(['dms_roster.reg_live.kecualikan_master_banned' => false]);
        Cache::put('dms_roster:aturan_live:gagal:v1', 'timeout');

        $this->assertNull($this->svc->ambil());
        $this->assertSame('timeout', $this->svc->galatTerakhir());
    }

    public function test_ambil_mendahulukan_hasil_segar_di_cache(): void
    {
        config(['dms_roster.reg_live.kecualikan_master_banned' => false]);
        $kunci = 'dms_roster:aturan_live:v10:'.CarbonImmutable::now()->toDateString().':';
        Cache::put($kunci, ['basi' => false, 'diambil' => 'segar']);
        Cache::put('dms_roster:aturan_live:gagal:v1', 'tidak relevan');

        $this->assertSame(['basi' => false, 'diambil' => 'segar'], $this->svc->ambil());
    }

    /**
     * @return array{waktu:string,jenis:string,gate:string,lolos:bool}
     */
    private function tap(string $waktu, string $jenis, string $gate, bool $lolos = true): array
    {
        return ['waktu' => $waktu, 'jenis' => $jenis, 'gate' => $gate, 'lolos' => $lolos];
    }

    /**
     * @return array{masuk:string,keluar:string,gate_in:string,gate_out:string,n_lebih:int,n_shift:int}
     */
    private function shift(string $masuk, string $keluar): array
    {
        return ['masuk' => $masuk, 'keluar' => $keluar, 'gate_in' => 'A', 'gate_out' => 'B', 'n_lebih' => 1, 'n_shift' => 1];
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
