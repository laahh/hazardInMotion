<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Models\DmsRosterMasterUpload;
use App\Services\Dms\Roster\DmsRosterKaryawanAktif;
use App\Services\Dms\Roster\DmsRosterMasterService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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
            ['Tanggal Awal Periode:', null, null, $awal, 'Periode: …', null, null, null, null, 'Kode:', 'D1'],
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
            [1, '10023451', 'Ahmad Fauzi', 'PT Berau Coal', 'Operator HD', 'BMO 1', null, null, null, 'd1', 'N1', 'OFF', ''],
            [2, 'C5BXK', 'Ifa', 'PT BCE', 'Safety Evaluator', 'HO', null, null, null, 'OFF', 'CT', 's', 'I'],
        ]));

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'], $hasil['tanggal']);
        $this->assertSame('GMO', $hasil['departemen_site']);
        $a = $hasil['karyawan'][0];
        $this->assertSame(['10023451', 'Operator HD', null], [$a['sid'], $a['jabatan'], $a['jam']]);
        $this->assertSame(['kode' => 'D1', 'masuk' => '06:00', 'keluar' => '18:00', 'menit' => 720, 'total' => 720], $a['jadwal']['2026-11-01']);
        $this->assertSame(['kode' => 'N1', 'masuk' => '18:00', 'keluar' => '06:00', 'menit' => 720, 'total' => 720], $a['jadwal']['2026-11-02']);
        $this->assertSame(['kode' => 'OFF', 'masuk' => null, 'keluar' => null, 'menit' => null, 'total' => null], $a['jadwal']['2026-11-03']);
        $this->assertArrayNotHasKey('2026-11-04', $a['jadwal']);
        $this->assertSame(['OFF', 'CT', 'S', 'I'], array_column($hasil['karyawan'][1]['jadwal'], 'kode'));
    }

    public function test_kode_of_tidak_lagi_diterima(): void
    {
        $hasil = $this->svc->bacaSheet($this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'OF', 'D1', 'D1', 'D1']]));

        $this->assertStringContainsString('kode "OF" tidak dikenal', $hasil['galat'][0]);
    }

    public function test_kode_d1_sampai_d13_dan_n1_sampai_n7_diterima_kode_lama_ditolak(): void
    {
        $kode = array_merge(array_map(static fn (int $i): string => 'D'.$i, range(1, 13)), array_map(static fn (int $i): string => 'N'.$i, range(1, 7)));
        $baris = [1, 'A1', '', '', '', '', null, null, null, ...array_map('strtolower', $kode)];
        $hasil = $this->svc->bacaSheet($this->sheet([$baris], hari: 20));

        $this->assertSame([], $hasil['galat']);
        $jadwal = array_values($hasil['karyawan'][0]['jadwal']);
        $this->assertSame($kode, array_column($jadwal, 'kode'));
        $this->assertSame(['06:00', '18:00'], [$jadwal[12]['masuk'], $jadwal[12]['keluar']], 'D13 = Day 06:00–18:00');
        $this->assertSame(['18:00', '06:00'], [$jadwal[19]['masuk'], $jadwal[19]['keluar']], 'N7 = Night 18:00–06:00');

        foreach (['DS', 'NS', 'D14', 'N8', 'D0'] as $lama) {
            $tolak = $this->svc->bacaSheet($this->sheet([[1, 'A1', '', '', '', '', null, null, null, $lama]], hari: 1));
            $this->assertStringContainsString('kode "'.$lama.'" tidak dikenal', $tolak['galat'][0] ?? '', $lama);
        }
    }

    public function test_legenda_meringkas_d1_d13_dan_n1_n7(): void
    {
        $this->assertSame(
            [['D1–D13', 'Day 1–13'], ['N1–N7', 'Night 1–7'], ['OFF', 'Off / libur'], ['CT', 'Cuti'], ['S', 'Sakit'], ['I', 'Izin']],
            array_map(static fn (array $l): array => [$l[0], $l[1]], DmsRosterMasterService::legenda()),
        );
    }

    public function test_jam_per_karyawan_menimpa_jam_kode_dan_total_dicek(): void
    {
        // 0.25 = 06:00 dan 0.75 = 18:00 dalam pecahan hari Excel.
        $hasil = $this->svc->bacaSheet($this->sheet([
            [1, 'A1', 'Budi', 'PT X', 'Mekanik', 'BMO 1', 0.25, 0.75, 12, 'D1', 'N1', 'OFF', 'D1'],
            [2, 'B2', 'Siti', 'PT X', 'SO', 'HO', '22:00', '06:30', '8.5', 'N1', '', '', ''],
        ]));

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(['masuk' => '06:00', 'keluar' => '18:00', 'menit' => 720, 'total' => 720], $hasil['karyawan'][0]['jam']);
        $this->assertSame(['kode' => 'N1', 'masuk' => '06:00', 'keluar' => '18:00', 'menit' => 720, 'total' => 720], $hasil['karyawan'][0]['jadwal']['2026-11-02']);
        $this->assertNull($hasil['karyawan'][0]['jadwal']['2026-11-03']['masuk']);
        $this->assertNull($hasil['karyawan'][0]['jadwal']['2026-11-03']['total']);
        $this->assertSame(510, $hasil['karyawan'][1]['jadwal']['2026-11-01']['menit']);
        $this->assertSame(510, $hasil['karyawan'][1]['jadwal']['2026-11-01']['total']);
    }

    public function test_kesalahan_jam_dilaporkan(): void
    {
        $hasil = $this->svc->bacaSheet($this->sheet([
            [1, 'A1', '', '', '', '', '07:00', null, null, 'D1', 'D1', 'D1', 'D1'],
            [2, 'B2', '', '', '', '', '07:00', '19:00', 13, 'D1', 'D1', 'D1', 'D1'],
            [3, 'C3', '', '', '', '', null, null, 8, 'D1', 'D1', 'D1', 'D1'],
        ]));

        $this->assertSame([], $hasil['karyawan']);
        $this->assertCount(3, $hasil['galat']);
        $this->assertStringContainsString('harus diisi berdua', $hasil['galat'][0]);
        $this->assertStringContainsString('tidak lebih dari 07:00–19:00 = 12 jam', $hasil['galat'][1]);
        $this->assertStringContainsString('tanpa Jam Kerja Awal', $hasil['galat'][2]);
    }

    public function test_tanpa_kolom_jam_tetap_diterima(): void
    {
        $hasil = $this->svc->bacaSheet($this->sheet([[1, 'A1', 'X', '', '', '', 'D1', 'D1', 'N1', 'N1']], denganJam: false));

        $this->assertSame([], $hasil['galat']);
        $this->assertCount(4, $hasil['karyawan'][0]['jadwal']);
    }

    public function test_satu_kesalahan_menolak_seluruh_file(): void
    {
        $hasil = $this->svc->bacaSheet($this->sheet([
            [1, 'A1', '', '', '', '', null, null, null, 'D1', 'X', 'D1', 'D1'],
            [2, 'A1', '', '', '', '', null, null, null, 'D1', 'D1', 'D1', 'D1'],
            [3, 'BAD SID', '', '', '', '', null, null, null, 'D1', 'D1', 'D1', 'D1'],
        ]));

        $this->assertSame([], $hasil['karyawan']);
        $this->assertCount(3, $hasil['galat']);
        $this->assertStringContainsString('kode "X"', $hasil['galat'][0]);
        $this->assertStringContainsString('sudah ada di baris 7', $hasil['galat'][1]);
        $this->assertStringContainsString('SID "BAD SID"', $hasil['galat'][2]);
    }

    public function test_tanggal_awal_kosong_dan_tanggal_tidak_berurutan(): void
    {
        $kosong = $this->svc->bacaSheet($this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'D1']], awal: ''));
        $this->assertStringContainsString('Tanggal Awal Periode', $kosong['galat'][0]);

        $loncat = $this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'D1', 'D1', 'D1', 'D1']]);
        $loncat[3][11] = '05';
        $hasil = $this->svc->bacaSheet($loncat);
        $this->assertStringContainsString('tanggal 05/11/2026 tidak berurutan (seharusnya 03/11/2026)', $hasil['galat'][0]);
    }

    public function test_judul_kolom_berisi_tanggal_excel_asli_tanpa_tanggal_awal(): void
    {
        // Baris tanggal dari rumus (=D2+1 diformat "dd") terbaca sebagai nomor seri Excel: 46327 = 01/11/2026.
        $rows = $this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'D1', 'N1', 'OFF', 'CT']], awal: '');
        foreach ([0, 1, 2, 3] as $i) {
            $rows[3][9 + $i] = 46327 + $i;
        }

        $hasil = $this->svc->bacaSheet($rows);

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'], $hasil['tanggal']);
        $this->assertSame(['D1', 'N1', 'OFF', 'CT'], array_column($hasil['karyawan'][0]['jadwal'], 'kode'));
    }

    public function test_judul_kolom_teks_tanggal_lintas_bulan(): void
    {
        $rows = $this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'D1', 'D1', 'N1']], awal: '30/11/2026', hari: 3);
        [$rows[3][9], $rows[3][10], $rows[3][11]] = ['30/11/2026', '2026-12-01', '02/12/2026'];

        $hasil = $this->svc->bacaSheet($rows);

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(['2026-11-30', '2026-12-01', '2026-12-02'], $hasil['tanggal']);
    }

    public function test_angka_tanggal_lintas_bulan_dan_tanggal_awal_tidak_cocok(): void
    {
        // 30, 01, 02 setelah awal 30/11 → 30 Nov, 1 Des, 2 Des.
        $rows = $this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'D1', 'D1', 'N1']], awal: '30/11/2026', hari: 3);
        [$rows[3][9], $rows[3][10], $rows[3][11]] = ['30', '01', '02'];
        $this->assertSame(['2026-11-30', '2026-12-01', '2026-12-02'], $this->svc->bacaSheet($rows)['tanggal']);

        $salah = $this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'D1']], awal: '05/11/2026', hari: 1);
        $salah[3][9] = 46327;
        $this->assertStringContainsString('tidak berurutan (seharusnya 05/11/2026)', $this->svc->bacaSheet($salah)['galat'][0]);
    }

    public function test_departemen_kosong_tidak_mengambil_label_legenda(): void
    {
        $hasil = $this->svc->bacaSheet($this->sheet([[1, 'A1', '', '', '', '', null, null, null, 'D1']], hari: 1, departemen: null));

        $this->assertSame([], $hasil['galat']);
        $this->assertNull($hasil['departemen_site']);
    }

    public function test_template_setahun_januari_sampai_desember_bisa_dibaca_ulang(): void
    {
        $rows = $this->svc->template(CarbonImmutable::create(2026, 1, 1), CarbonImmutable::create(2026, 12, 1))
            ->getActiveSheet()->toArray(null, true, false, false);

        $hasil = $this->svc->bacaSheet($rows);

        $this->assertSame([], $hasil['galat']);
        $this->assertCount(365, $hasil['tanggal']);
        $this->assertSame(['2026-01-01', '2026-12-31'], [$hasil['tanggal'][0], $hasil['tanggal'][364]]);
        $this->assertCount(365, $hasil['karyawan'][0]['jadwal']);
    }

    public function test_rentang_template_terbalik_atau_lebih_dari_12_bulan_ditolak(): void
    {
        foreach ([[CarbonImmutable::create(2026, 5, 1), CarbonImmutable::create(2026, 4, 1)],
                  [CarbonImmutable::create(2026, 1, 1), CarbonImmutable::create(2027, 1, 1)]] as [$dari, $sampai]) {
            try {
                $this->svc->template($dari, $sampai);
                $this->fail('Rentang '.$dari->format('Y-m').'–'.$sampai->format('Y-m').' seharusnya ditolak.');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_template_perusahaan_terisi_karyawan_dengan_jadwal_kosong(): void
    {
        $karyawan = DmsRosterKaryawanAktif::rapikan([
            (object) ['sid' => ' c5bxk ', 'nama' => 'IFA APRILLIANTO', 'perusahaan' => 'PT Berau Coal Energy', 'jabatan' => 'SAFETY EVALUATOR', 'site' => 'HO'],
            (object) ['sid' => 'C5BXK', 'nama' => 'GANDA', 'perusahaan' => 'PT Berau Coal Energy', 'jabatan' => 'X', 'site' => 'HO'],
            (object) ['sid' => '', 'nama' => 'TANPA SID', 'perusahaan' => 'PT Berau Coal Energy', 'jabatan' => 'X', 'site' => 'HO'],
            (object) ['sid' => '6H2DF', 'nama' => 'AGUS CAHYONO', 'perusahaan' => 'PT Berau Coal Energy', 'jabatan' => 'WHEEL TYPE GL', 'site' => 'BMO 2'],
        ]);
        $this->assertSame(['C5BXK', '6H2DF'], array_column($karyawan, 'sid'));

        $rows = $this->svc->template(CarbonImmutable::create(2026, 11, 1), CarbonImmutable::create(2026, 12, 1), $karyawan)
            ->getActiveSheet()->toArray(null, true, false, false);
        $hasil = $this->svc->bacaSheet($rows);

        $this->assertSame([], $hasil['galat']);
        $this->assertCount(61, $hasil['tanggal']);
        $this->assertSame(['C5BXK', '6H2DF'], array_column($hasil['karyawan'], 'sid'));
        $this->assertSame(['SAFETY EVALUATOR', 'BMO 2'], [$hasil['karyawan'][0]['jabatan'], $hasil['karyawan'][1]['site']]);
        $this->assertSame([], $hasil['karyawan'][0]['jadwal'], 'Template perusahaan tidak boleh berisi jadwal contoh.');
    }

    public function test_template_memuat_kolom_total_jam_kerja_otomatis(): void
    {
        $sh = $this->svc->template(CarbonImmutable::create(2026, 11, 1))->getActiveSheet();

        $this->assertSame(
            ['Site', 'Jam Kerja Awal', 'Jam Kerja Akhir', 'Total Jam Kerja'],
            [$sh->getCell('F4')->getValue(), $sh->getCell('G4')->getValue(), $sh->getCell('H4')->getValue(), $sh->getCell('I4')->getValue()],
        );
        // Rumus Akhir − Awal: 05:30–17:30 = 12, baris tanpa jam kosong.
        $this->assertSame('=IF(OR(G8="",H8=""),"",ROUND(MOD(H8-G8,1)*24,2))', $sh->getCell('I8')->getValue());
        $this->assertEquals(12, $sh->getCell('I8')->getCalculatedValue());
        $this->assertSame('', $sh->getCell('I7')->getCalculatedValue());
        $this->assertSame('', $sh->getCell('I20')->getCalculatedValue());
        // Lewat tengah malam: 18:00–06:00 = 12 jam.
        $sh->setCellValue('G21', 0.75);
        $sh->setCellValue('H21', 0.25);
        $this->assertEquals(12, $sh->getCell('I21')->getCalculatedValue());
        // Kolom tanggal pertama langsung sesudah Total Jam Kerja (kolom J).
        $this->assertSame('2026-11-01', \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $sh->getCell('J4')->getValue())->format('Y-m-d'));
    }

    public function test_total_jam_kerja_boleh_lebih_kecil_karena_istirahat(): void
    {
        $hasil = $this->svc->bacaSheet($this->sheet([
            [1, 'A1', '', '', '', '', '05:30', '17:30', '11', 'D1', 'D1', 'OFF', 'D1'],
            [2, 'B2', '', '', '', '', '05:30', '17:30', '10,5', 'D1', '', '', ''],
            [3, 'C3', '', '', '', '', '05:30', '17:30', '#VALUE!', 'D1', '', '', ''],
        ]));

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(['kode' => 'D1', 'masuk' => '05:30', 'keluar' => '17:30', 'menit' => 720, 'total' => 660], $hasil['karyawan'][0]['jadwal']['2026-11-01']);
        $this->assertNull($hasil['karyawan'][0]['jadwal']['2026-11-03']['total']);
        $this->assertSame(630, $hasil['karyawan'][1]['jadwal']['2026-11-01']['total']);
        // Rumus template yang gagal dihitung = ikut rentang Awal–Akhir.
        $this->assertSame(720, $hasil['karyawan'][2]['jadwal']['2026-11-01']['total']);
    }

    public function test_total_jam_kerja_dari_jadwal_untuk_tampilan(): void
    {
        $this->assertSame(11.0, DmsRosterMasterService::totalKaryawanDariJadwal([
            ['kode' => 'D1', 'total' => 660], ['kode' => 'OFF', 'total' => null], ['kode' => 'D2', 'total' => 660],
        ]));
        $this->assertNull(DmsRosterMasterService::totalKaryawanDariJadwal([['kode' => 'D1', 'total' => 660], ['kode' => 'D2', 'total' => 720]]));
    }

    public function test_judul_jam_lama_check_in_out_tetap_dibaca(): void
    {
        // Helper sheet() memakai judul lama "Jam Check-in / Check-out / Total Jam Kerja".
        $hasil = $this->svc->bacaSheet($this->sheet([[1, 'A1', '', '', '', '', '06:00', '18:00', 12, 'D1', 'D1', 'D1', 'D1']]));

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(['masuk' => '06:00', 'keluar' => '18:00', 'menit' => 720, 'total' => 720], $hasil['karyawan'][0]['jam']);
    }

    public function test_kelompokkan_perusahaan_dan_site(): void
    {
        $pt = DmsRosterKaryawanAktif::kelompokkan([
            ['perusahaan' => 'PT B', 'site' => 'LMO', 'jumlah' => 2],
            ['perusahaan' => 'PT A', 'site' => 'BMO 2', 'jumlah' => 5],
            ['perusahaan' => 'PT A', 'site' => '', 'jumlah' => 1],
            ['perusahaan' => 'PT A', 'site' => 'BMO 1', 'jumlah' => 3],
        ]);

        $this->assertSame(['PT A', 'PT B'], array_column($pt, 'nama'));
        $this->assertSame(9, $pt[0]['jumlah']);
        $this->assertSame(['-', 'BMO 1', 'BMO 2'], array_column($pt[0]['site'], 'nama'));
    }

    public function test_tanda_tangan_digambar_dan_upload_disimpan_sebagai_gambar(): void
    {
        Storage::fake('local');
        $upload = new DmsRosterMasterUpload();
        $upload->id = 77;

        // PNG 1×1, bentuk yang sama dengan signature_pad toDataURL('image/png').
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $hasil = $this->svc->simpanTtd($upload, 'data:image/png;base64,'.$png, null);
        $this->assertSame(['path' => 'roster-master/77/ttd-approval.png', 'sumber' => 'GAMBAR'], $hasil);
        Storage::disk('local')->assertExists('roster-master/77/ttd-approval.png');

        $jpg = UploadedFile::fake()->image('ttd.jpg', 300, 120);
        $this->assertSame(['path' => 'roster-master/77/ttd-approval.jpg', 'sumber' => 'UPLOAD'], $this->svc->simpanTtd($upload, null, $jpg));
    }

    public function test_tanda_tangan_bukan_gambar_atau_kosong_ditolak(): void
    {
        Storage::fake('local');
        $upload = new DmsRosterMasterUpload();
        $upload->id = 78;

        foreach ([
            [null, null, 'belum digambar'],
            ['data:image/svg+xml;base64,PHN2Zz4=', null, 'belum digambar'],
            [null, UploadedFile::fake()->createWithContent('ttd.png', 'bukan gambar sungguhan'), 'PNG atau JPG'],
        ] as [$data, $file, $pesan]) {
            try {
                $this->svc->simpanTtd($upload, $data, $file);
                $this->fail('Seharusnya ditolak: '.$pesan);
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($pesan, $e->getMessage());
            }
        }

        $pending = new DmsRosterMasterUpload(['status' => DmsRosterMasterUpload::STATUS_PENDING]);
        $this->expectExceptionMessage('wajib disertai tanda tangan');
        $this->svc->review($pending, true, null, 'uji', null);
    }

    public function test_kolom_jam_kerja_awal_akhir_di_halaman_diturunkan_dari_jadwal(): void
    {
        $h = static fn (string $kode, ?string $m, ?string $k): array => ['kode' => $kode, 'masuk' => $m, 'keluar' => $k, 'menit' => null];

        // Semua hari kerja pakai jam bawaan kode → kolom kosong (seperti template).
        $this->assertSame([null, null], DmsRosterMasterService::jamKaryawanDariJadwal([
            $h('D1', '06:00', '18:00'), $h('N1', '18:00', '06:00'), $h('OFF', null, null),
        ]));
        // Jam khusus karyawan (sama di semua hari kerja, beda dari bawaan) → tampil.
        $this->assertSame(['05:30', '17:30'], DmsRosterMasterService::jamKaryawanDariJadwal([
            $h('D1', '05:30', '17:30'), $h('N1', '05:30', '17:30'), $h('CT', null, null),
        ]));
        // Data lama berkode DS/NS dengan jam bawaannya dulu → bukan jam khusus.
        $this->assertSame([null, null], DmsRosterMasterService::jamKaryawanDariJadwal([
            $h('DS', '07:00', '19:00'), $h('DS', '07:00', '19:00'),
        ]));
        // Jam berbeda-beda antar hari → tidak bisa diringkas jadi satu kolom.
        $this->assertSame([null, null], DmsRosterMasterService::jamKaryawanDariJadwal([
            $h('D1', '05:30', '17:30'), $h('D2', '06:00', '18:00'),
        ]));
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
        // Total Jam Kerja dari rumus template ikut terbaca.
        $this->assertSame(['masuk' => '05:30', 'keluar' => '17:30', 'menit' => 720, 'total' => 720], $hasil['karyawan'][1]['jam']);
        $this->assertSame(540, $hasil['karyawan'][2]['jam']['menit']);
        $this->assertSame(540, $hasil['karyawan'][2]['jam']['total']);
    }
}
