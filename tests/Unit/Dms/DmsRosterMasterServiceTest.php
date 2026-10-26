<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterMasterService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Pembaca file Master Roster format "ROSTER KERJA KARYAWAN" (kepala periode,
 * judul kolom 3 baris, satu kolom per hari, kolom jam per karyawan opsional).
 * Bagian simpan/approval menyentuh DB dan tidak diuji di sini.
 */
class DmsRosterMasterServiceTest extends TestCase
{
    private DmsRosterMasterService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DmsRosterMasterService();
    }

    /**
     * Sheet tiruan dengan tata letak template: judul kolom baris 4, nama hari
     * baris 5, bulan baris 6, data mulai baris 7.
     *
     * @param  list<list<mixed>>  $karyawan  [No, SID, Nama, PT, Jabatan, Site, (Jam in, Jam out, Total,) kode…]
     * @return list<list<mixed>>
     */
    private function sheet(array $karyawan, string $awal = '01/11/2026', int $hari = 4, bool $denganJam = true, ?string $departemen = 'GMO'): array
    {
        $identitas = ['No', 'SID', 'Nama Karyawan', 'Perusahaan', 'Jabatan', 'Site'];
        if ($denganJam) {
            array_push($identitas, 'Jam Check-in', 'Jam Check-out', 'Total Jam Kerja');
        }
        $tgl = array_map(static fn (int $i): string => sprintf('%02d', $i + 1), range(0, $hari - 1));
        $kosong = array_fill(0, count($identitas), null);

        return [
            ['ROSTER KERJA KARYAWAN'],
            ['Tanggal Awal Periode:', null, null, $awal, 'Periode: …', null, null, null, null, 'Kode:', 'DS'],
            ['Departemen / Site:', null, null, $departemen, null, null, null, null, null, 'Jam:', '07:00–19:00'],
            [...$identitas, ...$tgl],
            [...$kosong, ...array_fill(0, $hari, 'Sen')],
            [...$kosong, ...array_fill(0, $hari, 'Nov')],
            ...$karyawan,
        ];
    }

    public function test_membaca_format_roster_kerja_karyawan_dan_jam_bawaan_kode(): void
    {
        $hasil = $this->svc->bacaSheet($this->sheet([
            [1, '10023451', 'Ahmad Fauzi', 'PT Berau Coal', 'Operator HD', 'BMO 1', null, null, null, 'ds', 'NS', 'OFF', ''],
            [2, 'C5BXK', 'Ifa', 'PT BCE', 'Safety Evaluator', 'HO', null, null, null, 'OF', 'CT', 's', 'I'],
        ]));

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'], $hasil['tanggal']);
        $this->assertSame('GMO', $hasil['departemen_site']);
        $a = $hasil['karyawan'][0];
        $this->assertSame(['10023451', 'Operator HD', null], [$a['sid'], $a['jabatan'], $a['jam']]);
        $this->assertSame(['kode' => 'DS', 'masuk' => '07:00', 'keluar' => '19:00', 'menit' => 720], $a['jadwal']['2026-11-01']);
        $this->assertSame(['kode' => 'NS', 'masuk' => '19:00', 'keluar' => '07:00', 'menit' => 720], $a['jadwal']['2026-11-02']);
        $this->assertSame(['kode' => 'OFF', 'masuk' => null, 'keluar' => null, 'menit' => null], $a['jadwal']['2026-11-03']);
        $this->assertArrayNotHasKey('2026-11-04', $a['jadwal']);
        $this->assertSame(['OF', 'CT', 'S', 'I'], array_column($hasil['karyawan'][1]['jadwal'], 'kode'));
    }

    public function test_jam_per_karyawan_menimpa_jam_kode_dan_total_dicek(): void
    {
        // 0.25 = 06:00 dan 0.75 = 18:00 dalam pecahan hari Excel.
        $hasil = $this->svc->bacaSheet($this->sheet([
            [1, 'A1', 'Budi', 'PT X', 'Mekanik', 'BMO 1', 0.25, 0.75, 12, 'DS', 'NS', 'OFF', 'DS'],
            [2, 'B2', 'Siti', 'PT X', 'SO', 'HO', '22:00', '06:30', '8.5', 'NS', '', '', ''],
        ]));

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(['masuk' => '06:00', 'keluar' => '18:00', 'menit' => 720], $hasil['karyawan'][0]['jam']);
        $this->assertSame(['kode' => 'NS', 'masuk' => '06:00', 'keluar' => '18:00', 'menit' => 720], $hasil['karyawan'][0]['jadwal']['2026-11-02']);
        $this->assertNull($hasil['karyawan'][0]['jadwal']['2026-11-03']['masuk']);
        $this->assertSame(510, $hasil['karyawan'][1]['jadwal']['2026-11-01']['menit']);
    }

    public function test_kesalahan_jam_dilaporkan(): void
    {
        $hasil = $this->svc->bacaSheet($this->sheet([
            [1, 'A1', '', '', '', '', '07:00', null, null, 'DS', 'DS', 'DS', 'DS'],
            [2, 'B2', '', '', '', '', '07:00', '19:00', 10, 'DS', 'DS', 'DS', 'DS'],
            [3, 'C3', '', '', '', '', null, null, 8, 'DS', 'DS', 'DS', 'DS'],
        ]));

        $this->assertSame([], $hasil['karyawan']);
        $this->assertCount(3, $hasil['galat']);
        $this->assertStringContainsString('harus diisi berdua', $hasil['galat'][0]);
        $this->assertStringContainsString('tidak sama dengan 07:00–19:00 = 12 jam', $hasil['galat'][1]);
        $this->assertStringContainsString('tanpa Jam Check-in', $hasil['galat'][2]);
    }

    public function test_tanpa_kolom_jam_tetap_diterima(): void
    {
        $hasil = $this->svc->bacaSheet($this->sheet([[1, 'A1', 'X', '', '', '', 'DS', 'DS', 'NS', 'NS']], denganJam: false));

        $this->assertSame([], $hasil['galat']);
        $this->assertCount(4, $hasil['karyawan'][0]['jadwal']);
    }

    public function test_satu_kesalahan_menolak_seluruh_file(): void
    {
        $hasil = $this->svc->bacaSheet($this->sheet([
            [1, 'A1', '', '', '', '', null, null, null, 'DS', 'X', 'DS', 'DS'],
            [2, 'A1', '', '', '', '', null, null, null, 'DS', 'DS', 'DS', 'DS'],
            [3, 'BAD SID', '', '', '', '', null, null, null, 'DS', 'DS', 'DS', 'DS'],
        ]));

        $this->assertSame([], $hasil['karyawan']);
        $this->assertCount(3, $hasil['galat']);
        $this->assertStringContainsString('kode "X"', $hasil['galat'][0]);
        $this->assertStringContainsString('sudah ada di baris 7', $hasil['galat'][1]);
        $this->assertStringContainsString('SID "BAD SID"', $hasil['galat'][2]);
    }

    public function test_tanggal_awal_kosong_dan_tanggal_tidak_berurutan(): void
    {
        $kosong = $this->svc->bacaSheet($this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'DS']], awal: ''));
        $this->assertStringContainsString('Tanggal Awal Periode', $kosong['galat'][0]);

        $loncat = $this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'DS', 'DS', 'DS', 'DS']]);
        $loncat[3][11] = '05';
        $hasil = $this->svc->bacaSheet($loncat);
        $this->assertStringContainsString('tanggal 05/11/2026 tidak berurutan (seharusnya 03/11/2026)', $hasil['galat'][0]);
    }

    public function test_judul_kolom_berisi_tanggal_excel_asli_tanpa_tanggal_awal(): void
    {
        // Baris tanggal dari rumus (=D2+1 diformat "dd") terbaca sebagai nomor seri Excel: 46327 = 01/11/2026.
        $rows = $this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'DS', 'NS', 'OFF', 'CT']], awal: '');
        foreach ([0, 1, 2, 3] as $i) {
            $rows[3][9 + $i] = 46327 + $i;
        }

        $hasil = $this->svc->bacaSheet($rows);

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'], $hasil['tanggal']);
        $this->assertSame(['DS', 'NS', 'OFF', 'CT'], array_column($hasil['karyawan'][0]['jadwal'], 'kode'));
    }

    public function test_judul_kolom_teks_tanggal_lintas_bulan(): void
    {
        $rows = $this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'DS', 'DS', 'NS']], awal: '30/11/2026', hari: 3);
        [$rows[3][9], $rows[3][10], $rows[3][11]] = ['30/11/2026', '2026-12-01', '02/12/2026'];

        $hasil = $this->svc->bacaSheet($rows);

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(['2026-11-30', '2026-12-01', '2026-12-02'], $hasil['tanggal']);
    }

    public function test_angka_tanggal_lintas_bulan_dan_tanggal_awal_tidak_cocok(): void
    {
        // 30, 01, 02 setelah awal 30/11 → 30 Nov, 1 Des, 2 Des.
        $rows = $this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'DS', 'DS', 'NS']], awal: '30/11/2026', hari: 3);
        [$rows[3][9], $rows[3][10], $rows[3][11]] = ['30', '01', '02'];
        $this->assertSame(['2026-11-30', '2026-12-01', '2026-12-02'], $this->svc->bacaSheet($rows)['tanggal']);

        $salah = $this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'DS']], awal: '05/11/2026', hari: 1);
        $salah[3][9] = 46327;
        $this->assertStringContainsString('tidak berurutan (seharusnya 05/11/2026)', $this->svc->bacaSheet($salah)['galat'][0]);
    }

    public function test_departemen_kosong_tidak_mengambil_label_legenda(): void
    {
        $hasil = $this->svc->bacaSheet($this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'DS']], hari: 1, departemen: null));

        $this->assertSame([], $hasil['galat']);
        $this->assertNull($hasil['departemen_site']);
    }

    public function test_template_bisa_dibaca_ulang_tanpa_galat(): void
    {
        $rows = $this->svc->template(CarbonImmutable::create(2026, 11, 1))->getActiveSheet()->toArray(null, true, false, false);

        $hasil = $this->svc->bacaSheet($rows);

        $this->assertSame([], $hasil['galat']);
        $this->assertCount(30, $hasil['tanggal']);
        $this->assertSame('2026-11-01', $hasil['tanggal'][0]);
        $this->assertSame(['10023451', '10023452', '10023453'], array_column($hasil['karyawan'], 'sid'));
        $this->assertNull($hasil['karyawan'][0]['jam']);
        $this->assertSame(['masuk' => '06:00', 'keluar' => '18:00', 'menit' => 720], $hasil['karyawan'][1]['jam']);
        $this->assertSame(540, $hasil['karyawan'][2]['jam']['menit']);
    }
}
