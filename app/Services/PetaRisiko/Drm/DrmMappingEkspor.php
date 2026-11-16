<?php

namespace App\Services\PetaRisiko\Drm;

use Carbon\CarbonImmutable;

/**
 * Excel "Mapping Master DRM 2.1" — struktur sama dengan workbook DRM21_Mapping_Master_Overall
 * (README + M01–M16), ditulis ulang setiap sync dari master yang diimpor + hasil ClickHouse:
 * - M01 ditambah status identifikasi SAP per baris (gabungan antar detail lokasi) dan atribut risiko HIRA.
 * - M02/M03 dan M15/M16 dihitung ulang dari regex config; M07/M08 dari aaj_lokasi_dan_pja (id = m_pja).
 * - M06 ditambah id detail lokasi OBDS yang cocok namanya (kosong = tidak ditemukan di OBDS).
 * M10/M13/M14 (kandidat Master A/C/D) tidak dipakai pipeline dan tetap ada di workbook master aslinya.
 * Ditulis ke storage/app/peta-risiko-drm/mapping-bahaya-risiko.xlsx.
 */
class DrmMappingEkspor
{
    public const NAMA_FILE = 'mapping-bahaya-risiko.xlsx';

    private const STATUS = [
        'G' => '! Teridentifikasi – ada gap',
        'K' => '✓ Teridentifikasi & terkontrol',
        'T' => '○ Tanpa laporan (punya jalur identifikasi SAP)',
        'X' => '? Tanpa laporan, tidak punya jalur identifikasi SAP di master',
        '-' => '× Tidak ada detail lokasi baseline',
    ];

    /** M01: [judul, lebar, dibungkus?, kunci]. Kunci berawalan "s." diambil dari baris status. */
    private const KOLOM_M01 = [
        ['id_baris (ri)', 9, false, 'ri'], ['id_aktivitas', 9, false, 'aid'], ['Aktivitas', 30, true, 'aktivitas'],
        ['Sub aktivitas', 30, true, 'sub'], ['Bahaya', 40, true, 'bahaya'], ['Kategori bahaya', 12, false, 'kb'],
        ['Kelompok aktivitas', 26, true, 'kelompok'], ['Bahaya umum (Y/N)', 9, false, 'umum'], ['ID HIRA asli (v31)', 12, false, 'idh'],
        ['Jml grup lokasi', 9, false, 'n_grup'], ['Jml label SAP', 9, false, 'n_label'], ['Jml aktivitas OBS', 9, false, 'n_obs'],
        ['Jml aktivitas OAK', 9, false, 'n_oak'], ['Jml tema checklist', 9, false, 'n_tema'],
        ['Status gabungan (data sampai)', 30, false, 's.status'], ['Detail lokasi baseline', 10, false, 's.dl_n'],
        ['DL terkontrol', 10, false, 's.dl_k'], ['DL ada gap', 10, false, 's.dl_g'], ['DL tanpa laporan', 10, false, 's.dl_t'],
        ['DL tanpa jalur SAP', 10, false, 's.dl_x'], ['DL belum closed', 10, false, 's.dl_open'], ['DL berulang', 10, false, 's.dl_ulang'],
        ['DL OAK stop', 10, false, 's.dl_stop'], ['Temuan belum closed', 10, false, 's.temuan_open'], ['Temuan dalam jendela', 10, false, 's.temuan_jendela'],
        ['Laporan SAP terakhir', 12, false, 's.terakhir'], ['Risiko awal (HIRA)', 11, false, 's.n1'], ['Kekerapan awal', 15, false, 's.k1'],
        ['Konsekuensi awal', 15, false, 's.c1'], ['Risiko residual', 11, false, 's.n2'], ['Kekerapan residual', 15, false, 's.k2'],
        ['Konsekuensi residual', 15, false, 's.c2'], ['Pengendalian yang ada (HIRA)', 45, true, 's.kontrol'],
        ['Label SAP terpetakan dlm rentang data', 45, true, 's.label_a'], ['Teks OBS/OAK terlapor', 40, true, 's.teks_b'],
        ['Insiden terkait 12 bln', 9, false, 's.inc_n'], ['Insiden terakhir', 12, false, 's.inc_tgl'], ['Risiko laten', 8, false, 's.laten'],
    ];

    /**
     * @param  array{baris: list<array<string, mixed>>, dl: list<array<string, mixed>>, pja: array<string, array{nama: string, site: string}>}  $ekspor
     * @param  array<string, mixed>  $cfg
     */
    public function tulis(array $ekspor, DrmMaster $master, CarbonImmutable $sampai, array $cfg, string $tujuan): void
    {
        $d = $master->data;
        $kelompok = $cfg['proses_bisnis'];
        $namaKel = array_map(fn ($k) => $k[0], $kelompok);
        $kel = $master->kelompokBaris($kelompok);
        $tema = $master->temaBaris($cfg['tema_checklist']);
        $baris = $master->barisPerRi();

        $hitung = fn (iterable $daftar): array => array_count_values([...$daftar]);
        $nGrup = $hitung((function () use ($d) {
            foreach ($d['grupBaris'] as $ris) {
                yield from $ris;
            }
        })());
        $nLabel = $hitung($this->ratakan(array_column($d['label'], 'ri')));
        $nObs = $hitung($this->ratakan(array_column($d['obs'], 'ri')));
        $nOak = $hitung($this->ratakan(array_column($d['oak'], 'ri')));
        $nTema = $hitung($this->ratakan(array_values($tema)));
        $status = array_column($ekspor['baris'], null, 'hid');

        $x = new DrmXlsxWriter();
        $this->sheet($x, 'README_Peta_Join', ['PETA MAPPING MASTER DRM 2.1 (HIRA - Lokasi - SAP - OBS - OAK - Checklist)'], $this->readme($master, $sampai, $cfg, $ekspor), [140], [0]);

        $this->sheet($x, 'M01_Baris_HIRA', array_column(self::KOLOM_M01, 0), (function () use ($d, $kel, $namaKel, $nGrup, $nLabel, $nObs, $nOak, $nTema, $status) {
            foreach ($d['baris'] as $b) {
                $s = $status[(string) $b['ri']] ?? [];
                $nilai = [
                    'ri' => $b['ri'], 'aid' => $b['aid'], 'aktivitas' => $b['aktivitas'], 'sub' => $b['sub'], 'bahaya' => $b['bahaya'],
                    'kb' => $b['kb'], 'kelompok' => $namaKel[$kel[$b['ri']]] ?? '', 'umum' => $b['umum'] ? 'Y' : 'N', 'idh' => implode(', ', $b['idh']),
                    'n_grup' => $nGrup[$b['ri']] ?? 0, 'n_label' => $nLabel[$b['ri']] ?? 0, 'n_obs' => $nObs[$b['ri']] ?? 0,
                    'n_oak' => $nOak[$b['ri']] ?? 0, 'n_tema' => $nTema[$b['ri']] ?? 0,
                ];
                yield array_map(function (array $k) use ($nilai, $s) {
                    $kunci = $k[3];
                    if (! str_starts_with($kunci, 's.')) {
                        return $nilai[$kunci];
                    }
                    $v = $s[substr($kunci, 2)] ?? '';

                    return match ($kunci) {
                        's.status' => self::STATUS[$v] ?? $v,
                        's.laten' => $v === true ? 'Ya' : '',
                        default => $v,
                    };
                }, self::KOLOM_M01);
            }
        })(), array_column(self::KOLOM_M01, 1), array_keys(array_filter(array_column(self::KOLOM_M01, 2))));

        $this->sheet($x, 'M02_Kelompok_Aktivitas', ['id_kelompok', 'Kelompok aktivitas', 'Pola regex (prioritas urut dari atas)', 'Jml aktivitas HIRA', 'Jml baris HIRA'],
            $this->m02($kelompok, $kel, $baris), [10, 38, 90, 12, 12], [2]);
        $this->sheet($x, 'M03_Aktivitas_Kelompok', ['id_aktivitas', 'Aktivitas HIRA', 'Kelompok aktivitas', 'Jml baris HIRA'],
            $this->m03($baris, $kel, $namaKel), [10, 50, 38, 12], [1]);
        $this->sheet($x, 'M04_Grup_Lokasi', ['id_grup', 'Grup lokasi (HIRA)', 'Jml baris HIRA', 'Jml detail lokasi (master)', 'Jml detail lokasi cocok OBDS'],
            $this->m04($d, $ekspor['dl']), [9, 30, 12, 14, 14]);
        $this->sheet($x, 'M05_Bridge_Grup_Baris', ['id_grup', 'Grup lokasi', 'id_baris (ri)'], (function () use ($d) {
            foreach ($d['grupBaris'] as $gid => $ris) {
                foreach ($ris as $ri) {
                    yield [$gid, $d['grup'][$gid] ?? '', $ri];
                }
            }
        })(), [9, 30, 10]);
        [$m06, $m07] = $this->m06m07($d, $ekspor['dl'], $ekspor['pja']);
        $this->sheet($x, 'M06_DetailLokasi', ['id_DL', 'Site (SAP)', 'Lokasi (SAP)', 'Detail lokasi (SAP)', 'id_grup', 'Grup lokasi', 'id detail lokasi OBDS', 'Jml PJA', 'PJA'],
            $m06, [8, 12, 34, 44, 8, 26, 16, 8, 70], [3, 8]);
        $this->sheet($x, 'M07_Bridge_DL_PJA', ['id_DL', 'id_PJA (m_pja)'], $m07, [8, 14]);
        $this->sheet($x, 'M08_PJA', ['id_PJA (m_pja)', 'Site', 'Nama PJA', 'Jml detail lokasi'], $this->m08($ekspor['pja'], $m07), [14, 12, 50, 14]);
        $this->sheet($x, 'M09_Label_SAP_Baris', ['Label SAP (ketidaksesuaian / subketidaksesuaian)', 'Ketidaksesuaian', 'Subketidaksesuaian', 'id_baris (ri)', 'Bahaya HIRA', 'Sumber pemetaan'],
            (function () use ($d, $baris) {
                foreach ($d['label'] as $l) {
                    foreach ($l['ri'] as $ri) {
                        yield [$l['k'].' / '.$l['sk'], $l['k'], $l['sk'], $ri, $baris[$ri]['bahaya'] ?? '', $l['sumber']];
                    }
                }
            })(), [60, 34, 40, 10, 40, 30], [0, 2, 4]);
        foreach (['M11_OBS_Aktivitas_Baris' => ['obs', 'Teks aktivitas OBS (persis)'], 'M12_OAK_Aktivitas_Baris' => ['oak', 'Teks aktivitas OAK (persis)']] as $nama => [$kunci, $judul]) {
            $this->sheet($x, $nama, [$judul, 'id_baris (ri)', 'Bahaya HIRA'], (function () use ($d, $kunci, $baris) {
                foreach ($d[$kunci] as $t) {
                    foreach ($t['ri'] as $ri) {
                        yield [$t['teks'], $ri, $baris[$ri]['bahaya'] ?? ''];
                    }
                }
            })(), [60, 10, 45], [0, 2]);
        }
        $this->sheet($x, 'M15_Tema_Checklist', ['id_tema', 'Tema', 'Pola regex teks checklist/OBS', 'Pola regex teks bahaya HIRA', 'Jml baris HIRA cocok'],
            array_map(fn ($id, $t) => [$id, $t[0], $t[1], $t[2], count($tema[$id] ?? [])], array_keys($cfg['tema_checklist']), $cfg['tema_checklist']),
            [8, 24, 70, 60, 12], [2, 3]);
        $this->sheet($x, 'M16_Bridge_Tema_Baris', ['id_tema', 'Tema', 'id_baris (ri)', 'Bahaya HIRA', 'Kelompok aktivitas baris', 'Bahaya umum'],
            (function () use ($tema, $cfg, $baris, $kel, $namaKel) {
                foreach ($tema as $id => $ris) {
                    foreach ($ris as $ri) {
                        yield [$id, $cfg['tema_checklist'][$id][0], $ri, $baris[$ri]['bahaya'] ?? '', $namaKel[$kel[$ri]] ?? '', ($baris[$ri]['umum'] ?? false) ? 'Y' : 'N'];
                    }
                }
            })(), [8, 24, 10, 45, 34, 9], [3]);
        $x->simpan($tujuan);
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @param  array<string, mixed>  $ekspor
     * @return list<list<string>>
     */
    private function readme(DrmMaster $master, CarbonImmutable $sampai, array $cfg, array $ekspor): array
    {
        $d = $master->data;
        $n = array_count_values(array_column($ekspor['baris'], 'status'));
        $baris = [
            'Ditulis '.now('Asia/Makassar')->format('d M Y H:i').' WITA. Data SAP sampai '.$sampai->toDateString()
                .' (dibaca '.$cfg['rentang_hari'].' hari ke belakang; jendela status '.$cfg['jendela_hari'].' hari).',
            'Master diimpor dari '.$d['sumber'].' pada '.substr($d['dibuat'], 0, 10).' (php artisan peta-risiko:impor-master).',
            '',
            'RANTAI JOIN',
            '1. Baris HIRA (M01, kunci id_baris) -> aktivitas (M03) -> kelompok aktivitas (M02, regex atas teks aktivitas).',
            '2. Baris HIRA <-> grup lokasi (M05) <-> detail lokasi SAP (M06, via id_grup) <-> PJA (M07 -> M08).',
            '3. Detail lokasi = site + lokasi + detail lokasi; nama dicocokkan ke OBDS setelah huruf kecil dan spasi dirapatkan.',
            '4. Hazard/Inspeksi: label ketidaksesuaian / subketidaksesuaian (M09) -> id_baris.',
            '5. OBS: teks aktivitas OBS persis (M11) -> id_baris. OAK: "aktivitas / sub aktivitas" OAK persis (M12) -> id_baris; hasil OAK berisi "Stop" = gap OAK Stop.',
            '6. Checklist OAK dan teks OBS -> tema (M15) -> bridge tema-baris (M16); baris diidentifikasi bila kelompok aktivitas checklist/OBS sama dengan kelompok baris, atau baris itu bahaya umum.',
            '7. GERBANG: bukti hanya dihitung bila id_baris ada di baris grup lokasi detail lokasi laporan (irisan isi laporan ∩ lokasi laporan).',
            '8. Satu baris identifikasi = (detail lokasi, id_baris). Gap = belum closed (REJECT dihitung terbuka) | berulang (≥'.$cfg['ambang_berulang'].' laporan) | OAK Stop. BUKAN TEMUAN dan DELETED dibuang.',
            '',
            'SUMBER',
            'Dari workbook master (kurasi): M01, M04, M05, M06, M09, M11, M12.',
            'Dihitung ulang tiap sync dari ClickHouse/config: M02, M03 (regex kelompok), M07, M08 (obds.aaj_lokasi_dan_pja, id = m_pja), M15, M16 (regex tema).',
            'M10/M13/M14 (kandidat Master A/C/D) tidak dipakai pipeline; lihat workbook master aslinya.',
            '',
            'RINGKASAN STATUS M01 (gabungan terburuk antar detail lokasi)',
        ];
        foreach (self::STATUS as $k => $label) {
            $baris[] = sprintf('%s: %s baris', $label, number_format($n[$k] ?? 0, 0, ',', '.'));
        }
        $baris[] = sprintf('Detail lokasi OBDS yang cocok dengan master M06: %s (master: %s).', number_format(count($ekspor['dl']), 0, ',', '.'), number_format(count($d['dl']), 0, ',', '.'));
        $baris[] = '';
        $baris[] = 'CATATAN';
        $baris[] = '"Terkontrol" = terkontrol menurut catatan SAP, bukan bukti kondisi lapangan. Baris tanpa laporan = gap, bukan aman.';
        $baris[] = 'Satu detail lokasi bisa milik 2 PJA: angka antar-PJA tidak boleh dijumlahkan.';
        $baris[] = 'Atribut risiko (kekerapan, konsekuensi, residual, pengendalian) dari obds.mv_hira_master; kosong bila ID HIRA v31 sudah tidak ada di sana.';

        return array_map(fn ($t) => [$t], $baris);
    }

    /**
     * @param  array<string, array{0: string, 1: string, 2: string}>  $kelompok
     * @param  array<int, string>  $kel
     * @param  array<int, array<string, mixed>>  $baris
     * @return list<list<string|int>>
     */
    private function m02(array $kelompok, array $kel, array $baris): array
    {
        $nBaris = array_count_values($kel);
        $akt = [];
        foreach ($baris as $ri => $b) {
            $akt[$kel[$ri]][$b['aid']] = true;
        }
        $hasil = [];
        foreach ($kelompok as $kode => [$nama, , $pola]) {
            $hasil[(int) substr($kode, 1)] = [(int) substr($kode, 1), $nama, $pola === '' ? '(tidak ada pola; sisa)' : $pola, count($akt[$kode] ?? []), $nBaris[$kode] ?? 0];
        }
        ksort($hasil);

        return array_values($hasil);
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     * @param  array<int, string>  $kel
     * @param  array<string, string>  $namaKel
     * @return list<list<string|int>>
     */
    private function m03(array $baris, array $kel, array $namaKel): array
    {
        $hasil = [];
        foreach ($baris as $ri => $b) {
            $hasil[$b['aid']] ??= [$b['aid'], $b['aktivitas'], $namaKel[$kel[$ri]] ?? '', 0];
            $hasil[$b['aid']][3]++;
        }
        ksort($hasil);

        return array_values($hasil);
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  list<array<string, mixed>>  $dlObds
     * @return list<list<string|int>>
     */
    private function m04(array $d, array $dlObds): array
    {
        $nDl = array_count_values(array_column($d['dl'], 'gid'));
        $nCocok = array_count_values(array_column($dlObds, 'grp'));
        $hasil = [];
        foreach ($d['grup'] as $gid => $nama) {
            $hasil[] = [$gid, $nama, count($d['grupBaris'][$gid] ?? []), $nDl[$gid] ?? 0, $nCocok[$nama] ?? 0];
        }

        return $hasil;
    }

    /**
     * M06 (detail lokasi master + id OBDS cocok + PJA) dan M07 (detail lokasi → PJA).
     *
     * @param  array<string, mixed>  $d
     * @param  list<array<string, mixed>>  $dlObds  baris DrmSql::detailLokasi()
     * @param  array<string, array{nama: string, site: string}>  $pja
     * @return array{0: list<list<string|int>>, 1: list<list<int|string>>}
     */
    private function m06m07(array $d, array $dlObds, array $pja): array
    {
        $perKunci = [];
        foreach ($dlObds as $o) {
            $perKunci[DrmMaster::crcDl((string) $o['site'], (string) $o['lokasi'], (string) $o['detlok'])][] = $o;
        }
        $m06 = $m07 = [];
        foreach ($d['dl'] as $x) {
            $o = $perKunci[DrmMaster::crcDl($x['site'], $x['lokasi'], $x['detail'])] ?? [];
            $ids = array_values(array_unique(array_merge(...array_map(fn ($r) => array_map('strval', (array) $r['pja']), $o ?: [['pja' => []]]))));
            foreach ($ids as $p) {
                $m07[] = [$x['id'], $p];
            }
            $m06[] = [
                $x['id'], $x['site'], $x['lokasi'], $x['detail'], $x['gid'], $d['grup'][$x['gid']] ?? '',
                implode(', ', array_column($o, 'dl')), count($ids), implode(' ; ', array_map(fn ($p) => $pja[$p]['nama'] ?? $p, $ids)),
            ];
        }

        return [$m06, $m07];
    }

    /**
     * @param  array<string, array{nama: string, site: string}>  $pja
     * @param  list<list<int|string>>  $m07
     * @return list<list<string|int>>
     */
    private function m08(array $pja, array $m07): array
    {
        $nDl = array_count_values(array_map(fn ($r) => (string) $r[1], $m07));
        $hasil = [];
        foreach ($pja as $id => $p) {
            if (isset($nDl[(string) $id])) {
                $hasil[] = [(string) $id, $p['site'], $p['nama'], $nDl[(string) $id]];
            }
        }
        usort($hasil, fn ($a, $b) => [$a[1], $a[2]] <=> [$b[1], $b[2]]);

        return $hasil;
    }

    /**
     * Tulis sheet; kolom ID (judul "id…" atau "(ri)") ditulis tanpa pemisah ribuan.
     *
     * @param  list<string>  $judul
     * @param  iterable<list<string|int|float|bool|null>>  $baris
     * @param  list<int>  $lebar
     * @param  list<int>  $bungkus
     */
    private function sheet(DrmXlsxWriter $x, string $nama, array $judul, iterable $baris, array $lebar = [], array $bungkus = []): void
    {
        $polos = array_keys(array_filter($judul, fn ($j) => preg_match('/^id[_ ]|\(ri\)|^ID /i', $j) === 1));
        $x->tambahSheet($nama, $judul, $baris, $lebar, $bungkus, $polos);
    }

    /**
     * @param  list<list<int>>  $daftar
     * @return \Generator<int>
     */
    private function ratakan(array $daftar): \Generator
    {
        foreach ($daftar as $l) {
            yield from $l;
        }
    }
}
