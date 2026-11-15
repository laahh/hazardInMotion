<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmMappingEkspor;
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
        $kpi = $snap['meta']['cakupan']['SITE|LMO']['kpi'];

        $this->assertSame(3, $kpi['total']);
        $this->assertSame(1, $kpi['K']);
        $this->assertSame(1, $kpi['G']);
        $this->assertSame(1, $kpi['T']);
        // Terkontrol Significant → residual Medium = turun kategori.
        $this->assertSame(1, $kpi['turun']);
        $this->assertSame(1, $kpi['final']['Medium']);
        // Minggu lama hanya masuk tren, tidak ke KPI.
        $this->assertSame(5, $snap['meta']['cakupan']['SITE|LMO']['tren']['2026-11-07']['total']);
        // Matriks awal: Hampir Pasti (baris 0) × Bencana (kolom 4).
        $this->assertSame(2, $snap['meta']['cakupan']['SITE|LMO']['mAwal'][0][4]);
    }

    public function test_risiko_laten_diambil_dari_kolom_laten_matriks_minggu_terakhir(): void
    {
        $snap = $this->susun();

        // Hanya baris laten=1 di minggu terakhir yang dihitung (minggu lama & baris G diabaikan).
        $this->assertSame(1, $snap['meta']['cakupan']['SITE|LMO']['kpi']['laten']);
        $this->assertSame(2, $snap['meta']['cakupan']['PJA|10']['kpi']['laten']);
        $this->assertSame(0, $snap['meta']['cakupan']['PROSES|OB|ALL']['kpi']['laten']);
        // Laten tidak boleh melebihi jumlah tidak teridentifikasi + tidak terpetakan.
        foreach ($snap['meta']['cakupan'] as $c) {
            $this->assertLessThanOrEqual($c['kpi']['T'] + $c['kpi']['X'], $c['kpi']['laten']);
        }
    }

    public function test_baris_excel_status_gabungan_mapping_dan_insiden(): void
    {
        $baris = array_column($this->susun()['ekspor'], null, 'hid');

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
        $this->assertSame('OB Removal', $baris['3']['proses']);
        // hid 4: tidak punya pasangan SAP (Master A/B) → di luar baseline, tidak dipaksakan.
        $this->assertSame('X', $baris['4']['status']);
        $this->assertSame('', $baris['4']['final']);
        $this->assertFalse($baris['4']['laten']);
        // hid 5: hanya punya pasangan butir checklist OAK (Master D) → tetap terpetakan, bukan X.
        $this->assertSame('-', $baris['5']['status']);
        $this->assertSame(2, $baris['5']['n_d']);
        $this->assertSame('APD › Helm | APD › Rompi', $baris['5']['butir_d']);
    }

    public function test_sql_memuat_checklist_oak_dan_label_hazard_spesifik(): void
    {
        $sql = new DrmSql(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI));
        $status = $sql->pasanganBaseline();
        $mingguan = $sql->matriksCakupan();

        // Butir tidak sesuai = gap, aktivitas butir dari register, dan syarat kata di teks bahaya.
        $this->assertStringContainsString('toUInt8(j.sid = 10187)', $status);
        $this->assertStringContainsString('toInt64(id_oak_activity) AS aid', $status);
        $this->assertStringContainsString('length(arrayIntersect(i.ts, h.tbb)) >= 1', $status);
        $this->assertStringContainsString('OR ifNull(gd.ts_d, 0) > 0', $status);
        // Label spesifik (≤ 2 kelompok aktivitas) ikut menandai aktivitas; dipakai juga di tren mingguan.
        $this->assertStringContainsString('HAVING uniqExact(h.proses) <= 2', $status);
        $this->assertStringContainsString('FROM eva', $mingguan);
        $this->assertStringContainsString('SELECT dl, hid, minggu, toUInt8(if(ts = 1, 4, 3)) FROM ed', $mingguan);
    }

    public function test_file_excel_mapping_valid_dan_bisa_dibaca(): void
    {
        $snap = $this->susun();
        $file = tempnam(sys_get_temp_dir(), 'drm').'.xlsx';
        (new DrmMappingEkspor())->tulis($snap['ekspor'], CarbonImmutable::parse(self::SAMPAI), config('peta_risiko_drm'), $file);

        $wb = IOFactory::load($file);
        $this->assertSame(['Mapping bahaya-risiko', 'Ringkasan', 'Keterangan'], $wb->getSheetNames());
        $ws = $wb->getSheet(0);
        $this->assertSame('Bahaya / penyebab potensial', (string) $ws->getCell('D1')->getValue());
        $this->assertSame(6, $ws->getHighestRow());
        $this->assertSame(1, $ws->getCell('A2')->getValue());
        $ringkas = $wb->getSheet(1)->toArray();
        $this->assertEquals(['BASELINE (terpetakan ke SAP + punya detail lokasi)', 2], array_slice($ringkas[2], 0, 2));
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

    public function test_proses_bisnis_masuk_meta_cakupan_dan_baris_aktivitas(): void
    {
        $snap = $this->susun();
        $kode = array_column($snap['meta']['proses'], 'kode');

        $this->assertCount(18, $kode);
        $this->assertSame('X', end($kode));
        $this->assertSame(11, count(array_filter($snap['meta']['proses'], fn ($p) => $p['jenis'] === 'inti')));
        $this->assertSame(4, $snap['meta']['cakupan']['PROSES|OB|ALL']['kpi']['G']);
        $kolom = array_flip($snap['meta']['kolomAkt']);
        $this->assertSame('OB', $snap['pja']['10']['akt'][0][$kolom['proses']]);
    }

    public function test_sql_berisi_cakupan_proses_dan_aturan_proses(): void
    {
        $sql = (new DrmSql(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI)))->matriksCakupan();

        $this->assertStringContainsString("('PROSES', concat(h.proses, '|ALL'))", $sql);
        $this->assertStringContainsString("toDate('2026-08-16') AS t_open", $sql);
        $this->assertStringContainsString("('DNB', 'blast|", $sql);
        $this->assertStringContainsString(' AS pb,', $sql);
    }

    public function test_sql_site_membatasi_detail_lokasi_dan_ambang_terisi(): void
    {
        $sql = new DrmSql(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI));
        $site = $sql->untukSite("LMO'x")->aktivitasDetailLokasi();

        $this->assertStringContainsString("AND t.site = 'LMO\\'x'", $site);
        $this->assertStringNotContainsString('{ambang}', $site);
        $this->assertStringContainsString("toDate('2026-11-14') AS t_end", $site);
        $this->assertStringNotContainsString('AND t.site =', $sql->aktivitasDetailLokasi());
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
            ['jenis' => 'PROSES', 'kunci' => 'OB|ALL', 'minggu' => self::SAMPAI, 'status' => 'G', 'k1' => 'Mungkin', 'c1' => 'Minor', 'n1' => 'High',
                'kf' => 'Mungkin', 'cf' => 'Minor', 'nf' => 'High', 'laten' => '0', 'n' => '4'],
        ];
        $hira = fn (string $hid, string $a) => ['hid' => $hid, 'a' => $a, 'an' => ucfirst($a), 'sn' => 'Sub', 'bn' => 'Bahaya '.$hid, 'p' => 'Potensi',
            'sites' => ['LMO'], 'grp' => ['Loading Point'], 'k1' => 'Mungkin', 'c1' => 'Minor', 'n1' => 'High', 'k2' => 'Langka', 'c2' => 'Minor',
            'n2' => 'Low', 'kt' => 'Administrasi: SOP', 'faktor' => 'Man', 'aspek' => 'Safety', 'di_a' => 1, 'di_b' => 0, 'proses' => 'OB'];
        $akt = fn (string $dl, string $a) => ['dl' => $dl, 'aktivitas' => ucfirst($a), 'a' => $a, 'bahaya' => '2', 'k' => '1', 'g' => '1', 't' => '0', 'x' => '0',
            'gap_open' => '1', 'gap_ulang' => '0', 'gap_stop' => '0', 'sig' => '0', 'high' => '2', 'terakhir' => '2026-11-10', 'strip' => '--KG', 'via_bukti' => '0', 'proses' => 'OB'];

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
            ],
        ];

        return (new DrmSnapshotBuilder(new RfidClickHouseClient()))
            ->susun($data, CarbonImmutable::parse(self::SAMPAI), config('peta_risiko_drm'));
    }
}
