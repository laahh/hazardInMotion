<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmBeranda;
use App\Services\PetaRisiko\Drm\DrmMaster;
use App\Services\PetaRisiko\Drm\DrmSql;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DrmBerandaTest extends TestCase
{
    private const SAMPAI = '2026-11-14'; // Sabtu → minggu berjalan mulai Minggu 8 Nov

    public function test_status_tanpa_laporan_adalah_gap_dan_masalah_mengalahkan_deteksi(): void
    {
        $this->assertSame(['none', 0], DrmBeranda::status(0, 0));
        $this->assertSame(['ok', 0], DrmBeranda::status(DrmBeranda::DETEKSI, 0));
        $this->assertSame(['ok', 0], DrmBeranda::status(0, DrmBeranda::SPESIFIK));
        // OAK stop di kelompok membuat semua baris kelompok bermasalah walau ada deteksi.
        $this->assertSame(['bm', DrmBeranda::OAK_STOP], DrmBeranda::status(DrmBeranda::DETEKSI | DrmBeranda::OAK_STOP, 0));
        $this->assertSame(['bm', DrmBeranda::BELUM_CLOSED | DrmBeranda::BERULANG], DrmBeranda::status(DrmBeranda::DETEKSI, 2 | 4 | 8));
        // OAK Stop pada OAK yang checklist-nya menangkap bahaya ini (bit spesifik 16) = gap.
        $this->assertSame(['bm', DrmBeranda::OAK_STOP], DrmBeranda::status(0, 2 | 16));
        // Butir checklist OAK Tidak Sesuai (lookup 10187) = gap.
        $this->assertSame(['bm', DrmBeranda::TIDAK_SESUAI], DrmBeranda::status(0, 2 | 32));
        // Bit insiden (harian) bukan masalah dan bukan deteksi.
        $this->assertSame(['none', 0], DrmBeranda::status(0, DrmBeranda::INSIDEN));
    }

    public function test_urai_bit_jarang_dan_minggu_kalender_minggu_sabtu(): void
    {
        $this->assertSame([0 => 3, 2 => 17], DrmBeranda::urai('0:1,2:17,0:2'));
        $this->assertSame([], DrmBeranda::urai(''));

        $b = new DrmBeranda(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI));
        $minggu = $b->minggu();
        $this->assertSame('2026-11-08', $minggu[0]);
        $this->assertSame('2026-11-01', $minggu[1]);
        $this->assertCount((int) config('peta_risiko_drm.beranda.minggu'), $minggu);
        $this->assertSame('2026-10-18', $b->hari0());
    }

    public function test_bahaya_umum_teks_sama_di_empat_kelompok_dalam_satu_grup(): void
    {
        $hira = [];
        foreach (['DNB', 'MNT', 'CG', 'CH'] as $i => $kel) {
            $hira["u$i"] = ['bn' => 'Tidak memakai APD', 'proses' => $kel, 'n1' => 'High'];
        }
        $hira['s1'] = ['bn' => 'Lereng longsor', 'proses' => 'OB', 'n1' => 'Significant'];
        $hidPerDl = ['1' => ['u0', 'u1', 's1'], '2' => ['u2', 'u3'], '3' => ['u0']];
        $dl = ['1' => ['grp' => 'Pit'], '2' => ['grp' => 'Pit'], '3' => ['grp' => 'Disposal']];

        $umum = (new DrmBeranda(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI)))->bahayaUmum(['1', '2', '3'], $hidPerDl, $hira, $dl);

        $this->assertEqualsCanonicalizing(['u0', 'u1', 'u2', 'u3'], $umum['Pit']);
        $this->assertArrayNotHasKey('Disposal', $umum); // hanya 1 kelompok di grup itu
    }

    public function test_berkas_dan_ringkasan_mgmt_per_minggu_per_level(): void
    {
        $b = (new DrmBeranda(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI)))->denganGrup(['1' => ['grp' => 'Pit'], '9' => ['grp' => 'Pit']]);
        $hira = [
            'h1' => ['bn' => 'Jalan licin', 'proses' => 'CH', 'n1' => 'Significant'],
            'h2' => ['bn' => 'Debu', 'proses' => 'CH', 'n1' => 'High'],
            'h3' => ['bn' => 'Retakan', 'proses' => 'OB', 'n1' => 'High'],
        ];
        $idx = $b->indeks(
            [
                ['dl' => '1', 'kel' => 'CH', 'w' => '0:1,1:17', 'h' => '27:1', 'last' => '2026-11-14'],
                ['dl' => '1', 'kel' => '', 'w' => '', 'h' => '', 'last' => '2026-11-14'],
                ['dl' => '9', 'kel' => 'CH', 'w' => '0:1', 'h' => '', 'last' => '2026-11-10'], // bukan detail lokasi PJA
            ],
            [['dl' => '1', 'hid' => 'h3', 'w' => '0:6', 'h' => '27:70']],
        );
        $berkas = $b->berkas(['1'], ['1' => ['h1', 'h2', 'h3']], $hira, ['1' => ['grp' => 'Pit']], $idx, [
            '1' => [['no' => '7', 'dl' => '1', 'tanggal' => '2026-03-01', 'kategori' => 'Near Miss', 'detlok' => 'Front', 'lokasi' => 'Pit',
                'label' => 'Jalan › Licin', 'sebelum' => '0', 'bulan' => '1', 'sesudah' => '2', 'k_sebelum' => '3', 'k_sesudah' => '1', 'terakhir' => '1970-01-01', 'belum_closed' => '1']],
        ]);

        $this->assertSame(['1|CH'], array_keys((array) $berkas['kd']));
        $this->assertSame('2026-11-14', ((array) $berkas['dlast'])['1']);
        $this->assertSame('', $berkas['rc'][0]['terakhir']);
        $this->assertSame(0, $berkas['rc'][0]['sebelum']);

        $m = $b->ringkasMgmt(['1'], ['1' => ['h1', 'h2', 'h3']], $hira, $berkas, [['tanggal' => '2026-11-01'], ['tanggal' => '2026-01-01']]);
        // Satuan ketat [tot, penuh, sebagian, hanya bermasalah, tanpa laporan, closed, ulang, stop, tidak sesuai].
        // Minggu 0: CH terdeteksi → h1 (Significant) & h2 (High) terkontrol penuh; h3 belum closed → hanya bermasalah.
        $this->assertSame([1, 1, 0, 0, 0, 0, 0, 0, 0], array_slice($m['w'][0]['Significant'], 0, 9));
        $this->assertSame([2, 1, 0, 1, 0, 1, 0, 0, 0], array_slice($m['w'][0]['High'], 0, 9));
        // Minggu 1: OAK stop di CH → h1 & h2 hanya bermasalah; h3 tanpa laporan.
        $this->assertSame([1, 0, 0, 1, 0, 0, 0, 1, 0], array_slice($m['w'][1]['Significant'], 0, 9));
        $this->assertSame([2, 0, 0, 1, 1, 0, 0, 1, 0], array_slice($m['w'][1]['High'], 0, 9));
        $this->assertSame(['CH' => 1, 'OB' => 1], $m['kelTot']['High']);
        $this->assertSame(['OB' => 1], $m['kelGap'][0]['High']);
        $this->assertSame([0 => 1, 1 => 1], $m['kgTot']['High']);
        $this->assertSame([1 => 1], $m['u4'][0]['High']); // h3 (OB|Pit) tak terkontrol minggu 0-3
        $this->assertSame([0 => 1], $m['u4'][1]['Significant']); // h1 terkontrol di minggu 0, tidak di 1-4
        $this->assertSame(0, $m['lama']);
        $this->assertSame(1, $m['insiden']);
    }

    public function test_ringkasan_per_kelompok_grup_dan_matriks_konsisten_dengan_total(): void
    {
        $b = (new DrmBeranda(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI)))
            ->denganGrup(['1' => ['grp' => 'Pit'], '2' => ['grp' => 'Disposal']]);
        $hira = [
            'h1' => ['a' => '7', 'bn' => 'Jalan licin', 'proses' => 'K13', 'n1' => 'High', 'k1' => 'Mungkin', 'c1' => 'Major', 'k2' => 'Langka', 'c2' => 'Minor'],
            'h2' => ['a' => '8', 'bn' => 'Retakan', 'proses' => 'K16', 'n1' => 'High', 'k1' => '', 'c1' => ''],
        ];
        // h1 ada di dua detail lokasi (Pit terdeteksi, Disposal tanpa laporan); h2 hanya di Disposal tanpa laporan.
        $idx = $b->indeks([['dl' => '1', 'kel' => 'K13', 'w' => '0:1', 'h' => '', 'last' => '2026-11-14']], []);
        $pasangan = ['1' => ['h1'], '2' => ['h1', 'h2']];
        $berkas = $b->berkas(['1', '2'], $pasangan, $hira, ['1' => ['grp' => 'Pit'], '2' => ['grp' => 'Disposal']], $idx, []);
        $m = $b->ringkasMgmt(['1', '2'], $pasangan, $hira, $berkas, []);

        // Total: h1 sebagian (1 dari 2 DL), h2 tanpa laporan.
        $this->assertSame([2, 0, 1, 0, 1, 0, 0, 0, 0], array_slice($m['w'][0]['High'], 0, 9));
        // Per kelompok: jumlahnya sama dengan total.
        $this->assertSame([1, 0, 1, 0, 0, 0, 0, 0, 0], array_slice($m['kel'][0]['High']['K13'], 0, 9));
        $this->assertSame([1, 0, 0, 0, 1, 0, 0, 0, 0], array_slice($m['kel'][0]['High']['K16'], 0, 9));
        // Per grup: status dari detail lokasi grup itu saja (h1 penuh di Pit, tanpa laporan di Disposal).
        $this->assertSame([1, 1, 0, 0, 0, 0, 0, 0, 0], array_slice($m['grp'][0]['High']['Pit'], 0, 9));
        $this->assertSame([2, 0, 0, 0, 2, 0, 0, 0, 0], array_slice($m['grp'][0]['High']['Disposal'], 0, 9));
        // Matriks: hanya h1 punya nilai risiko; sebagian → final tetap risiko awal (Mungkin=2, Major=3 → sel 13).
        $this->assertSame([13 => 1], $m['mx'][0]['High']['K13']['a']);
        $this->assertSame([13 => 1], $m['mx'][0]['High']['K13']['f']);
        $this->assertArrayNotHasKey('K16', $m['mx'][0]['High']);
        // Kelompok di dalam grup: jumlahnya sama dengan total grup.
        $this->assertSame([1, 0, 0, 0, 1, 0, 0, 0, 0], array_slice($m['gk'][0]['High']['Disposal|K13'], 0, 9));
        $this->assertSame([1, 0, 0, 0, 1, 0, 0, 0, 0], array_slice($m['gk'][0]['High']['Disposal|K16'], 0, 9));
        // Per detail lokasi: DL 1 punya h1 terkontrol; DL 2 punya h1 & h2 tanpa laporan.
        $this->assertSame([1, 1, 0, 0, 0, 0, 0, 0, 0], array_slice($m['dlw'][0]['High']['1'], 0, 9));
        $this->assertSame([2, 0, 0, 0, 2, 0, 0, 0, 0], array_slice($m['dlw'][0]['High']['2'], 0, 9));
        // Per aktivitas HIRA: di semua grup (h1 sebagian) dan di dalam grup (Disposal: tanpa laporan).
        $this->assertSame([1, 0, 1, 0, 0, 0, 0, 0, 0], array_slice($m['akt'][0]['High']['7'], 0, 9));
        $this->assertSame([1, 0, 0, 0, 1, 0, 0, 0, 0], array_slice($m['gka'][0]['High']['Disposal|7'], 0, 9));
        $this->assertSame([1, 1, 0, 0, 0, 0, 0, 0, 0], array_slice($m['gka'][0]['High']['Pit|7'], 0, 9));
    }

    public function test_sql_beranda_memakai_minggu_kalender_dan_root_cause(): void
    {
        $sql = (new DrmSql(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI), DrmMaster::dariArray([])->literal(config('peta_risiko_drm'))))->untukSite('LMO');

        $kel = $sql->berandaKelompok();
        $this->assertStringContainsString('toStartOfWeek(t_end, 0) AS wk0', $kel);
        $this->assertStringContainsString("AND t.Site = 'LMO'", $kel);
        // OBS/OAK tidak lagi menandai seluruh kelompok aktivitas (17 Nov 2026).
        $this->assertStringNotContainsString('FROM sap s JOIN okx k ON k.tx = s.tx', $kel);
        // Tidak ada sumber yang menandai seluruh kelompok (dokumentasi Risk Matrix 5x5): kev kosong.
        $this->assertStringContainsString("kev AS (SELECT toInt64(0) AS dl, '' AS kel, toDate('1970-01-01') AS d WHERE 0)", $kel);

        $spe = $sql->berandaSpesifik();
        // OBS / OAK: label persis M11 / M12 (dokumentasi Risk Matrix 5x5), bukan checklist / tema / kelompok.
        $this->assertStringContainsString('LEFT JOIN mox m ON m.kc = k.kc', $spe);
        // OAK juga lewat kurasi aktivitas OAK → aktivitas HIRA (mkx), digabung tanpa duplikat.
        $this->assertStringContainsString('LEFT JOIN mkx a ON a.ka = k.ka', $spe);
        $this->assertStringContainsString("ARRAY JOIN arrayDistinct(arrayConcat(m.rs, if(s.j = 'OBSERVASI AREA KRITIS', a.rs,", $spe);
        $this->assertStringContainsString("CRC32(replaceRegexpAll(replaceRegexpAll(lowerUTF8(ifNull(tx, '')), 'risk\\\\s*intervention\\\\s*center', 'langsung'), '[^0-9a-z]+', '')) AS kc", $spe);
        $this->assertStringNotContainsString('bcbeats.', $spe);
        $this->assertStringNotContainsString('obds.m_oak_detail_objek', $spe);
        $this->assertStringContainsString('toUInt8(2 + 16 * stop + 32 * ts)', $spe);
        $this->assertStringContainsString("r.Status_Causal = 'ROOT CAUSE'", $spe);
        $this->assertStringContainsString('max(toUInt8(l.nl >= 3)) AS ul', $spe);
        $this->assertStringContainsString('max(toUInt8(e.cd IS NULL)) AS op', $spe);
        $this->assertStringContainsString('parseDateTimeBestEffortUSOrNull', $spe);

        $inc = $sql->berandaInsiden();
        $this->assertStringContainsString("toDate('2025-10-01')", $inc);
        $this->assertStringContainsString("NOT IN ('CLOSED', 'DONE')", $inc);
    }

    public function test_mode_pita_cakupan_untuk_ringkasan_unik(): void
    {
        $b = (new DrmBeranda(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI)))
            ->denganGrup(['1' => ['grp' => 'Pit'], '2' => ['grp' => 'Pit'], '3' => ['grp' => 'Pit'], '4' => ['grp' => 'Pit'], '5' => ['grp' => 'Pit']]);
        $hira = ['h1' => ['a' => '7', 'bn' => 'Jalan licin', 'proses' => 'K13', 'n1' => 'High']];
        // h1 di 5 DL; terdeteksi (kelompok K13) di 4 DL → cakupan 80% → pita 1 (≥80%), bukan "sebagian".
        $idx = $b->indeks(array_map(fn ($d) => ['dl' => (string) $d, 'kel' => 'K13', 'w' => '0:1', 'h' => '', 'last' => '2026-11-14'], [1, 2, 3, 4]), []);
        $pasangan = array_fill_keys(['1', '2', '3', '4', '5'], ['h1']);
        $dl = array_fill_keys(['1', '2', '3', '4', '5'], ['grp' => 'Pit']);
        $berkas = $b->berkas(array_keys($pasangan), $pasangan, $hira, $dl, $idx, []);

        $ketat = $b->ringkasMgmt(array_map('strval', array_keys($pasangan)), $pasangan, $hira, $berkas, []);
        $pita = $b->ringkasMgmt(array_map('strval', array_keys($pasangan)), $pasangan, $hira, $berkas, [], true);
        $this->assertSame([1, 0, 1, 0, 0], array_slice($ketat['w'][0]['High'], 0, 5)); // ketat: sebagian
        $this->assertSame([1, 1, 0, 0, 0], array_slice($pita['w'][0]['High'], 0, 5));  // pita: ≥80%
        $this->assertSame(800, $pita['w'][0]['High'][9]);                            // cakupan 80% (per mil)
        // Minggu 1 tanpa deteksi: pita 4 (0%).
        $this->assertSame([1, 0, 0, 0, 1], array_slice($pita['w'][1]['High'], 0, 5));
    }

    public function test_kartu_status_terkontrol_gap_open_laten_turun(): void
    {
        $b = (new DrmBeranda(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI)))
            ->denganGrup(['1' => ['grp' => 'Pit'], '2' => ['grp' => 'Pit']])
            ->denganInsiden(['1|h3' => 2, '2|h3' => 1]);
        $hira = [
            'h1' => ['a' => '7', 'bn' => 'Jalan licin', 'proses' => 'K13', 'n1' => 'High', 'n2' => 'Medium'],
            'h3' => ['a' => '8', 'bn' => 'Longsor', 'proses' => 'K6', 'n1' => 'High', 'n2' => 'Low'],
        ];
        // Minggu 0: K13 terdeteksi di DL 1 saja (DL 2 tanpa laporan) → h1 terkontrol (≥1 DL, tanpa masalah) + turun kategori.
        // K6 tidak terdeteksi di mana pun → h3 open risk; DL 2 punya insiden → laten.
        $idx = $b->indeks([['dl' => '1', 'kel' => 'K13', 'w' => '0:1', 'h' => '', 'last' => '2026-11-14']], []);
        $pasangan = ['1' => ['h1', 'h3'], '2' => ['h1', 'h3']];
        $dl = ['1' => ['grp' => 'Pit'], '2' => ['grp' => 'Pit']];
        $berkas = $b->berkas(['1', '2'], $pasangan, $hira, $dl, $idx, []);
        $m = $b->ringkasMgmt(['1', '2'], $pasangan, $hira, $berkas, [], true);

        // [idk, idg, tid, turun, laten]
        $this->assertSame([1, 0, 1, 1, 1], array_slice($m['w'][0]['High'], 10, 5));
        $this->assertSame([0, 0, 2, 0, 1], array_slice($m['w'][1]['High'], 10, 5));

        // Rincian modal: [kel, hid, level, inc, tr, kode minggu]; minggu 0 = K (h1) / T (h3), minggu 1 = T.
        $r = $b->ringkasMgmt(['1', '2'], $pasangan, $hira, $berkas, [], true, true)['rincian'];
        $u = array_column($r['u'], null, 1);
        $this->assertSame(['K13', 'h1', 'High', 0, 1], array_slice($u['h1'], 0, 5));
        $this->assertSame('KT', substr($u['h1'][5], 0, 2));
        $this->assertSame(['K6', 'h3', 'High', 3, 1], array_slice($u['h3'], 0, 5)); // 3 insiden dijumlah antar DL
        $this->assertSame('TT', substr($u['h3'][5], 0, 2));
        $this->assertSame(['Pit', 'K13', 'h1'], array_slice($r['g'][0], 0, 3));
    }

    public function test_rincian_modal_memuat_jenis_gap_dan_volume_per_minggu(): void
    {
        $b = (new DrmBeranda(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI)))
            ->denganGrup(['1' => ['grp' => 'Pit'], '2' => ['grp' => 'Pit']]);
        $hira = ['h1' => ['a' => '7', 'bn' => 'Jalan licin', 'proses' => 'K13', 'n1' => 'High', 'n2' => 'Medium']];
        // DL 1 minggu 0: label belum closed + berulang (bit 2|4|8); OBS 3 laporan (2 tema), OAK 1 (1 butir), 4 Hazard (2 label).
        // DL 2: OBS 2 laporan minggu 0; minggu 1 OAK Stop (bit 2|16) dari 1 OAK.
        $idx = $b->indeks([], [
            ['dl' => '1', 'hid' => 'h1', 'w' => '0:14', 'h' => '', 'v' => '0:3:1:4:11.12:21:31.32'],
            ['dl' => '2', 'hid' => 'h1', 'w' => '0:2,1:18', 'h' => '', 'v' => '0:2:0:0:11::,1:0:1:0::21:'],
        ]);
        $pasangan = ['1' => ['h1'], '2' => ['h1']];
        $dl = ['1' => ['grp' => 'Pit'], '2' => ['grp' => 'Pit']];
        $berkas = $b->berkas(['1', '2'], $pasangan, $hira, $dl, $idx, []);
        $r = $b->ringkasMgmt(['1', '2'], $pasangan, $hira, $berkas, [], true, true)['rincian'];

        // gap hex per minggu: minggu 0 = belum closed (1) + berulang (2) = 3, minggu 1 = OAK Stop (4).
        $this->assertSame('34', substr($r['u'][0][6], 0, 2));
        // vol "wi:obs:oak:ket:item obs:item oak:item label": laporan dijumlah antar DL, item = label BERBEDA
        // (label OBS 11 ada di DL 1 dan DL 2 → tetap 1 item, bukan 2).
        $this->assertSame('0:5:1:4:2:1:2,1:0:1:0:0:1:0', $r['u'][0][7]);
        $this->assertSame($r['u'][0][7], $r['g'][0][8]);
        // Berkas PJA tidak ikut membawa volume.
        $this->assertCount(2, ((array) $berkas['sp'])['1|h1']);
    }

    public function test_sql_beranda_menghasilkan_kolom_volume(): void
    {
        $sql = (new DrmSql(config('peta_risiko_drm'), CarbonImmutable::parse(self::SAMPAI), DrmMaster::dariArray([])->literal(config('peta_risiko_drm'))))->untukSite('LMO');

        $spe = $sql->berandaSpesifik();
        $this->assertStringContainsString("toUInt64(countIf(src = 'obs')), toUInt64(countIf(src = 'oak'))", $spe);
        $this->assertStringContainsString('toUInt64(sum(n)) AS nh', $spe);
        $this->assertStringContainsString("ifNull(c.v, '') AS v", $spe);
    }
}
