<?php

namespace App\Services\PetaRisiko\Drm\Master;

use App\Services\PetaRisiko\Drm\DrmMaster;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Isian master per baris (bisa diedit) ↔ Excel: data unduhan tersaring, pratinjau perubahan dari berkas unggahan,
 * dan penerapan dalam satu transaksi + drm_master_log (aksi 'unggah', siapa, sebelum → sesudah, nama berkas).
 * Hanya mode kolom (label di item_sumber_* drm_master_baris).
 */
final class DrmMasterBarisUnggah
{
    /** Kolom yang bisa diubah lewat Excel (keputusan user 20 Nov 2026); risiko awal, departemen, grup lokasi = info. */
    public const SKALAR = ['aktivitas', 'sub', 'bahaya'];

    public const BANYAK = ['OBS', 'OAK', 'KET'];

    private const PANJANG = ['aktivitas' => 500, 'sub' => 500, 'bahaya' => 1000];

    public function __construct(private readonly DrmMasterDb $db)
    {
    }

    /**
     * Label yang sudah ada di master per jenis (sumber dropdown Excel, sama dengan saran isian di halaman master),
     * unik menurut kunci pencocokan sync, terurut.
     *
     * @return array<string, list<string>>  OBS / OAK / KET → label
     */
    public function daftarLabel(): array
    {
        $hasil = [];
        foreach (self::BANYAK as $j) {
            $unik = [];
            foreach (DB::table('drm_master_baris')->where('aktif', true)->pluck(DrmMasterDb::KOLOM_LABEL[$j]) as $isi) {
                foreach (DrmMasterDb::pisahLabel($isi) as $l) {
                    $unik[$this->kunci($j, $l)] ??= $l;
                }
            }
            $daftar = array_values($unik);
            sort($daftar, SORT_NATURAL | SORT_FLAG_CASE);
            $hasil[$j] = $daftar;
        }

        return $hasil;
    }

    /**
     * Site tempat tiap baris dihitung, aturannya sama dengan sync: grup lokasi baris → detail lokasi master (drm_master_dl)
     * → site; kecuali departemen HIRA di config site_per_departemen (mis. Port and Barging Operation → MARINE) yang
     * hanya masuk site itu. Departemen = dokumen HIRA yang teks bahayanya cocok (sama dengan sumber atribut risiko).
     *
     * @param  array<int, list<array<string, mixed>>>  $hira  ri → dokumen HIRA (dept, cocok)
     * @return array<int, list<string>>  ri → site terurut
     */
    public function sitePerBaris(array $hira): array
    {
        $siteGrup = [];
        foreach (DB::table('drm_master_dl')->where('aktif', true)->select('gid', 'site')->distinct()->get() as $d) {
            $siteGrup[(int) $d->gid][(string) $d->site] = true;
        }
        $hasil = [];
        foreach (DB::table('drm_master_grup_baris')->get(['gid', 'ri']) as $g) {
            $hasil[(int) $g->ri] = ($hasil[(int) $g->ri] ?? []) + ($siteGrup[(int) $g->gid] ?? []);
        }
        $rute = [];
        foreach ((array) config('peta_risiko_drm.site_per_departemen', []) as $site => $daftar) {
            foreach ((array) $daftar as $dept) {
                $rute[mb_strtolower(trim((string) $dept))] = strtoupper(trim((string) $site));
            }
        }
        foreach ($rute === [] ? [] : $hira as $ri => $dok) {
            $cocok = array_values(array_filter($dok, fn ($d) => ! empty($d['cocok'])));
            $site = $rute[mb_strtolower(trim((string) ($cocok[0]['dept'] ?? '')))] ?? null;
            if ($site !== null) {
                $hasil[(int) $ri] = [$site => true];
            }
        }

        return array_map(function (array $s) {
            $s = array_keys($s);
            sort($s);

            return array_map('strval', $s);
        }, $hasil);
    }

    /**
     * "Perusahaan" tempat tiap baris berlaku, DIRINCI PER DETAIL LOKASI (keputusan user 24 Nov 2026 — jangan
     * digabung rata se-grup: grup lokasi (M05) bisa mencakup banyak detail lokasi dengan PJA/perusahaan yang
     * beda-beda, menggabungkannya jadi satu daftar membuat asal satu nama perusahaan tidak bisa ditelusuri /
     * tampak nyasar dari lokasi lain di grup yang sama yang sebenarnya tidak terkait). Nama perusahaan dari
     * PERSONIL PJA (drm_master_lokasi_pja.perusahaan, diisi dari ClickHouse personil lewat
     * `peta-risiko:master-db --isi` — lihat DrmSql::perusahaanPerPja), BUKAN nama PJA itu sendiri.
     *
     * @return array<int, list<array{site: string, lokasi: string, perusahaan: list<string>}>>  ri => rincian per (site, lokasi)
     */
    public function perusahaanPerBaris(): array
    {
        // (site, lokasi) → perusahaan unik di sana (lewat PJA-nya, bisa >1 PJA per lokasi).
        $pjaPerLokasi = [];
        foreach (DB::table('drm_master_lokasi_pja')->where('aktif', true)->select('site', 'lokasi', 'perusahaan')->distinct()->get() as $p) {
            $site = trim((string) $p->site);
            $lokasi = trim((string) $p->lokasi);
            $kunci = mb_strtolower($site).'|'.mb_strtolower($lokasi);
            $pjaPerLokasi[$kunci] ??= ['site' => $site, 'lokasi' => $lokasi, 'perusahaan' => []];
            foreach (DrmMasterDb::pisahLabel($p->perusahaan ?? '') as $nama) {
                $pjaPerLokasi[$kunci]['perusahaan'][$nama] = true;
            }
        }
        // Grup → kunci (site, lokasi) yang detail lokasinya aktif DAN punya data perusahaan.
        $lokasiPerGrup = [];
        foreach (DB::table('drm_master_dl')->where('aktif', true)->select('gid', 'site', 'lokasi')->distinct()->get() as $d) {
            $kunci = mb_strtolower(trim((string) $d->site)).'|'.mb_strtolower(trim((string) $d->lokasi));
            if (isset($pjaPerLokasi[$kunci])) {
                $lokasiPerGrup[(int) $d->gid][$kunci] = true;
            }
        }
        $hasil = [];
        foreach (DB::table('drm_master_grup_baris')->get(['gid', 'ri']) as $g) {
            $hasil[(int) $g->ri] = ($hasil[(int) $g->ri] ?? []) + ($lokasiPerGrup[(int) $g->gid] ?? []);
        }

        return array_map(function (array $kunciSet) use ($pjaPerLokasi) {
            $out = [];
            foreach (array_keys($kunciSet) as $kunci) {
                $nama = array_keys($pjaPerLokasi[$kunci]['perusahaan']);
                sort($nama, SORT_NATURAL | SORT_FLAG_CASE);
                $out[] = ['site' => $pjaPerLokasi[$kunci]['site'], 'lokasi' => $pjaPerLokasi[$kunci]['lokasi'], 'perusahaan' => $nama];
            }
            usort($out, fn ($a, $b) => [$a['site'], $a['lokasi']] <=> [$b['site'], $b['lokasi']]);

            return $out;
        }, $hasil);
    }

    /** @return array<int, string> gid => nama grup lokasi aktif */
    public function grupAktif(): array
    {
        return DB::table('drm_master_grup')->where('aktif', true)->orderBy('nama')->pluck('nama', 'gid')->map(fn ($n) => (string) $n)->all();
    }

    /**
     * Isian semua baris aktif saat ini: ri => [aktivitas, sub, bahaya, kb, grup[], OBS[], OAK[], KET[], aid, idh].
     *
     * @return array<int, array<string, mixed>>
     */
    public function isianDb(?array $ri = null): array
    {
        if (! DrmMasterDb::labelDiBaris()) {
            throw new RuntimeException('Master belum memakai kolom label per baris (jalankan peta-risiko:master-db --isi-label-baris).');
        }
        $grupNama = DB::table('drm_master_grup')->pluck('nama', 'gid')->all();
        $grupPerRi = [];
        $q = DB::table('drm_master_grup_baris');
        if ($ri !== null) {
            $q->whereIn('ri', $ri);
        }
        foreach ($q->get(['gid', 'ri']) as $g) {
            if (isset($grupNama[$g->gid])) {
                $grupPerRi[(int) $g->ri][] = (string) $grupNama[$g->gid];
            }
        }
        $q = DB::table('drm_master_baris')->where('aktif', true);
        if ($ri !== null) {
            $q->whereIn('ri', $ri);
        }
        $hasil = [];
        foreach ($q->orderBy('aktivitas')->orderBy('sub')->orderBy('ri')->get() as $b) {
            $g = $grupPerRi[(int) $b->ri] ?? [];
            sort($g);
            $hasil[(int) $b->ri] = [
                'ri' => (int) $b->ri, 'aid' => (int) ($b->aid ?? 0), 'idh' => (string) ($b->idh ?? ''),
                'aktivitas' => (string) $b->aktivitas, 'sub' => (string) ($b->sub ?? ''), 'bahaya' => (string) $b->bahaya,
                'kb' => (string) ($b->kb ?? ''), 'grup' => array_values(array_unique($g)),
                'OBS' => DrmMasterDb::pisahLabel($b->{DrmMasterDb::KOLOM_LABEL['OBS']} ?? ''),
                'OAK' => DrmMasterDb::pisahLabel($b->{DrmMasterDb::KOLOM_LABEL['OAK']} ?? ''),
                'KET' => DrmMasterDb::pisahLabel($b->{DrmMasterDb::KOLOM_LABEL['KET']} ?? ''),
            ];
        }

        return $hasil;
    }

    /**
     * Baris untuk berkas unduhan, disaring sama dengan halaman (kb, label kosong, departemen, kata cari).
     *
     * @param  array{kb?: string, kosong?: string, dep?: string, q?: string}  $filter
     * @param  array<int, list<array<string, mixed>>>  $hira  ri → dokumen HIRA (DrmHiraSumber::untukBaris), boleh kosong
     * @return list<array<string, mixed>>
     */
    public function ekspor(array $filter, array $hira): array
    {
        $kelompok = DB::table('drm_master_aktivitas as a')->leftJoin('drm_master_kelompok as k', 'k.kode', '=', 'a.kelompok')
            ->pluck('k.nama', 'a.aid')->all();
        $kata = array_filter(preg_split('/\s+/', mb_strtolower(trim((string) ($filter['q'] ?? '')))) ?: []);
        $sitePer = $this->sitePerBaris($hira);
        $pjaPer = $this->perusahaanPerBaris();
        $hasil = [];
        foreach ($this->isianDb() as $ri => $b) {
            $deptHira = array_values(array_unique(array_filter(array_column($hira[$ri] ?? [], 'dept'))));
            $site = $sitePer[$ri] ?? [];
            $pja = $pjaPer[$ri] ?? [];
            if (($filter['kb'] ?? '') !== '' && $b['kb'] !== $filter['kb']) {
                continue;
            }
            if (($filter['site'] ?? '') !== '' && ! in_array($filter['site'], $site, true)) {
                continue;
            }
            if (($filter['pja'] ?? '') !== '' && ! array_filter($pja, fn ($p) => in_array($filter['pja'], $p['perusahaan'], true))) {
                continue;
            }
            $k = (string) ($filter['kosong'] ?? '');
            if (($k === 'SEMUA' && ($b['OBS'] || $b['OAK'] || $b['KET'])) || (in_array($k, ['OBS', 'OAK', 'KET'], true) && $b[$k])) {
                continue;
            }
            if (($filter['dep'] ?? '') !== '' && ! in_array($filter['dep'], $deptHira, true)) {
                continue;
            }
            $teks = mb_strtolower(implode(' ', [$ri, $b['aktivitas'], $b['sub'], $b['bahaya'], $b['kb'], implode(' ', $deptHira), $b['idh'],
                implode(' ', $b['grup']), implode(' ', $b['OBS']), implode(' ', $b['OAK']), implode(' ', $b['KET'])]));
            foreach ($kata as $w) {
                if (! str_contains($teks, $w)) {
                    continue 2;
                }
            }
            $teksPja = implode("\n", array_map(fn ($p) => $p['site'].' · '.$p['lokasi'].': '.implode(', ', $p['perusahaan']), $pja));
            $hasil[] = [...$b, 'dept_hira' => implode("\n", $deptHira), 'site' => implode("\n", $site), 'pja' => $teksPja,
                'kelompok' => (string) ($kelompok[$b['aid']] ?? ''), 'versi' => DrmMasterBarisExcel::versi($b)];
        }

        return $hasil;
    }

    /**
     * Bandingkan isi berkas dengan database. Tidak menyimpan apa pun.
     *
     * @param  list<array<string, mixed>>  $berkas  hasil DrmMasterBarisExcel::baca
     * @return array{ubah: list<array<string, mixed>>, galat: list<array<string, mixed>>, konflik: list<array<string, mixed>>, tetap: int, total: int}
     */
    public function pratinjau(array $berkas): array
    {
        [$rows, $galatLabel] = self::gabungLabel($berkas);
        $ri = array_values(array_filter(array_map(fn ($r) => ctype_digit((string) ($r['ri'] ?? '')) ? (int) $r['ri'] : 0, $rows)));
        $db = $this->isianDb($ri === [] ? [0] : $ri);
        $hasil = ['ubah' => [], 'galat' => $galatLabel[0] ?? [], 'konflik' => [], 'tetap' => 0, 'total' => count($rows)];
        $sudah = [];

        foreach ($rows as $r) {
            $id = ctype_digit((string) ($r['ri'] ?? '')) ? (int) $r['ri'] : 0;
            $label = ['baris' => (int) $r['baris'], 'ri' => $id];
            if ($id === 0 || ! isset($db[$id])) {
                $hasil['galat'][] = [...$label, 'pesan' => 'ID baris tidak dikenal (baris baru tidak bisa ditambah lewat Excel).'];

                continue;
            }
            if (isset($sudah[$id])) {
                $hasil['galat'][] = [...$label, 'pesan' => 'ID baris ini muncul lebih dari sekali di berkas.'];

                continue;
            }
            $sudah[$id] = true;
            $lama = $db[$id];
            $label['aktivitas'] = $lama['aktivitas'];
            $label['bahaya'] = $lama['bahaya'];
            if (isset($galatLabel[$id])) {
                $hasil['galat'][] = [...$label, 'pesan' => implode(' ', array_column($galatLabel[$id], 'pesan'))];

                continue;
            }
            if (($r['versi'] ?? '') !== '' && $r['versi'] !== DrmMasterBarisExcel::versi($lama)) {
                $hasil['konflik'][] = [...$label, 'pesan' => 'Baris sudah diubah orang lain setelah berkas diunduh. Unduh ulang lalu edit lagi.'];

                continue;
            }

            [$baru, $galat, $peringatan] = $this->isianBaru($r, $lama);
            if ($galat !== []) {
                $hasil['galat'][] = [...$label, 'pesan' => implode(' ', $galat)];

                continue;
            }
            $ubah = $this->beda($lama, $baru);
            if ($ubah === []) {
                $hasil['tetap']++;

                continue;
            }
            $hasil['ubah'][] = [...$label, 'ubah' => $ubah, 'peringatan' => array_values(array_intersect_key($peringatan, $ubah)),
                'baru' => $baru, 'versiDb' => DrmMasterBarisExcel::versi($lama)];
        }

        return $hasil;
    }

    /**
     * Gabungkan sheet Master baris dengan sheet Label: ID yang muncul di sheet Label mendapat isian OBS / OAK / KET
     * persis dari sheet itu (teks satu label per baris); ID yang hanya ada di sheet Label ikut diproses (tanpa cek versi).
     *
     * @param  array{baris: list<array<string, mixed>>, label?: array<int, array<string, mixed>>, galatLabel?: list<array<string, mixed>>}  $berkas
     * @return array{0: list<array<string, mixed>>, 1: array<int, list<array<string, mixed>>>}  [baris, galat label per ri (0 = tanpa ID)]
     */
    public static function gabungLabel(array $berkas): array
    {
        $label = $berkas['label'] ?? [];
        $rows = [];
        $ada = [];
        foreach ($berkas['baris'] ?? [] as $r) {
            $id = ctype_digit((string) ($r['ri'] ?? '')) ? (int) $r['ri'] : 0;
            if (isset($label[$id])) {
                foreach (self::BANYAK as $j) {
                    $r[$j] = implode("\n", $label[$id][$j]);
                }
            }
            $ada[$id] = true;
            $rows[] = $r;
        }
        foreach ($label as $id => $l) {
            if (! isset($ada[$id])) {
                $rows[] = ['baris' => (int) $l['baris'], 'ri' => (string) $id, 'OBS' => implode("\n", $l['OBS']), 'OAK' => implode("\n", $l['OAK']),
                    'KET' => implode("\n", $l['KET'])];
            }
        }
        $galat = [];
        foreach ($berkas['galatLabel'] ?? [] as $g) {
            $galat[(int) $g['ri']][] = $g;
        }

        return [$rows, $galat];
    }

    /**
     * Terapkan hasil pratinjau. Baris yang berubah di database sejak pratinjau (versi beda) dilewati.
     *
     * @return array{diubah: int, dilewati: list<int>}
     */
    public function terapkan(array $pratinjau, string $namaBerkas): array
    {
        $ri = array_column($pratinjau['ubah'], 'ri');
        if ($ri === []) {
            return ['diubah' => 0, 'dilewati' => []];
        }
        $user = Auth::user()?->getAuthIdentifier();

        return DB::transaction(function () use ($pratinjau, $ri, $namaBerkas, $user) {
            $db = $this->isianDb($ri);
            $diubah = 0;
            $dilewati = [];
            foreach ($pratinjau['ubah'] as $u) {
                $lama = $db[$u['ri']] ?? null;
                if ($lama === null || DrmMasterBarisExcel::versi($lama) !== $u['versiDb']) {
                    $dilewati[] = (int) $u['ri'];

                    continue;
                }
                $this->simpanBaris((int) $u['ri'], $u['baru'], $u['ubah'], $user);
                $sebelum = $sesudah = [];
                foreach ($u['ubah'] as $k => $_) {
                    $sebelum[$k] = is_array($lama[$k]) ? implode("\n", $lama[$k]) : $lama[$k];
                    $sesudah[$k] = is_array($u['baru'][$k]) ? implode("\n", $u['baru'][$k]) : $u['baru'][$k];
                }
                $this->db->catat('drm_master_baris', (string) $u['ri'], 'unggah', $sebelum, $sesudah + ['berkas' => $namaBerkas]);
                $diubah++;
            }
            $this->db->catat('drm_master_baris', mb_substr('berkas: '.$namaBerkas, 0, 100), 'unggah', null, [
                'berkas' => $namaBerkas, 'baris diubah' => $diubah, 'dilewati (berubah sejak pratinjau)' => count($dilewati),
                'tidak berubah' => (int) $pratinjau['tetap'], 'galat' => count($pratinjau['galat']), 'konflik' => count($pratinjau['konflik']),
            ]);

            return ['diubah' => $diubah, 'dilewati' => $dilewati];
        });
    }

    /**
     * Isian dari satu baris berkas, divalidasi. Kolom yang tidak ada di berkas = tidak diubah.
     *
     * @return array{0: array<string, mixed>, 1: list<string>, 2: array<string, string>}
     */
    private function isianBaru(array $r, array $lama): array
    {
        $baru = $lama;
        $galat = [];
        foreach (self::SKALAR as $k) {
            if (! array_key_exists($k, $r)) {
                continue;
            }
            // Satu sheet: nilai diulang di setiap baris label ID yang sama → daftar nilai berbeda. Nilai yang sama dengan
            // database diabaikan; tepat satu nilai lain = perubahan; lebih dari satu = galat (tidak jelas mana yang benar).
            $nilai = array_values(array_unique(array_map(fn ($x) => trim(preg_replace('/\s+/u', ' ', (string) $x)), (array) $r[$k])));
            // Baris label baru biasanya hanya diisi ID · Jenis · Label: sel kosong diabaikan bila baris lain ID ini berisi.
            if (count($nilai) > 1) {
                $nilai = array_values(array_filter($nilai, fn ($x) => $x !== ''));
            }
            $lain = array_values(array_filter($nilai, fn ($x) => $x !== $lama[$k]));
            if (count($lain) > 1) {
                $galat[] = ucfirst($k).' diisi berbeda di beberapa baris label ID ini ("'.implode('" / "', $lain).'"); samakan.';

                continue;
            }
            $v = $lain[0] ?? $lama[$k];
            if (in_array($k, ['aktivitas', 'bahaya'], true) && $v === '') {
                $galat[] = ucfirst($k).' tidak boleh kosong.';
            }
            if (isset(self::PANJANG[$k]) && mb_strlen($v) > self::PANJANG[$k]) {
                $galat[] = ucfirst($k).' maksimal '.self::PANJANG[$k].' karakter.';
            }
            $baru[$k] = $v;
        }
        foreach (['OBS', 'OAK', 'KET'] as $j) {
            if (! array_key_exists($j, $r)) {
                continue;
            }
            $baru[$j] = DrmMasterDb::pisahLabel((string) $r[$j]);
            foreach ($baru[$j] as $l) {
                if ($j === 'KET' && in_array('', DrmMasterDb::pisahKet($l), true)) {
                    $galat[] = 'Ketidaksesuaian "'.$l.'" harus berformat "ketidaksesuaian / sub ketidaksesuaian".';
                }
                if (mb_strlen($l) > 500) {
                    $galat[] = 'Label '.$j.' maksimal 500 karakter.';
                }
            }
        }
        $peringatan = [
            'bahaya' => 'Teks bahaya diubah: nilai risiko HIRA (kekerapan × konsekuensi) bisa tidak tercocok lagi saat sync.',
            'aktivitas' => 'Aktivitas diubah: kelompok aktivitas tetap mengikuti ID aktivitas baris ini.',
        ];

        return [$baru, $galat, $peringatan];
    }

    /** @return array<string, array<string, mixed>> kolom berubah → {dari, ke} (skalar) atau {tambah, hapus} (banyak) */
    private function beda(array $lama, array $baru): array
    {
        $ubah = [];
        foreach (self::SKALAR as $k) {
            if ($lama[$k] !== $baru[$k]) {
                $ubah[$k] = ['dari' => $lama[$k], 'ke' => $baru[$k]];
            }
        }
        foreach (self::BANYAK as $k) {
            $kunci = fn (string $v) => $this->kunci($k, $v);
            $a = array_combine(array_map($kunci, $lama[$k]), $lama[$k]) ?: [];
            $b = array_combine(array_map($kunci, $baru[$k]), $baru[$k]) ?: [];
            $tambah = array_values(array_diff_key($b, $a));
            $hapus = array_values(array_diff_key($a, $b));
            if ($tambah !== [] || $hapus !== []) {
                $ubah[$k] = ['tambah' => $tambah, 'hapus' => $hapus];
            }
        }

        return $ubah;
    }

    /** Kunci pencocokan sama dengan sync: OBS/OAK normTeks, Ketidaksesuaian norm "k / sk", grup huruf kecil. */
    private function kunci(string $jenis, string $v): string
    {
        return match ($jenis) {
            'KET' => (fn ($p) => DrmMaster::norm($p[0]).' / '.DrmMaster::norm($p[1]))(DrmMasterDb::pisahKet($v)),
            'OBS', 'OAK' => DrmMaster::normTeks($v),
            default => mb_strtolower(trim($v)),
        };
    }

    private function simpanBaris(int $ri, array $baru, array $ubah, mixed $user): void
    {
        $kolom = [];
        foreach (self::SKALAR as $k) {
            if (isset($ubah[$k])) {
                $kolom[$k] = $baru[$k];
            }
        }
        foreach (self::BANYAK as $j) {
            if (isset($ubah[$j])) {
                $kolom[DrmMasterDb::KOLOM_LABEL[$j]] = implode("\n", $baru[$j]);
            }
        }
        if ($kolom !== []) {
            DB::table('drm_master_baris')->where('ri', $ri)->update($kolom + ['diubah_oleh' => $user, 'updated_at' => now()]);
        }
    }
}
