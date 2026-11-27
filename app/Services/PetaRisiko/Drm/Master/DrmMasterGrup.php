<?php

declare(strict_types=1);

namespace App\Services\PetaRisiko\Drm\Master;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Master "Grup lokasi & aktivitas": M05 (grup lokasi → baris HIRA, drm_master_grup_baris) dilihat per aktivitas,
 * plus M02/M03 (aktivitas → kelompok, drm_master_aktivitas). Semua perubahan dalam transaksi dan dicatat di
 * drm_master_log; berlaku pada peta-risiko:sync berikutnya.
 */
final class DrmMasterGrup
{
    public function __construct(private readonly DrmMasterDb $db)
    {
    }

    /** @return list<array{gid: int, nama: string, aktif: bool, baris: int, aktivitas: int, dl: int}> */
    public function daftarGrup(): array
    {
        $baris = DB::table('drm_master_grup_baris')->selectRaw('gid, count(*) n')->groupBy('gid')->pluck('n', 'gid');
        $akt = DB::table('drm_master_grup_baris as g')->join('drm_master_baris as b', 'b.ri', '=', 'g.ri')
            ->selectRaw('g.gid, count(distinct b.aid) n')->groupBy('g.gid')->pluck('n', 'gid');
        $dl = DB::table('drm_master_dl')->where('aktif', true)->selectRaw('gid, count(*) n')->groupBy('gid')->pluck('n', 'gid');

        return DB::table('drm_master_grup')->orderBy('nama')->get()->map(fn ($g) => [
            'gid' => (int) $g->gid, 'nama' => (string) $g->nama, 'aktif' => (bool) $g->aktif,
            'baris' => (int) ($baris[$g->gid] ?? 0), 'aktivitas' => (int) ($akt[$g->gid] ?? 0), 'dl' => (int) ($dl[$g->gid] ?? 0),
        ])->all();
    }

    /**
     * Aktivitas yang punya baris di grup ini: baris di grup, total baris aktivitas, dan grup lain tempat aktivitas itu ada.
     *
     * @return list<array<string, mixed>>
     */
    public function aktivitasGrup(int $gid): array
    {
        $baris = DB::table('drm_master_grup_baris as g')->join('drm_master_baris as b', 'b.ri', '=', 'g.ri')
            ->where('g.gid', $gid)->orderBy('b.sub')->orderBy('b.bahaya')
            ->get(['b.ri', 'b.aid', 'b.sub', 'b.bahaya', 'b.kb', 'b.aktif']);
        if ($baris->isEmpty()) {
            return [];
        }
        $aids = $baris->pluck('aid')->unique()->values()->all();
        $akt = DB::table('drm_master_aktivitas')->whereIn('aid', $aids)->get()->keyBy('aid');
        $total = DB::table('drm_master_baris')->whereIn('aid', $aids)->selectRaw('aid, count(*) n')->groupBy('aid')->pluck('n', 'aid');
        $namaGrup = DB::table('drm_master_grup')->pluck('nama', 'gid');
        $lain = [];
        foreach (DB::table('drm_master_grup_baris as g')->join('drm_master_baris as b', 'b.ri', '=', 'g.ri')
            ->whereIn('b.aid', $aids)->where('g.gid', '!=', $gid)->selectRaw('b.aid, g.gid, count(*) n')->groupBy('b.aid', 'g.gid')->get() as $r) {
            $lain[(int) $r->aid][] = ['gid' => (int) $r->gid, 'nama' => (string) ($namaGrup[$r->gid] ?? $r->gid), 'n' => (int) $r->n];
        }

        $hasil = [];
        foreach ($baris->groupBy('aid') as $aid => $rows) {
            $a = $akt[$aid] ?? null;
            $hasil[] = [
                'aid' => (int) $aid,
                'aktivitas' => (string) ($a->aktivitas ?? '(aktivitas '.$aid.')'),
                'kelompok' => (string) ($a->kelompok ?? 'K0'),
                'total' => (int) ($total[$aid] ?? $rows->count()),
                'lain' => $lain[(int) $aid] ?? [],
                'baris' => $rows->map(fn ($r) => ['ri' => (int) $r->ri, 'sub' => (string) $r->sub, 'bahaya' => (string) $r->bahaya, 'kb' => (string) $r->kb, 'aktif' => (bool) $r->aktif])->values()->all(),
            ];
        }
        usort($hasil, fn ($x, $y) => strcmp($x['kelompok'], $y['kelompok']) ?: strcmp($x['aktivitas'], $y['aktivitas']));

        return $hasil;
    }

    /**
     * Aktivitas yang barisnya belum (semua) ada di grup ini — pilihan "Tambah aktivitas".
     *
     * @return list<array{aid: int, aktivitas: string, kelompok: string, sisa: int}>
     */
    public function aktivitasLuar(int $gid): array
    {
        $diGrup = DB::table('drm_master_grup_baris')->where('gid', $gid)->select('ri');

        return DB::table('drm_master_baris as b')->join('drm_master_aktivitas as a', 'a.aid', '=', 'b.aid')
            ->whereNotIn('b.ri', $diGrup)->selectRaw('b.aid, a.aktivitas, a.kelompok, count(*) sisa')
            ->groupBy('b.aid', 'a.aktivitas', 'a.kelompok')->orderBy('a.aktivitas')->get()
            ->map(fn ($r) => ['aid' => (int) $r->aid, 'aktivitas' => (string) $r->aktivitas, 'kelompok' => (string) $r->kelompok, 'sisa' => (int) $r->sisa])->all();
    }

    /** @return list<array{gid: int, site: string, lokasi: string, detail: string}> */
    public function detailLokasi(int $gid): array
    {
        return DB::table('drm_master_dl')->where('gid', $gid)->where('aktif', true)->orderBy('site')->orderBy('lokasi')->orderBy('detail')
            ->get(['site', 'lokasi', 'detail'])->map(fn ($r) => ['site' => (string) $r->site, 'lokasi' => (string) $r->lokasi, 'detail' => (string) $r->detail])->all();
    }

    /** Detail lokasi aktif semua grup sekaligus (satu query, bukan N+1). @return array<int, list<array{site: string, lokasi: string, detail: string}>> */
    public function semuaDetailLokasi(): array
    {
        $hasil = [];
        foreach (DB::table('drm_master_dl')->where('aktif', true)->orderBy('site')->orderBy('lokasi')->orderBy('detail')->get(['gid', 'site', 'lokasi', 'detail']) as $d) {
            $hasil[(int) $d->gid][] = ['site' => (string) $d->site, 'lokasi' => (string) $d->lokasi, 'detail' => (string) $d->detail];
        }

        return $hasil;
    }

    /**
     * Semua baris HIRA dengan grup lokasi tempatnya berlaku (M05) — tampilan flat satu baris = satu <tr>, sama
     * konsep dengan master per baris (drm_master_baris + chip label OBS/OAK/KET): grup lokasi di sini jadi chip
     * yang bisa ditambah/dikeluarkan per baris, bukan navigasi "pilih 1 grup dulu".
     *
     * @return list<array{ri: int, aid: int, a: string, kelompok: string, s: string, b: string, kb: string, aktif: bool, grup: list<array{gid: int, nama: string}>}>
     */
    public function semuaBaris(): array
    {
        $baris = DB::table('drm_master_baris as b')->join('drm_master_aktivitas as a', 'a.aid', '=', 'b.aid')
            ->orderBy('b.aktivitas')->orderBy('b.sub')->orderBy('b.bahaya')
            ->get(['b.ri', 'b.aid', 'b.aktivitas', 'b.sub', 'b.bahaya', 'b.kb', 'b.aktif', 'a.kelompok']);
        $namaGrup = DB::table('drm_master_grup')->pluck('nama', 'gid');
        $grupPerRi = [];
        foreach (DB::table('drm_master_grup_baris')->orderBy('gid')->get(['ri', 'gid']) as $g) {
            $grupPerRi[(int) $g->ri][] = ['gid' => (int) $g->gid, 'nama' => (string) ($namaGrup[$g->gid] ?? ('#'.$g->gid))];
        }

        return $baris->map(fn ($b) => [
            'ri' => (int) $b->ri, 'aid' => (int) $b->aid, 'a' => (string) $b->aktivitas, 'kelompok' => (string) ($b->kelompok ?: 'K0'),
            's' => (string) $b->sub, 'b' => (string) $b->bahaya, 'kb' => (string) $b->kb, 'aktif' => (bool) $b->aktif,
            'grup' => $grupPerRi[(int) $b->ri] ?? [],
        ])->values()->all();
    }

    /**
     * Detail lokasi aktif (lewat grup M05) per baris HIRA, plus perusahaan yang terhubung di site+lokasi itu
     * (lewat PJA, drm_master_lokasi_pja — sama rantai dengan DrmMasterBarisUnggah::perusahaanPerBaris, tapi di
     * sini DIRINCI PER GRUP juga karena halaman ini mengedit keanggotaan grup per baris), plus PIC Perusahaan
     * RESMI per detlok (drm_master_lokasi_pic — dari PIC_Detail_Lokasi ClickHouse sejak migrasi 2026_11_27_100000,
     * BUKAN personil PJA/Mitra). Batch (4 query), bukan N+1 — sumber kolom info Site/Perusahaan/PIC di tabel
     * dan unduhan Excel.
     *
     * @return array<int, list<array{gid: int, grup: string, site: string, lokasi: string, detail: string, perusahaan: list<string>, pic: list<string>}>>
     */
    private function lokasiPerRi(): array
    {
        $namaGrup = DB::table('drm_master_grup')->pluck('nama', 'gid');
        $grupPerRi = [];
        foreach (DB::table('drm_master_grup_baris')->get(['ri', 'gid']) as $g) {
            $grupPerRi[(int) $g->ri][(int) $g->gid] = true;
        }
        $gids = collect($grupPerRi)->map(fn ($g) => array_keys($g))->flatten()->unique()->values()->all();
        $dlPerGrup = [];
        foreach (DB::table('drm_master_dl')->whereIn('gid', $gids)->where('aktif', true)->orderBy('site')->orderBy('lokasi')->orderBy('detail')->get() as $d) {
            $dlPerGrup[(int) $d->gid][] = $d;
        }
        $sites = [];
        foreach ($dlPerGrup as $list) {
            foreach ($list as $d) {
                $sites[(string) $d->site] = true;
            }
        }
        // Nama PERUSAHAAN (bukan nama PJA/nama_pja — itu judul dokumen, bukan perusahaan; lihat migrasi
        // 2026_11_24_100000_add_perusahaan_to_drm_master_lokasi_pja_table), pola sama dengan
        // DrmMasterBarisUnggah::perusahaanPerBaris().
        $pjaPerLokasi = [];
        if ($sites !== []) {
            foreach (DB::table('drm_master_lokasi_pja')->where('aktif', true)->whereIn('site', array_keys($sites))->get(['site', 'lokasi', 'perusahaan']) as $p) {
                $k = mb_strtolower(trim($p->site).'|'.trim($p->lokasi));
                foreach (DrmMasterDb::pisahLabel($p->perusahaan ?? '') as $nama) {
                    $pjaPerLokasi[$k][$nama] = true;
                }
            }
        }
        // PIC Perusahaan RESMI per detlok (drm_master_lokasi_pic, dari PIC_Detail_Lokasi ClickHouse sejak 27 Nov
        // 2026 — beda sumber dengan $pjaPerLokasi yang dari personil).
        $picPerDetlok = [];
        if ($sites !== []) {
            foreach (DB::table('drm_master_lokasi_pic')->where('aktif', true)->whereIn('site', array_keys($sites))->get(['site', 'lokasi', 'detail', 'pic_perusahaan']) as $p) {
                $k = mb_strtolower(trim($p->site).'|'.trim($p->lokasi).'|'.trim($p->detail));
                $picPerDetlok[$k] = DrmMasterDb::pisahLabel($p->pic_perusahaan ?? '');
            }
        }
        $hasil = [];
        foreach ($grupPerRi as $ri => $gidSet) {
            foreach (array_keys($gidSet) as $gid) {
                $nama = (string) ($namaGrup[$gid] ?? ('#'.$gid));
                $dlList = $dlPerGrup[$gid] ?? [];
                if ($dlList === []) {
                    $hasil[$ri][] = ['gid' => $gid, 'grup' => $nama, 'site' => '', 'lokasi' => '', 'detail' => '', 'perusahaan' => [], 'pic' => []];

                    continue;
                }
                foreach ($dlList as $d) {
                    $k = mb_strtolower(trim($d->site).'|'.trim($d->lokasi));
                    $kDetlok = $k.'|'.mb_strtolower(trim($d->detail));
                    $perusahaan = array_keys($pjaPerLokasi[$k] ?? []);
                    sort($perusahaan, SORT_NATURAL | SORT_FLAG_CASE);
                    $hasil[$ri][] = ['gid' => $gid, 'grup' => $nama, 'site' => (string) $d->site, 'lokasi' => (string) $d->lokasi, 'detail' => (string) $d->detail,
                        'perusahaan' => $perusahaan, 'pic' => $picPerDetlok[$kDetlok] ?? []];
                }
            }
        }

        return $hasil;
    }

    /**
     * PIC Perusahaan RESMI per detlok (drm_master_lokasi_pic, dari PIC_Detail_Lokasi ClickHouse) DIKELOMPOKKAN per site+lokasi — kolom
     * "PIC Perusahaan" di tabel Master Grup Lokasi & Aktivitas. BUKAN digabung rata jadi satu daftar (koreksi
     * user 26 Nov 2026): satu baris baris bisa masuk grup yang mencakup banyak lokasi fisik se-perusahaan (mis.
     * grup "Jetty/Port/Beaching Point" mencakup 8 lokasi di 6 site berbeda), jadi perusahaan harus tetap terlihat
     * relevan untuk lokasi yang mana — bukan satu gumpalan tanpa konteks lokasi.
     *
     * @return array<int, list<array{site: string, lokasi: string, pic: list<string>}>>
     */
    public function picPerRi(): array
    {
        $hasil = [];
        foreach ($this->lokasiPerRi() as $ri => $list) {
            $perLokasi = [];
            foreach ($list as $l) {
                if ($l['pic'] === [] || $l['site'] === '') {
                    continue;
                }
                $k = $l['site'].'|'.$l['lokasi'];
                $perLokasi[$k] = ['site' => $l['site'], 'lokasi' => $l['lokasi'], 'pic' => $l['pic']];
            }
            if ($perLokasi === []) {
                continue;
            }
            $daftar = array_values($perLokasi);
            usort($daftar, fn ($a, $b) => strcmp($a['site'], $b['site']) ?: strcmp($a['lokasi'], $b['lokasi']));
            $hasil[$ri] = $daftar;
        }

        return $hasil;
    }

    /**
     * Semua Detail Lokasi aktif (drm_master_dl) + PJA pemilik lokasinya (drm_master_lokasi_pja, level lokasi) +
     * PIC Perusahaan RESMI per detlok (drm_master_lokasi_pic, dari PIC_Detail_Lokasi ClickHouse sejak 27 Nov
     * 2026 — tidak lagi dari personil PJA Child) — tabel ringkas "Detail Lokasi → PJA → PIC" (keputusan user 26
     * Nov 2026: PJA/PIC melekat di level lokasi, tapi tabelnya harus diturunkan sampai ke detlok, bukan berhenti
     * di lokasi). PJA yang sama akan terlihat berulang di tiap detlok yang mewarisinya dari lokasi yang sama —
     * itu memang warisannya, bukan duplikat data; PIC sekarang sudah cocok PERSIS per detlok, tidak diwariskan.
     *
     * @return list<array{site: string, lokasi: string, detail: string, pja: list<string>, pic: list<string>}>
     */
    public function semuaLokasiPjaDanPic(): array
    {
        $pjaPerLokasi = [];
        foreach (DB::table('drm_master_lokasi_pja')->where('aktif', true)->orderBy('nama_pja')->get(['site', 'lokasi', 'nama_pja']) as $p) {
            $k = mb_strtolower(trim((string) $p->site)).'|'.mb_strtolower(trim((string) $p->lokasi));
            $pjaPerLokasi[$k][] = (string) $p->nama_pja;
        }
        $picPerDetlok = [];
        foreach (DB::table('drm_master_lokasi_pic')->where('aktif', true)->get(['site', 'lokasi', 'detail', 'pic_perusahaan']) as $p) {
            $k = mb_strtolower(trim((string) $p->site)).'|'.mb_strtolower(trim((string) $p->lokasi)).'|'.mb_strtolower(trim((string) $p->detail));
            $picPerDetlok[$k] = DrmMasterDb::pisahLabel($p->pic_perusahaan ?? '');
        }
        $hasil = [];
        foreach (DB::table('drm_master_dl')->where('aktif', true)->orderBy('site')->orderBy('lokasi')->orderBy('detail')->get(['site', 'lokasi', 'detail']) as $d) {
            $k = mb_strtolower(trim((string) $d->site)).'|'.mb_strtolower(trim((string) $d->lokasi));
            $kDetlok = $k.'|'.mb_strtolower(trim((string) $d->detail));
            $hasil[] = [
                'site' => (string) $d->site, 'lokasi' => (string) $d->lokasi, 'detail' => (string) $d->detail,
                'pja' => $pjaPerLokasi[$k] ?? [], 'pic' => $picPerDetlok[$kDetlok] ?? [],
            ];
        }

        return $hasil;
    }

    /**
     * Pasangan (site, perusahaan) ASLI per baris — BUKAN dua daftar terpisah (site unik + perusahaan unik),
     * supaya filter Site & Perusahaan bisa mensyaratkan keduanya cocok di lokasi yang SAMA (pelajaran dari
     * koreksi "Per Perusahaan" Master per Baris 24-25 Nov 2026: menggabungkan rata membuat BMO3 kelihatan
     * terhubung ke perusahaan yang sebenarnya cuma ada di BMO1). Dipakai sebagai filter di tabel (bukan kolom),
     * keputusan user 25 Nov 2026.
     *
     * @return array<int, list<array{site: string, perusahaan: list<string>}>>
     */
    public function lokasiRingkasPerBaris(): array
    {
        $hasil = [];
        foreach ($this->lokasiPerRi() as $ri => $list) {
            foreach ($list as $l) {
                if ($l['site'] === '') {
                    continue;
                }
                $hasil[$ri][] = ['site' => $l['site'], 'perusahaan' => $l['perusahaan']];
            }
        }

        return $hasil;
    }

    /** @return list<string> Site aktif, untuk opsi dropdown filter. */
    public function daftarSite(): array
    {
        return DB::table('drm_master_dl')->where('aktif', true)->distinct()->orderBy('site')->pluck('site')->map(fn ($v) => (string) $v)->all();
    }

    /** @return list<string> Nama perusahaan (lewat PJA) aktif, untuk opsi dropdown filter. */
    public function daftarPerusahaan(): array
    {
        $unik = [];
        foreach (DB::table('drm_master_lokasi_pja')->where('aktif', true)->pluck('perusahaan') as $isi) {
            foreach (DrmMasterDb::pisahLabel($isi) as $p) {
                $unik[$p] = true;
            }
        }
        $daftar = array_keys($unik);
        sort($daftar, SORT_NATURAL | SORT_FLAG_CASE);

        return $daftar;
    }

    /**
     * Site/Lokasi FISIK + PJA yang menaunginya untuk sekumpulan ri (keputusan user 27 Nov 2026, popup "Asal HIRA"
     * di Overview: selain dokumen HIRA ClickHouse, tampilkan juga lokasi & PJA terkait dari master DRM 2.1
     * sendiri — grup lokasi M05 → detail lokasi → PJA, SAMA rantai dengan Alur Baseline). Disaring ke ri yang
     * diminta saja (bukan recompute semua baris master seperti lokasiPerRi()), supaya murah dipanggil ad hoc dari
     * satu klik popup. Dikelompokkan per (site, lokasi) — BUKAN sampai ke detlok — karena satu grup bisa
     * mencakup ratusan detlok (sama alasan dengan picPerRi()), terlalu banyak untuk ditampilkan di satu popup.
     *
     * @param  list<int>  $ri
     * @return array<int, list<array{site: string, lokasi: string, pja: list<string>}>>
     */
    public function lokasiPjaUntukRi(array $ri): array
    {
        $ri = array_values(array_unique(array_filter(array_map('intval', $ri), fn ($x) => $x > 0)));
        if ($ri === []) {
            return [];
        }
        $gidPerRi = [];
        foreach (DB::table('drm_master_grup_baris')->whereIn('ri', $ri)->get(['ri', 'gid']) as $g) {
            $gidPerRi[(int) $g->ri][] = (int) $g->gid;
        }
        $gids = array_values(array_unique(array_merge(...array_values($gidPerRi) ?: [[]])));
        if ($gids === []) {
            return [];
        }
        $lokasiPerGid = [];
        foreach (DB::table('drm_master_dl')->whereIn('gid', $gids)->where('aktif', true)->get(['site', 'lokasi', 'gid']) as $d) {
            $k = mb_strtolower(trim((string) $d->site)).'|'.mb_strtolower(trim((string) $d->lokasi));
            $lokasiPerGid[(int) $d->gid][$k] = ['site' => (string) $d->site, 'lokasi' => (string) $d->lokasi];
        }
        $sites = [];
        foreach ($lokasiPerGid as $list) {
            foreach ($list as $l) {
                $sites[$l['site']] = true;
            }
        }
        $pjaPerLokasi = [];
        if ($sites !== []) {
            foreach (DB::table('drm_master_lokasi_pja')->where('aktif', true)->whereIn('site', array_keys($sites))->get(['site', 'lokasi', 'nama_pja']) as $p) {
                $k = mb_strtolower(trim((string) $p->site)).'|'.mb_strtolower(trim((string) $p->lokasi));
                $pjaPerLokasi[$k][(string) $p->nama_pja] = true;
            }
        }
        $hasil = [];
        foreach ($ri as $r) {
            $lokasiUnik = [];
            foreach ($gidPerRi[$r] ?? [] as $gid) {
                foreach ($lokasiPerGid[$gid] ?? [] as $k => $l) {
                    $lokasiUnik[$k] = $l;
                }
            }
            if ($lokasiUnik === []) {
                continue;
            }
            $daftar = [];
            foreach ($lokasiUnik as $k => $l) {
                $pja = array_keys($pjaPerLokasi[$k] ?? []);
                sort($pja, SORT_NATURAL | SORT_FLAG_CASE);
                $daftar[] = ['site' => $l['site'], 'lokasi' => $l['lokasi'], 'pja' => $pja];
            }
            usort($daftar, fn ($a, $b) => strcmp($a['site'], $b['site']) ?: strcmp($a['lokasi'], $b['lokasi']));
            $hasil[$r] = $daftar;
        }

        return $hasil;
    }

    /**
     * Baris datar untuk unduhan CSV: SATU baris = SATU (grup lokasi × site/lokasi/detail × perusahaan), tidak
     * digabung jadi teks (keputusan user 25 Nov 2026, pola sama dengan unduhan Alur Baseline/Korelasi). Filter
     * sama dengan yang di tabel browser (kb, kelompok, grup/tanpa grup, kata cari) — HANYA menyaring baris HIRA
     * mana yang disertakan; grup/site/perusahaan yang tampil per baris tetap SEMUA, sama seperti di layar
     * (filter grup di layar juga cuma menyembunyikan baris, bukan memotong chip grup yang tampil).
     *
     * GENERATOR, bukan array penuh (koreksi 2026-11-25, setelah unduhan serupa di /peta-risiko-pja/korelasi
     * kehabisan memori 2 GB pada baseline sebesar ini): baris HIRA dibaca lewat cursor() dan tiap hasil pecah
     * langsung di-yield, controller menulisnya sebagai CSV streaming (fputcsv per baris) — BUKAN Xlsx, karena
     * PhpSpreadsheet tetap menyimpan semua sel di memori sebelum menulis berkas.
     *
     * Filter Site/Perusahaan mensyaratkan keduanya cocok di lokasi yang SAMA (bukan "ada site ini di baris" DAN
     * terpisah "ada perusahaan ini di baris", yang bisa salah pasang — pelajaran dari koreksi "Per Perusahaan"
     * Master per Baris 24-25 Nov 2026).
     *
     * @param  array{kb?: string, kelompok?: string, grup?: string, site?: string, pja?: string, q?: string}  $filter
     * @return \Generator<int, array<string, string>>
     */
    public function eksporBarisLazy(array $filter = []): \Generator
    {
        $baris = DB::table('drm_master_baris as b')->join('drm_master_aktivitas as a', 'a.aid', '=', 'b.aid')
            ->orderBy('b.aktivitas')->orderBy('b.sub')->orderBy('b.bahaya')
            ->select(['b.ri', 'b.aktivitas', 'b.sub', 'b.bahaya', 'b.kb', 'a.kelompok'])->cursor();
        $namaKelompok = $this->kelompok();
        $lokasi = $this->lokasiPerRi();
        $kata = mb_strtolower(trim((string) ($filter['q'] ?? '')));
        $grupFilter = (string) ($filter['grup'] ?? '');
        $siteFilter = (string) ($filter['site'] ?? '');
        $pjaFilter = (string) ($filter['pja'] ?? '');
        foreach ($baris as $b) {
            if (($filter['kb'] ?? '') !== '' && (string) $b->kb !== $filter['kb']) {
                continue;
            }
            if (($filter['kelompok'] ?? '') !== '' && (string) $b->kelompok !== $filter['kelompok']) {
                continue;
            }
            $list = $lokasi[(int) $b->ri] ?? [];
            $gidList = array_unique(array_column($list, 'gid'));
            if ($grupFilter === '__TANPA__' && $gidList !== []) {
                continue;
            }
            if ($grupFilter !== '' && $grupFilter !== '__TANPA__' && ! in_array((int) $grupFilter, $gidList, true)) {
                continue;
            }
            if (($siteFilter !== '' || $pjaFilter !== '') && ! collect($list)->contains(
                fn ($l) => ($siteFilter === '' || $l['site'] === $siteFilter) && ($pjaFilter === '' || in_array($pjaFilter, $l['perusahaan'], true))
            )) {
                continue;
            }
            if ($kata !== '' && ! str_contains(mb_strtolower($b->aktivitas.' '.$b->sub.' '.$b->bahaya), $kata)) {
                continue;
            }
            $dasar = [
                'ri' => (string) $b->ri, 'aktivitas' => (string) $b->aktivitas,
                'kelompok' => (string) ($namaKelompok[$b->kelompok] ?? ((string) $b->kelompok ?: 'K0')),
                'sub' => (string) ($b->sub ?? ''), 'bahaya' => (string) $b->bahaya, 'kb' => (string) ($b->kb ?? ''),
            ];
            if ($list === []) {
                yield $dasar + ['grup' => '', 'site' => '', 'detail' => '', 'perusahaan' => '', 'pic' => ''];

                continue;
            }
            foreach ($list as $l) {
                $detail = $l['lokasi'] !== '' ? $l['lokasi'].($l['detail'] !== '' ? ' ('.$l['detail'].')' : '') : '';
                $pic = implode('; ', $l['pic']);
                if ($l['perusahaan'] === []) {
                    yield $dasar + ['grup' => $l['grup'], 'site' => $l['site'], 'detail' => $detail, 'perusahaan' => '', 'pic' => $pic];

                    continue;
                }
                foreach ($l['perusahaan'] as $p) {
                    yield $dasar + ['grup' => $l['grup'], 'site' => $l['site'], 'detail' => $detail, 'perusahaan' => $p, 'pic' => $pic];
                }
            }
        }
    }

    /**
     * Tambahkan baris (ri) langsung ke grup, lintas aktivitas (dipakai chip "+ Tambah ke grup" & aksi borongan di
     * master/grup — beda dengan tambah(), yang membatasi ke satu aktivitas untuk alur "Tambah aktivitas" / Alur baseline).
     * Mengembalikan jumlah baru.
     *
     * @param  list<int>  $ri
     */
    public function tambahBaris(int $gid, array $ri): int
    {
        $this->pastikanGrup($gid);
        $valid = DB::table('drm_master_baris')->whereIn('ri', $ri)->pluck('ri')->map(fn ($v) => (int) $v)->all();

        return DB::transaction(function () use ($gid, $valid) {
            $ada = DB::table('drm_master_grup_baris')->where('gid', $gid)->whereIn('ri', $valid)->pluck('ri')->map(fn ($v) => (int) $v)->all();
            $baru = array_values(array_diff($valid, $ada));
            $sekarang = now();
            foreach ($baru as $r) {
                $id = DB::table('drm_master_grup_baris')->insertGetId(['gid' => $gid, 'ri' => $r, 'diubah_oleh' => $this->user(), 'created_at' => $sekarang, 'updated_at' => $sekarang]);
                $this->db->catat('drm_master_grup_baris', (string) $id, 'tambah', null, ['gid' => $gid, 'ri' => $r]);
            }

            return count($baru);
        });
    }

    /**
     * Tambahkan baris aktivitas ke grup (semua barisnya, atau hanya $ri yang milik aktivitas itu). Mengembalikan jumlah baru.
     *
     * @param  list<int>|null  $ri
     */
    public function tambah(int $gid, int $aid, ?array $ri = null): int
    {
        $this->pastikanGrup($gid);
        $milik = DB::table('drm_master_baris')->where('aid', $aid)->pluck('ri')->map(fn ($v) => (int) $v)->all();
        if ($milik === []) {
            throw new InvalidArgumentException('Aktivitas tidak ditemukan.');
        }
        $target = $ri === null ? $milik : array_values(array_intersect($milik, $ri));

        return DB::transaction(function () use ($gid, $target) {
            $ada = DB::table('drm_master_grup_baris')->where('gid', $gid)->whereIn('ri', $target)->pluck('ri')->map(fn ($v) => (int) $v)->all();
            $baru = array_values(array_diff($target, $ada));
            $sekarang = now();
            foreach ($baru as $r) {
                $id = DB::table('drm_master_grup_baris')->insertGetId(['gid' => $gid, 'ri' => $r, 'diubah_oleh' => $this->user(), 'created_at' => $sekarang, 'updated_at' => $sekarang]);
                $this->db->catat('drm_master_grup_baris', (string) $id, 'tambah', null, ['gid' => $gid, 'ri' => $r]);
            }

            return count($baru);
        });
    }

    /**
     * Keluarkan baris dari grup. Mengembalikan jumlah yang dihapus.
     *
     * @param  list<int>  $ri
     */
    public function hapus(int $gid, array $ri): int
    {
        return DB::transaction(function () use ($gid, $ri) {
            $rows = DB::table('drm_master_grup_baris')->where('gid', $gid)->whereIn('ri', $ri)->get(['id', 'gid', 'ri']);
            foreach ($rows as $r) {
                DB::table('drm_master_grup_baris')->where('id', $r->id)->delete();
                $this->db->catat('drm_master_grup_baris', (string) $r->id, 'hapus', ['gid' => (int) $r->gid, 'ri' => (int) $r->ri], null);
            }

            return $rows->count();
        });
    }

    /**
     * Pindahkan baris dari grup asal ke grup tujuan. Baris yang sudah ada di tujuan cukup dikeluarkan dari asal.
     *
     * @param  list<int>  $ri
     */
    public function pindah(int $asal, int $tujuan, array $ri): int
    {
        if ($asal === $tujuan) {
            throw new InvalidArgumentException('Grup tujuan sama dengan grup asal.');
        }
        $this->pastikanGrup($tujuan);

        return DB::transaction(function () use ($asal, $tujuan, $ri) {
            $sudah = DB::table('drm_master_grup_baris')->where('gid', $tujuan)->whereIn('ri', $ri)->pluck('ri')->map(fn ($v) => (int) $v)->all();
            $rows = DB::table('drm_master_grup_baris')->where('gid', $asal)->whereIn('ri', $ri)->get(['id', 'ri']);
            foreach ($rows as $r) {
                if (in_array((int) $r->ri, $sudah, true)) {
                    DB::table('drm_master_grup_baris')->where('id', $r->id)->delete();
                    $this->db->catat('drm_master_grup_baris', (string) $r->id, 'hapus', ['gid' => $asal, 'ri' => (int) $r->ri], null);

                    continue;
                }
                DB::table('drm_master_grup_baris')->where('id', $r->id)->update(['gid' => $tujuan, 'diubah_oleh' => $this->user(), 'updated_at' => now()]);
                $this->db->catat('drm_master_grup_baris', (string) $r->id, 'ubah', ['gid' => $asal, 'ri' => (int) $r->ri], ['gid' => $tujuan, 'ri' => (int) $r->ri]);
            }

            return $rows->count();
        });
    }

    /** Ubah kelompok aktivitas (M02). True bila berubah. */
    public function ubahKelompok(int $aid, string $kelompok): bool
    {
        if (! DB::table('drm_master_kelompok')->where('kode', $kelompok)->exists()) {
            throw new InvalidArgumentException('Kelompok aktivitas tidak dikenal.');
        }
        $a = DB::table('drm_master_aktivitas')->where('aid', $aid)->first();
        if ($a === null) {
            throw new InvalidArgumentException('Aktivitas tidak ditemukan.');
        }
        if ((string) $a->kelompok === $kelompok) {
            return false;
        }
        DB::table('drm_master_aktivitas')->where('aid', $aid)->update(['kelompok' => $kelompok, 'diubah_oleh' => $this->user(), 'updated_at' => now()]);
        $this->db->catat('drm_master_aktivitas', (string) $aid, 'ubah', ['kelompok' => (string) $a->kelompok], ['kelompok' => $kelompok]);

        return true;
    }

    /** @return array<string, string> kode => nama, urut sesuai master */
    public function kelompok(): array
    {
        return DB::table('drm_master_kelompok')->orderBy('urutan')->pluck('nama', 'kode')->map(fn ($v) => (string) $v)->all();
    }

    private function pastikanGrup(int $gid): void
    {
        if (! DB::table('drm_master_grup')->where('gid', $gid)->exists()) {
            throw new InvalidArgumentException('Grup lokasi tidak ditemukan.');
        }
    }

    private function user(): ?int
    {
        $id = auth()->id();

        return $id === null ? null : (int) $id;
    }
}
