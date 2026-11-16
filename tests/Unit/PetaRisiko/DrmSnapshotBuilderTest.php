<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmMappingEkspor;
use App\Services\PetaRisiko\Drm\DrmMaster;
use App\Services\PetaRisiko\Drm\DrmSnapshotBuilder;
use App\Services\PetaRisiko\Drm\DrmSql;
use App\Services\SportEvaluation\RfidClickHouseClient;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DrmSnapshotBuilderTest extends TestCase
{
    private const SAMPAI = '2026-11-14';

    public function test_kpi_dan_matriks_cakupan_dari_minggu_terakhir(): void
    {
        $snap = $this->susun();
        $kpi = $snap['cakupan']['SITE|LMO']['kpi'];

        $this->assertSame(3, $kpi['total']);
        $this->assertSame(1, $kpi['K']);
        $this->assertSame(1, $kpi['G']);
        $this->assertSame(1, $kpi['T']);
        // Terkontrol Significant → residual Medium = turun kategori.
        $this->assertSame(1, $kpi['turun']);
        $this->assertSame(1, $kpi['final']['Medium']);
        // Minggu lama hanya masuk tren, tidak ke KPI.
        $this->assertSame(5, $snap['cakupan']['SITE|LMO']['tren']['2026-11-07']['total']);
        // Matriks awal: Hampir Pasti (baris 0) × Bencana (kolom 4).
        $this->assertSame(2, $snap['cakupan']['SITE|LMO']['mAwal'][0][4]);
    }

    public function test_risiko_laten_diambil_dari_kolom_laten_matriks_minggu_terakhir(): void
    {
        $snap = $this->susun();

        // Hanya baris laten=1 di minggu terakhir yang dihitung (minggu lama & baris G diabaikan).
        $this->assertSame(1, $snap['cakupan']['SITE|LMO']['kpi']['laten']);
        $this->assertSame(2, $snap['cakupan']['PJA|10']['kpi']['laten']);
        $this->assertSame(0, $snap['cakupan']['PROSES|K16|ALL']['kpi']['laten']);
        // Laten tidak boleh melebihi jumlah tidak teridentifikasi + tidak terpetakan.
        foreach ($snap['cakupan'] as $c) {
            $this->assertLessThanOrEqual($c['kpi']['T'] + $c['kpi']['X'], $c['kpi']['laten']);
        }
    }

    public function test_baris_excel_status_gabungan_mapping_dan_insiden(): void
    {
        $baris = array_column($this->susun()['ekspor']['baris'], null, 'hid');

        $this->assertCount(5, $baris);
        // hid 1: tidak teridentifikasi di semua DL + insiden → laten.
        $this->assertSame('T', $baris['1']['status']);
        $this->assertTrue($baris['1']['laten']);
        $this->assertSame(1, $baris['1']['inc_n']);
        // hid 2: gap di salah satu DL menang atas terkontrol di DL lain (status terburuk).
        $this->assertSame('G', $baris['2']['status']);
        $this->assertSame(['dl_n' => 3, 'dl_g' => 1, 'dl_k' => 2], ['dl_n' => $baris['2']['dl_n'], 'dl_g' => $baris['2']['dl_g'], 'dl_k' => $baris['2']['dl_k']]);
        $this->assertSame('Hazard › Jalan licin | Hazard › Jalan berlubang', $baris['2']['label_a']);
        // hid 3: tidak punya DL baseline.
        $this->assertSame('-', $baris['3']['status']);
        $this->assertSame('Dumping & Penimbunan', $baris['3']['proses']);
        // hid 4: tidak punya pasangan SAP tapi punya DL → tetap baseline HIRA (X = tanpa laporan), final = risiko awal.
        $this->assertSame('X', $baris['4']['status']);
        $this->assertSame(1, $baris['4']['dl_x']);
        $this->assertSame('High', $baris['4']['final']);
        $this->assertFalse($baris['4']['laten']);
        // hid 5: hanya punya pasangan butir checklist OAK (Master D) → tetap terpetakan, bukan X.
        $this->assertSame('-', $baris['5']['status']);
        $this->assertSame(2, $baris['5']['n_d']);
        $this->assertSame('APD › Helm | APD › Rompi', $baris['5']['butir_d']);
    }

    public function test_sql_baseline_dari_master_dengan_gerbang_grup_dan_gap_spec(): void
    {
        $sql = $this->sql();
        $status = $sql->pasanganBaseline();
        $mingguan = $sql->matriksCakupan();

        // Literal master (angka + CRC32), bukan teks; baseline = detail lokasi × baris grupnya (M05).
        $this->assertStringContainsString("JSONExtract('[[11,[7],".DrmMaster::crc('Jalan licin').',3,13,4,0]', $status);
        $this->assertStringContainsString('JOIN mgx g ON g.gid = d.gid', $status);
        // Gerbang: bukti label hanya dihitung bila (detail lokasi, baris) ada di baseline.
        $this->assertStringContainsString('WHERE (sh.dl, m.hid) IN (SELECT dl, hid FROM basek)', $status);
        // PJA per detail lokasi dari aaj_lokasi_dan_pja (level lokasi), id = m_pja.
        $this->assertStringContainsString('FROM obds.aaj_lokasi_dan_pja', $status);
        $this->assertStringNotContainsString('m_pja_locations', $mingguan);
        // Gap = belum closed | berulang | OAK stop; checklist tidak sesuai bukan gap.
        $this->assertStringNotContainsString('gd.ts_d, 0) > 0', $status);
        $this->assertStringContainsString('SELECT dl, hid, minggu, toUInt8(3) FROM ed', $mingguan);
        // OAK dicocokkan sebagai "aktivitas / sub" (format M12).
        $this->assertStringContainsString("concat(ifNull(`aktivitas pekerjaan OAK`, ''), ' / ', ifNull(`sub aktivitas pekerjaan OAK`, ''))", $status);
    }

    public function test_normalisasi_master_sama_dengan_sisi_clickhouse(): void
    {
        $this->assertSame('workshop tyre mtn 056', DrmMaster::norm("  Workshop\tTyre   MTN 056 "));
        $this->assertSame(crc32('smo|workshop|workshop tyre mtn 056'), DrmMaster::crcDl('SMO', 'Workshop', 'Workshop  Tyre MTN 056'));
        $m = $this->master();
        $this->assertSame([11 => 'K13', 12 => 'K16'], $m->kelompokBaris(config('peta_risiko_drm.proses_bisnis')));
        // Tema dicocokkan ke "bahaya sub " (spasi penutup untuk pola seperti "ban ").
        $this->assertSame([11], $m->temaBaris(config('peta_risiko_drm.tema_checklist'))[5] ?? []);
    }

    public function test_file_excel_mapping_valid_dan_bisa_dibaca(): void
    {
        $snap = $this->susun();
        $file = tempnam(sys_get_temp_dir(), 'drm').'.xlsx';
        (new DrmMappingEkspor())->tulis($snap['ekspor'], $this->master(), CarbonImmutable::parse(self::SAMPAI), config('peta_risiko_drm'), $file);

        $wb = IOFactory::load($file);
        $this->assertSame(['README_Peta_Join', 'M01_Baris_HIRA', 'M02_Kelompok_Aktivitas', 'M03_Aktivitas_Kelompok', 'M04_Grup_Lokasi',
            'M05_Bridge_Grup_Baris', 'M06_DetailLokasi', 'M07_Bridge_DL_PJA', 'M08_PJA', 'M09_Label_SAP_Baris', 'M11_OBS_Aktivitas_Baris',
            'M12_OAK_Aktivitas_Baris', 'M15_Tema_Checklist', 'M16_Bridge_Tema_Baris'], $wb->getSheetNames());
        $m01 = $wb->getSheetByName('M01_Baris_HIRA')->toArray(null, true, false);
        $this->assertSame('id_baris (ri)', $m01[0][0]);
        $this->assertSame([11, 'Jalan Tambang', 'Jalan licin', 'Jalan Tambang & Road Maintenance', 1], [$m01[1][0], $m01[1][2], $m01[1][4], $m01[1][6], $m01[1][10]]);
        $m06 = $wb->getSheetByName('M06_DetailLokasi')->toArray(null, true, false);
        // DL master cocok ke DL OBDS 101 lewat nama; PJA dari snapshot.
        $this->assertSame(['101', 1, 'PJA Pit BC'], [(string) $m06[1][6], $m06[1][7], $m06[1][8]]);
        $this->assertSame([[1, '10']], array_map(fn ($r) => [$r[0], (string) $r[1]], array_slice($wb->getSheetByName('M07_Bridge_DL_PJA')->toArray(null, true, false), 1)));
        $m16 = $wb->getSheetByName('M16_Bridge_Tema_Baris')->toArray(null, true, false);
        $this->assertContains([5, 'Jalan/berm', 11, 'Jalan licin', 'Jalan Tambang & Road Maintenance', 'N'], $m16);
        @unlink($file);
    }

    public function test_berkas_pja_hanya_berisi_detail_lokasi_miliknya_dan_personil_mitra(): void
    {
        $snap = $this->susun();
        $p = $snap['pja']['10'];

        $this->assertSame(['101', '102'], array_column($p['lokasi'], 'dl'));
        $this->assertCount(2, $p['akt']);
        $this->assertSame(['101', 'loading ob'], array_slice($p['akt'][0], 0, 2));
        $this->assertSame(1, $p['temuanTotal']);
        $this->assertEqualsCanonicalizing(['S1', 'S2'], array_column($p['personil'], 'sid'));
        // Personil dicocokkan lewat ID PJA: S1 milik PJA BC, S2 milik PJA Mitra anaknya.
        $this->assertSame(['S1' => true, 'S2' => false], array_column($p['personil'], 'bc', 'sid'));
        $this->assertCount(1, $p['bearc']);
        // Bahaya hanya dari pasangan baseline di detail lokasi PJA (hid 5 berada di DL 201 milik PJA lain).
        $this->assertEqualsCanonicalizing(['1', '2', '3'], array_map(fn ($b) => (string) $b[0], $p['bahaya']));
        $this->assertSame(['101' => '1:T,2:G', '102' => '3:K'], (array) $p['pasangan']);
        $this->assertSame('PJA Pit BC', $p['info']['nama']);
        $this->assertSame([['id' => '11', 'nama' => 'PJA Pit Mitra']], $p['info']['mitra']);
        // PJA tanpa detail lokasi (Mitra) tidak dibuatkan berkas sendiri.
        $this->assertArrayNotHasKey('11', $snap['pja']);
        $this->assertSame(1, $snap['meta']['pja'][0]['insiden']);
    }

    public function test_ringkasan_baseline_unik_tidak_dipetakan_ke_pja(): void
    {
        $r = $this->susun()['meta']['beranda']['ringkas'];

        // Semua detail lokasi (termasuk DL 201 tanpa PJA): bahaya 1, 2, 3, 5 masing-masing dihitung sekali.
        $this->assertSame(['ALL', 'LMO'], array_keys($r));
        $this->assertSame(4, $r['ALL']['w'][0]['High'][0]);
        $this->assertSame(3, $r['ALL']['ndl']);
        $this->assertSame(5, $r['ALL']['nBarisMaster']);
        $this->assertSame(4, $r['ALL']['nBarisDl']);
        // Jumlah per kelompok = baseline.
        $this->assertSame(4, array_sum(array_map(fn ($v) => $v[0], $r['ALL']['kel'][0]['High'])));
    }

    public function test_proses_bisnis_masuk_meta_cakupan_dan_baris_aktivitas(): void
    {
        $snap = $this->susun();
        $kode = array_column($snap['meta']['proses'], 'kode');

        // 26 kelompok aktivitas M02 (K1..K25 + K0 Lainnya) + X cadangan.
        $this->assertCount(27, $kode);
        $this->assertSame(['K1', 'K0', 'X'], [$kode[0], $kode[25], end($kode)]);
        $this->assertSame(4, $snap['cakupan']['PROSES|K16|ALL']['kpi']['G']);
        $kolom = array_flip($snap['meta']['kolomAkt']);
        $this->assertSame('K16', $snap['pja']['10']['akt'][0][$kolom['proses']]);
    }

    public function test_sql_berisi_cakupan_proses_dan_aturan_proses(): void
    {
        $sql = $this->sql()->matriksCakupan();

        $this->assertStringContainsString("('PROSES', concat(h.proses, '|ALL'))", $sql);
        $this->assertStringContainsString("toDate('2026-08-16') AS t_open", $sql);
        $this->assertStringContainsString("('K2', '(?i)handak|", $sql);
        $this->assertStringContainsString(' AS pb,', $sql);
    }

    public function test_sql_site_membatasi_detail_lokasi_dan_ambang_terisi(): void
    {
        $sql = $this->sql();
        $site = $sql->untukSite("LMO'x")->aktivitasDetailLokasi();

        $this->assertStringContainsString("AND t.site = 'LMO\\'x'", $site);
        $this->assertStringNotContainsString('{ambang}', $site);
        $this->assertStringContainsString("toDate('2026-11-14') AS t_end", $site);
        $this->assertStringNotContainsString('AND t.site =', $sql->aktivitasDetailLokasi());
    }

    private function master(): DrmMaster
    {
        return DrmMaster::dariArray([
            'baris' => [
                ['ri' => 11, 'aid' => 3, 'aktivitas' => 'Jalan Tambang', 'sub' => 'Grading', 'bahaya' => 'Jalan licin', 'kb' => 'Significant', 'kelompokM01' => '', 'umum' => false, 'idh' => [7]],
                ['ri' => 12, 'aid' => 4, 'aktivitas' => 'Dumping OB', 'sub' => 'Disposal', 'bahaya' => 'Unit terguling', 'kb' => 'High', 'kelompokM01' => '', 'umum' => true, 'idh' => [8]],
            ],
            'grup' => [1 => 'Loading Point', 2 => 'Disposal'],
            'grupBaris' => [1 => [11], 2 => [12]],
            'dl' => [['id' => 1, 'site' => 'LMO', 'lokasi' => 'Pit A', 'detail' => 'Front Loading', 'gid' => 1]],
            'label' => [['k' => 'Jalan', 'sk' => 'Licin', 'ri' => [11], 'sumber' => 'uji']],
            'obs' => [['teks' => 'Grading jalan', 'ri' => [11]]],
            'oak' => [],
        ]);
    }

    private function sql(): DrmSql
    {
        $cfg = config('peta_risiko_drm');

        return new DrmSql($cfg, CarbonImmutable::parse(self::SAMPAI), $this->master()->literal($cfg));
    }

    /** @return array{meta: array<string, mixed>, pja: array<string, array<string, mixed>>} */
    private function susun(): array

    {
        $baris = fn (string $minggu, string $status, string $n1, string $nf, string $kf, string $cf, int $n, int $laten = 0) => [
            'jenis' => 'SITE', 'kunci' => 'LMO', 'minggu' => $minggu, 'status' => $status,
            'k1' => 'Hampir Pasti', 'c1' => $n1 === 'Significant' ? 'Bencana' : 'Minor', 'n1' => $n1,
            'kf' => $kf, 'cf' => $cf, 'nf' => $nf, 'laten' => (string) $laten, 'n' => (string) $n,
        ];
        $matriks = [
            $baris(self::SAMPAI, 'K', 'Significant', 'Medium', 'Mungkin', 'Sedang', 1),
            $baris(self::SAMPAI, 'G', 'Significant', 'Significant', 'Hampir Pasti', 'Bencana', 1),
            $baris(self::SAMPAI, 'T', 'Low', 'Low', 'Hampir Pasti', 'Minor', 1, 1),
            $baris('2026-11-07', 'T', 'Low', 'Low', 'Hampir Pasti', 'Minor', 5, 1),
            ['jenis' => 'PJA', 'kunci' => '10', 'minggu' => self::SAMPAI, 'status' => 'T', 'k1' => 'Mungkin', 'c1' => 'Minor', 'n1' => 'Medium',
                'kf' => 'Mungkin', 'cf' => 'Minor', 'nf' => 'Medium', 'laten' => '1', 'n' => '2'],
            ['jenis' => 'PROSES', 'kunci' => 'K16|ALL', 'minggu' => self::SAMPAI, 'status' => 'G', 'k1' => 'Mungkin', 'c1' => 'Minor', 'n1' => 'High',
                'kf' => 'Mungkin', 'cf' => 'Minor', 'nf' => 'High', 'laten' => '0', 'n' => '4'],
        ];
        $hira = fn (string $hid, string $a) => ['hid' => $hid, 'a' => $a, 'an' => ucfirst($a), 'sn' => 'Sub', 'bn' => 'Bahaya '.$hid, 'p' => 'Potensi',
            'sites' => ['LMO'], 'grp' => ['Loading Point'], 'k1' => 'Mungkin', 'c1' => 'Minor', 'n1' => 'High', 'k2' => 'Langka', 'c2' => 'Minor',
            'n2' => 'Low', 'kt' => 'Administrasi: SOP', 'faktor' => 'Man', 'aspek' => 'Safety', 'di_a' => 1, 'di_b' => 0, 'proses' => 'K16'];
        $akt = fn (string $dl, string $a) => ['dl' => $dl, 'aktivitas' => ucfirst($a), 'a' => $a, 'bahaya' => '2', 'k' => '1', 'g' => '1', 't' => '0', 'x' => '0',
            'gap_open' => '1', 'gap_ulang' => '0', 'gap_stop' => '0', 'sig' => '0', 'high' => '2', 'terakhir' => '2026-11-10', 'strip' => '--KG', 'via_bukti' => '0', 'proses' => 'K16'];

        $data = [
            'sites' => ['LMO'],
            'hira' => [$hira('1', 'loading ob'), $hira('2', 'loading ob'), $hira('3', 'dumping'), [...$hira('4', 'pumping'), 'di_a' => 0, 'di_b' => 0], [...$hira('5', 'loading ob'), 'di_a' => 0, 'di_b' => 0, 'di_d' => 1]],
            'dl' => [
                ['dl' => '101', 'site' => 'LMO', 'id_lok' => '1', 'lokasi' => 'Pit A', 'detlok' => 'Front Loading', 'grp' => 'Loading Point', 'pja' => ['10']],
                ['dl' => '102', 'site' => 'LMO', 'id_lok' => '1', 'lokasi' => 'Pit A', 'detlok' => 'Disposal', 'grp' => 'Disposal', 'pja' => ['10']],
                ['dl' => '201', 'site' => 'LMO', 'id_lok' => '2', 'lokasi' => 'CPP', 'detlok' => 'ROM', 'grp' => 'CPP / ROM', 'pja' => []],
            ],
            'pja' => [
                ['id' => '10', 'nama' => 'PJA Pit BC', 'parent' => '0', 'tipe' => 'BC', 'id_site' => '1'],
                ['id' => '11', 'nama' => 'PJA Pit Mitra', 'parent' => '10', 'tipe' => 'Mitra Kerja', 'id_site' => '1'],
            ],
            'matriks' => $matriks,
            'insiden' => [['no' => 'I1', 'dl' => '101', 'tanggal' => now()->subMonth()->toDateString(), 'kategori' => 'Near Miss', 'site' => 'LMO',
                'lokasi' => 'Pit A', 'detlok' => 'Front Loading', 'kronologi' => 'x', 'potensi' => '', 'pja_bc' => '', 'pja_mk' => '', 'akar' => [], 'bahaya' => ['1']]],
            'personil' => [
                ['pja_id' => '10', 'pja' => 'PJA Pit BC', 'tipe' => 'BC', 'kategori' => 'PJA Layer 1', 'sid' => 'S1', 'nama' => 'A', 'perusahaan' => 'BC', 'site' => 'LMO', 'id_mk' => '0', 'harian' => [0, 1]],
                ['pja_id' => '11', 'pja' => 'PJA Pit Mitra', 'tipe' => 'Mitra Kerja', 'kategori' => 'PJA Layer 2', 'sid' => 'S2', 'nama' => 'B', 'perusahaan' => 'MK', 'site' => 'LMO', 'id_mk' => '1', 'harian' => [2, 0]],
                ['pja_id' => '99', 'pja' => 'PJA Lain', 'tipe' => 'BC', 'kategori' => '', 'sid' => 'S3', 'nama' => 'C', 'perusahaan' => 'BC', 'site' => 'LMO', 'id_mk' => '0', 'harian' => [0, 0]],
            ],
            'bearc' => [['sid' => 'S1', 'mulai' => '2026-11-03', 'persen' => 0.8, 'banned' => 0], ['sid' => 'S3', 'mulai' => '2026-11-03', 'persen' => 0.1, 'banned' => 1]],
            'coverage' => [['pja' => '10', 'dl' => '101', 'semua' => '5', 'dedicated' => '2']],
            'akt' => [$akt('101', 'loading ob'), $akt('102', 'dumping'), $akt('201', 'crushing')],
            'sorot' => [
                ['dl' => '101', 'hid' => '1', 'status' => 'T', 'open_n' => '0', 'rep' => '0', 'stop' => '0', 'terakhir' => '1970-01-01', 'n_inc' => '1', 'tgl_inc' => '2026-10-01'],
                ['dl' => '101', 'hid' => '2', 'status' => 'G', 'open_n' => '1', 'rep' => '1', 'stop' => '0', 'terakhir' => '2026-11-10', 'n_inc' => '0', 'tgl_inc' => ''],
            ],
            'temuan' => [['dl' => '101', 'tanggal' => '2026-11-10', 'aktivitas' => ['Loading ob']], ['dl' => '201', 'tanggal' => '2026-11-09', 'aktivitas' => []]],
            'mapping' => [
                ['hid' => '1', 'n_a' => '0', 'label_a' => [], 'n_b' => '1', 'teks_b' => ['loading ob']],
                ['hid' => '2', 'n_a' => '2', 'label_a' => ['Hazard › Jalan licin', 'Hazard › Jalan berlubang'], 'n_b' => '0', 'teks_b' => []],
                ['hid' => '5', 'n_a' => '0', 'label_a' => [], 'n_b' => '0', 'teks_b' => [], 'n_d' => '2', 'butir_d' => ['APD › Helm', 'APD › Rompi']],
            ],
            // Per site; hid 2 muncul di dua "site" untuk menguji penjumlahan.
            // Pasangan baseline per detail lokasi: hid 5 (aktivitas sama) TIDAK ada di lokasi PJA 10.
            'pasangan' => [['dl' => '101', 'pasangan' => '1:T,2:G'], ['dl' => '102', 'pasangan' => '3:K'], ['dl' => '201', 'pasangan' => '5:K']],
            'statusBahaya' => [
                ['hid' => '1', 'dl_n' => '2', 'g' => '0', 'k' => '0', 't' => '2', 'x' => '0', 'dl_open' => '0', 'dl_ulang' => '0', 'dl_stop' => '0',
                    'temuan_open' => '0', 'temuan_jendela' => '0', 'terakhir' => '1970-01-01'],
                ['hid' => '2', 'dl_n' => '2', 'g' => '0', 'k' => '2', 't' => '0', 'x' => '0', 'dl_open' => '0', 'dl_ulang' => '0', 'dl_stop' => '0',
                    'temuan_open' => '0', 'temuan_jendela' => '3', 'terakhir' => '2026-11-01'],
                ['hid' => '2', 'dl_n' => '1', 'g' => '1', 'k' => '0', 't' => '0', 'x' => '0', 'dl_open' => '1', 'dl_ulang' => '0', 'dl_stop' => '0',
                    'temuan_open' => '1', 'temuan_jendela' => '1', 'terakhir' => '2026-11-10'],
                ['hid' => '4', 'dl_n' => '1', 'g' => '0', 'k' => '0', 't' => '0', 'x' => '1', 'dl_open' => '0', 'dl_ulang' => '0', 'dl_stop' => '0',
                    'temuan_open' => '0', 'temuan_jendela' => '0', 'terakhir' => '1970-01-01'],
            ],
        ];

        return (new DrmSnapshotBuilder(new RfidClickHouseClient()))
            ->susun($data, CarbonImmutable::parse(self::SAMPAI), config('peta_risiko_drm'));
    }
}
