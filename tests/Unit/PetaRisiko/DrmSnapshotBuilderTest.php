<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmSnapshotBuilder;
use App\Services\PetaRisiko\Drm\DrmSql;
use App\Services\SportEvaluation\RfidClickHouseClient;
use Carbon\CarbonImmutable;
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

    public function test_risiko_laten_hanya_bahaya_insiden_yang_tidak_teridentifikasi(): void
    {
        $snap = $this->susun();

        $this->assertSame(1, $snap['meta']['cakupan']['SITE|LMO']['kpi']['laten']);
        $this->assertSame(1, $snap['meta']['cakupan']['PJA|10']['kpi']['laten']);
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
        // Laten bahaya insiden (hid 1, proses OB) ikut tercatat di cakupan proses.
        $this->assertSame(1, $snap['meta']['cakupan']['PROSES|OB|ALL']['kpi']['laten']);
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
        $baris = fn (string $minggu, string $status, string $n1, string $nf, string $kf, string $cf, int $n) => [
            'jenis' => 'SITE', 'kunci' => 'LMO', 'minggu' => $minggu, 'status' => $status,
            'k1' => 'Hampir Pasti', 'c1' => $n1 === 'Significant' ? 'Bencana' : 'Minor', 'n1' => $n1,
            'kf' => $kf, 'cf' => $cf, 'nf' => $nf, 'n' => (string) $n,
        ];
        $matriks = [
            $baris(self::SAMPAI, 'K', 'Significant', 'Medium', 'Mungkin', 'Sedang', 1),
            $baris(self::SAMPAI, 'G', 'Significant', 'Significant', 'Hampir Pasti', 'Bencana', 1),
            $baris(self::SAMPAI, 'T', 'Low', 'Low', 'Hampir Pasti', 'Minor', 1),
            $baris('2026-11-07', 'T', 'Low', 'Low', 'Hampir Pasti', 'Minor', 5),
            ['jenis' => 'PJA', 'kunci' => '10', 'minggu' => self::SAMPAI, 'status' => 'T', 'k1' => 'Mungkin', 'c1' => 'Minor', 'n1' => 'Medium',
                'kf' => 'Mungkin', 'cf' => 'Minor', 'nf' => 'Medium', 'n' => '2'],
            ['jenis' => 'PROSES', 'kunci' => 'OB|ALL', 'minggu' => self::SAMPAI, 'status' => 'G', 'k1' => 'Mungkin', 'c1' => 'Minor', 'n1' => 'High',
                'kf' => 'Mungkin', 'cf' => 'Minor', 'nf' => 'High', 'n' => '4'],
        ];
        $hira = fn (string $hid, string $a) => ['hid' => $hid, 'a' => $a, 'an' => ucfirst($a), 'sn' => 'Sub', 'bn' => 'Bahaya '.$hid, 'p' => 'Potensi',
            'sites' => ['LMO'], 'grp' => ['Loading Point'], 'k1' => 'Mungkin', 'c1' => 'Minor', 'n1' => 'High', 'k2' => 'Langka', 'c2' => 'Minor',
            'n2' => 'Low', 'kt' => 'Administrasi: SOP', 'faktor' => 'Man', 'aspek' => 'Safety', 'di_a' => 1, 'di_b' => 0, 'proses' => 'OB'];
        $akt = fn (string $dl, string $a) => ['dl' => $dl, 'aktivitas' => ucfirst($a), 'a' => $a, 'bahaya' => '2', 'k' => '1', 'g' => '1', 't' => '0', 'x' => '0',
            'gap_open' => '1', 'gap_ulang' => '0', 'gap_stop' => '0', 'sig' => '0', 'high' => '2', 'terakhir' => '2026-11-10', 'strip' => '--KG', 'via_bukti' => '0', 'proses' => 'OB'];

        $data = [
            'sites' => ['LMO'],
            'hira' => [$hira('1', 'loading ob'), $hira('2', 'loading ob'), $hira('3', 'dumping')],
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
        ];

        return (new DrmSnapshotBuilder(new RfidClickHouseClient()))
            ->susun($data, CarbonImmutable::parse(self::SAMPAI), config('peta_risiko_drm'));
    }
}
