<?php

declare(strict_types=1);

namespace App\Services\PetaRisiko\Drm;

use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Alur baseline DRM 2.1 (/peta-risiko-pja/korelasi), dibaca kiri → kanan per kolom:
 *   Aktivitas HIRA → Sub aktivitas → Bahaya-risiko → Label SAP (cara tertangkap) → Grup lokasi (M05) → Detail lokasi → PJA.
 * Satu simpul fokus menentukan cakupan alur; tiap kolom dibatasi supaya tetap terbaca (sisanya dihitung di "potong").
 * Status SAP bahaya (K/G/T) dari snapshot terakhir (rincian.json cakupan ALL, minggu penuh terakhir).
 */
final class DrmKorelasi
{
    public const JENIS = ['grup', 'dl', 'pja', 'kelompok', 'aktivitas', 'bahaya', 'obs', 'oak', 'ket', 'site_perusahaan', 'multi'];

    /** Pemisah site/perusahaan di parameter id milik jenis site_perusahaan (lihat dariSitePerusahaan). */
    private const PEMISAH_SITE_PERUSAHAAN = '||';

    /** Kolom alur (indeks = posisi kiri → kanan). */
    public const KOLOM = ['aktivitas', 'sub', 'bahaya', 'label', 'grup', 'dl', 'pja', 'pja_anak'];

    private const MAKS = 60;

    private const MAKS_DL_PER_GRUP = 12;

    /** PJA Child (Mitra) per PJA bisa sangat banyak (ada yang puluhan) — dibatasi sama seperti DL per grup. */
    private const MAKS_ANAK_PER_PJA = 12;

    private const LABEL_JENIS = ['obs' => 'OBS', 'oak' => 'OAK', 'ket' => 'KET'];

    private const NAMA_LABEL = ['obs' => 'Label Observasi', 'oak' => 'Label OAK', 'ket' => 'Label Ketidaksesuaian'];

    /** @var array<string, array<string, mixed>> */
    private array $node = [];

    /** @var array<string, array{from: string, to: string}> */
    private array $edge = [];

    /** @var array<string, int> kolom => jumlah simpul yang tidak ditampilkan */
    private array $potong = [];

    /** @var array<string, string>|null */
    private ?array $namaKelompok = null;

    /** @var array{minggu: ?string, hid: array<string, string>}|null */
    private ?array $status = null;

    /** @var array<string, list<string>>|null nama_pja (huruf kecil) => daftar nama perusahaan */
    private ?array $perusahaanPerPja = null;

    /** @var array<string, list<string>>|null nama_pja (huruf kecil) => daftar nama PJA Child (Mitra) */
    private ?array $anakPerPja = null;

    /** @var array<string, list<string>>|null "site|lokasi|detail" (huruf kecil) => daftar PIC Perusahaan resmi */
    private ?array $picPerLokasi = null;

    /** @var array<string, list<string>>|null nama_pja (huruf kecil) => perusahaan LANGSUNG (bukan gabungan) */
    private ?array $perusahaanLangsungPerPja = null;

    /**
     * Perusahaan yang sedang jadi filter PJA Child (fitur Site/Perusahaan, keputusan user 26 Nov 2026) — diset
     * oleh dariSitePerusahaan() SEBELUM memanggil simpulPja(), supaya PJA Child yang tampil hanya yang
     * perusahaannya sendiri cocok filter (bukan semua anak PJA induk yang kebetulan cocok). Null = tidak
     * menyaring (tampilkan semua anak, perilaku lama — dipakai fokus lain selain site_perusahaan).
     */
    private ?string $filterPerusahaanAnak = null;

    public function __construct(private readonly DrmRepository $repo)
    {
    }

    /** Fokus bawaan: aktivitas HIRA dengan baris (bahaya-risiko) terbanyak — awal alur baseline. */
    public function pusatBawaan(): array
    {
        $aid = DB::table('drm_master_baris')->selectRaw('aid, count(*) n')->groupBy('aid')->orderByDesc('n')->value('aid');

        return ['jenis' => 'aktivitas', 'id' => (string) ($aid ?? 0)];
    }

    /**
     * Alur untuk satu simpul fokus.
     *
     * @return array{pusat: string, kolom: list<string>, node: list<array<string, mixed>>, edge: list<array{from: string, to: string}>, potong: array<string, int>, minggu: ?string}
     */
    public function alur(string $jenis, string $id): array
    {
        if (! in_array($jenis, self::JENIS, true)) {
            throw new InvalidArgumentException('Jenis simpul tidak dikenal.');
        }
        $this->node = $this->edge = $this->potong = [];
        $this->filterPerusahaanAnak = null;
        $pusat = match ($jenis) {
            'aktivitas' => $this->dariAktivitas((int) $id),
            'bahaya' => $this->dariBaris(DB::table('drm_master_baris')->where('ri', (int) $id)->get(), 'bahaya'),
            'kelompok' => $this->dariKelompok($id),
            'grup' => $this->dariGrup((int) $id),
            'dl' => $this->dariDl((int) $id),
            'pja' => $this->dariPja($id),
            'site_perusahaan' => $this->dariSitePerusahaan($id),
            'multi' => $this->dariMulti(is_array($dekode = json_decode($id, true)) ? $dekode : []),
            default => $this->dariLabel(self::LABEL_JENIS[$jenis], $id),
        };
        if ($pusat === null) {
            throw new InvalidArgumentException('Simpul tidak ditemukan di master.');
        }
        $this->node[$pusat]['pusat'] = true;

        return ['pusat' => $pusat, 'kolom' => self::KOLOM, 'node' => array_values($this->node), 'edge' => array_values($this->edge),
            'potong' => $this->potong, 'minggu' => $this->statusSap()['minggu']];
    }

    /** Nama tampil jenis label, sama dengan kolom "Jenis" di unduhan Master per Baris. */
    private const NAMA_JENIS_LABEL = ['OBS' => 'Observasi', 'OAK' => 'OAK', 'KET' => 'Ketidaksesuaian'];

    /**
     * Ekspor flat SELURUH baseline (semua aktivitas — keputusan user 25 Nov 2026: unduhan ini LEPAS dari fokus
     * diagram yang sedang dibuka, bukan cuma aktivitas yang sedang dilihat) untuk unduhan CSV
     * (/peta-risiko-pja/korelasi/unduh): TIDAK ADA sel yang digabung/dipisah koma (koreksi 2026-11-25) — tiap
     * label SAP dan tiap jalur Grup lokasi → Detail lokasi → PJA jadi BARIS SENDIRI (aktivitas/sub/bahaya-risiko
     * diulang per baris, pola yang sama dengan "satu baris = satu label" di unduhan Master per Baris,
     * DrmMasterBarisUnggah::ekspor / DrmMasterBarisExcel). Label dan jalur lokasi sengaja TIDAK disilangkan
     * (bukan satu label = satu lokasi tertentu — keduanya cabang independen dari bahaya yang sama), jadi baris
     * label kolom lokasinya kosong dan sebaliknya.
     *
     * GENERATOR, bukan array penuh (koreksi 2026-11-25 kedua): seluruh baseline × grup × lokasi × PJA bisa jadi
     * jutaan baris hasil pecah — menumpuknya semua di satu array sekaligus (lalu Xlsx-nya) yang bikin unduhan
     * kehabisan memori 2 GB. Baris HIRA dibaca lewat cursor() (bukan get()) dan tiap baris hasil pecah langsung
     * di-yield, jadi memori tetap datar berapa pun totalnya. Controller menulisnya sebagai CSV streaming
     * (fputcsv per baris), BUKAN Xlsx — PhpSpreadsheet tetap menyimpan semua sel di memori sebelum disimpan,
     * jadi tidak cocok untuk jumlah baris sebesar ini walau ditulisnya per potongan.
     *
     * @return \Generator<int, array<string, string>>
     */
    /**
     * SEMUA aktivitas (bukan cuma fokus yang sedang dibuka), opsional disaring site/perusahaan — sama seperti
     * filter toolbar alur (lihat dariSitePerusahaan): hanya baris yang grup-lokasinya punya detail lokasi +
     * PJA yang cocok site/perusahaan itu yang ikut diekspor. Saat filter aktif, baris label SAP (tidak terkait
     * lokasi) dan grup/DL yang tidak cocok filter DIHILANGKAN sepenuhnya (bukan baris kosong), supaya unduhan
     * cuma berisi apa yang relevan dengan filter — keputusan user 26 Nov 2026.
     */
    public function eksporSemuaLazy(?string $site = null, ?string $perusahaan = null): \Generator
    {
        $site = trim((string) $site);
        $perusahaan = trim((string) $perusahaan);
        $filterAktif = $site !== '' || $perusahaan !== '';

        $this->namaKelompok ??= DB::table('drm_master_kelompok')->pluck('nama', 'kode')->all();
        $akt = DB::table('drm_master_aktivitas')->get()->keyBy('aid');
        $namaGrup = DB::table('drm_master_grup')->pluck('nama', 'gid');
        $grupPerRi = [];
        foreach (DB::table('drm_master_grup_baris')->get(['ri', 'gid']) as $g) {
            $grupPerRi[(int) $g->ri][] = [(int) $g->gid, (string) ($namaGrup[$g->gid] ?? ('#'.$g->gid))];
        }
        $gids = collect($grupPerRi)->flatten(1)->map(fn ($g) => $g[0])->unique()->values()->all();
        $dlQuery = DB::table('drm_master_dl')->whereIn('gid', $gids)->where('aktif', true);
        if ($site !== '') {
            $dlQuery->where('site', $site);
        }
        $dlPerGrup = [];
        foreach ($dlQuery->orderBy('site')->orderBy('lokasi')->orderBy('detail')->get() as $d) {
            $dlPerGrup[(int) $d->gid][] = $d;
        }
        $sites = [];
        foreach ($dlPerGrup as $list) {
            foreach ($list as $d) {
                $sites[(string) $d->site] = true;
            }
        }
        $pjaPerLokasi = [];
        if ($sites !== []) {
            foreach (DB::table('drm_master_lokasi_pja')->where('aktif', true)->whereIn('site', array_keys($sites))->get(['site', 'lokasi', 'nama_pja', 'perusahaan']) as $p) {
                if ($perusahaan !== '' && ! in_array($perusahaan, DrmMasterDb::pisahLabel($p->perusahaan ?? ''), true)) {
                    continue;
                }
                $k = mb_strtolower(trim($p->site).'|'.trim($p->lokasi));
                $pjaPerLokasi[$k][(string) $p->nama_pja] = true;
            }
        }
        if ($filterAktif) {
            // DL yang lokasinya tidak punya PJA cocok filter: buang (bukan DL tanpa PJA pada umumnya, lihat di bawah).
            foreach ($dlPerGrup as $gid => $list) {
                $dlPerGrup[$gid] = array_values(array_filter(
                    $list,
                    fn ($d) => isset($pjaPerLokasi[mb_strtolower(trim((string) $d->site).'|'.trim((string) $d->lokasi))])
                ));
                if ($dlPerGrup[$gid] === []) {
                    unset($dlPerGrup[$gid]);
                }
            }
        }
        foreach (DB::table('drm_master_baris')->orderBy('aid')->orderBy('sub')->orderBy('bahaya')->cursor() as $b) {
            $a = $akt[$b->aid] ?? null;
            $dasar = [
                'ri' => (string) $b->ri,
                'aktivitas' => (string) ($a->aktivitas ?? $b->aktivitas),
                'kelompok' => (string) ($this->namaKelompok[$a->kelompok ?? ''] ?? ($a->kelompok ?? '')),
                'sub' => (string) ($b->sub ?? ''),
                'bahaya' => (string) $b->bahaya,
                'kb' => (string) ($b->kb ?? ''),
            ];
            $adaBaris = false;
            if (! $filterAktif) {
                $label = [];
                foreach ($this->labelBaris($b) as [$j, $teks]) {
                    $label[$j.'|'.$teks] = [$j, $teks];
                }
                foreach ($label as [$j, $teks]) {
                    $adaBaris = true;
                    yield $dasar + ['jenis_label' => self::NAMA_JENIS_LABEL[$j] ?? $j, 'label' => $teks, 'grup' => '', 'dl' => '', 'pja' => ''];
                }
            }
            foreach ($grupPerRi[(int) $b->ri] ?? [] as [$gid, $namaG]) {
                $dlList = $dlPerGrup[$gid] ?? [];
                if ($dlList === []) {
                    if (! $filterAktif) {
                        $adaBaris = true;
                        yield $dasar + ['jenis_label' => '', 'label' => '', 'grup' => $namaG, 'dl' => '', 'pja' => ''];
                    }

                    continue;
                }
                foreach ($dlList as $d) {
                    $labelDl = $d->site.' · '.$d->lokasi.' ('.$d->detail.')';
                    $pja = array_keys($pjaPerLokasi[mb_strtolower(trim($d->site).'|'.trim($d->lokasi))] ?? []);
                    sort($pja, SORT_NATURAL | SORT_FLAG_CASE);
                    if ($pja === []) {
                        $adaBaris = true;
                        yield $dasar + ['jenis_label' => '', 'label' => '', 'grup' => $namaG, 'dl' => $labelDl, 'pja' => ''];

                        continue;
                    }
                    foreach ($pja as $nama) {
                        $adaBaris = true;
                        yield $dasar + ['jenis_label' => '', 'label' => '', 'grup' => $namaG, 'dl' => $labelDl, 'pja' => $nama];
                    }
                }
            }
            if (! $adaBaris && ! $filterAktif) {
                yield $dasar + ['jenis_label' => '', 'label' => '', 'grup' => '', 'dl' => '', 'pja' => ''];
            }
        }
    }

    /**
     * Daftar nilai untuk dropdown multi-select "Tandai" di toolbar (keputusan user 27 Nov 2026): centang satu
     * atau lebih nilai per kategori, lalu diagram menampilkan GABUNGAN relasi semua yang dicentang (lintas
     * kategori sekaligus) dalam satu kanvas — lihat dariMulti(). `id` di sini adalah id yang dipakai dariMulti()
     * untuk membangun pohonnya (aid untuk aktivitas, gid untuk grup, id baris drm_master_dl untuk dl, nama_pja
     * untuk pja); Sub Aktivitas & PJA Child TIDAK punya pembangun pohon sendiri (lihat JENIS), jadi id-nya
     * sengaja id PARENT-nya (aid induk / nama PJA induk) — mencentangnya ikut menarik SELURUH pohon induknya,
     * bukan cuma baris sub/anak itu sendiri.
     *
     * @return array{aktivitas: list<array{id: string, label: string}>, sub: list<array{id: string, label: string}>, grup: list<array{id: string, label: string}>, dl: list<array{id: string, label: string}>, pja: list<array{id: string, label: string}>, pja_anak: list<array{id: string, label: string}>}
     */
    public function daftarDropdown(): array
    {
        $aktivitas = DB::table('drm_master_aktivitas')->orderBy('aktivitas')->get(['aid', 'aktivitas'])
            ->map(fn ($r) => ['id' => (string) $r->aid, 'label' => (string) $r->aktivitas])->all();
        $grup = DB::table('drm_master_grup')->where('aktif', true)->orderBy('nama')->get(['gid', 'nama'])
            ->map(fn ($r) => ['id' => (string) $r->gid, 'label' => (string) $r->nama])->all();
        $dl = DB::table('drm_master_dl')->where('aktif', true)->orderBy('site')->orderBy('lokasi')->orderBy('detail')->get(['id', 'site', 'lokasi', 'detail'])
            ->map(fn ($r) => ['id' => (string) $r->id, 'label' => $r->site.' · '.$r->lokasi.' · '.$r->detail])->all();
        $pja = DB::table('drm_master_lokasi_pja')->where('aktif', true)->distinct()->orderBy('nama_pja')->pluck('nama_pja')
            ->map(fn ($n) => ['id' => (string) $n, 'label' => (string) $n])->values()->all();

        $sub = [];
        $subUnik = [];
        foreach (DB::table('drm_master_baris')->where('aktif', true)->get(['aid', 'sub']) as $b) {
            $teks = trim((string) $b->sub);
            $k = mb_strtolower($teks);
            if ($teks === '' || isset($subUnik[$k])) {
                continue;
            }
            $subUnik[$k] = true;
            $sub[] = ['id' => (string) $b->aid, 'label' => $teks];
        }
        usort($sub, fn ($a, $b) => strcmp($a['label'], $b['label']));

        $pjaAnak = [];
        $anakUnik = [];
        foreach (DB::table('drm_master_lokasi_pja')->where('aktif', true)->get(['nama_pja', 'pja_anak']) as $p) {
            foreach (DrmMasterDb::pisahLabel($p->pja_anak ?? '') as $nama) {
                $k = mb_strtolower($nama);
                if (isset($anakUnik[$k])) {
                    continue;
                }
                $anakUnik[$k] = true;
                $pjaAnak[] = ['id' => (string) $p->nama_pja, 'label' => $nama];
            }
        }
        usort($pjaAnak, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return ['aktivitas' => $aktivitas, 'grup' => $grup, 'dl' => $dl, 'pja' => $pja, 'sub' => $sub, 'pja_anak' => $pjaAnak];
    }

    /**
     * Cari simpul di semua jenis (maks 8 per jenis).
     *
     * @return list<array{jenis: string, id: string, label: string, sub: string}>
     */
    public function cari(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $pola = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
        $hasil = [];
        $tambah = function (string $jenis, $rows, callable $f) use (&$hasil) {
            foreach ($rows as $r) {
                $hasil[] = ['jenis' => $jenis] + $f($r);
            }
        };
        $tambah('aktivitas', DB::table('drm_master_aktivitas')->where('aktivitas', 'like', $pola)->limit(8)->get(), fn ($r) => ['id' => (string) $r->aid, 'label' => $r->aktivitas, 'sub' => 'Aktivitas HIRA']);
        $tambah('bahaya', DB::table('drm_master_baris')->where('bahaya', 'like', $pola)->limit(8)->get(), fn ($r) => ['id' => (string) $r->ri, 'label' => $r->bahaya, 'sub' => 'Bahaya · '.$r->aktivitas]);
        $tambah('grup', DB::table('drm_master_grup')->where('nama', 'like', $pola)->limit(8)->get(), fn ($r) => ['id' => (string) $r->gid, 'label' => $r->nama, 'sub' => 'Grup lokasi']);
        $tambah('kelompok', DB::table('drm_master_kelompok')->where('nama', 'like', $pola)->limit(8)->get(), fn ($r) => ['id' => $r->kode, 'label' => $r->nama, 'sub' => 'Kelompok aktivitas']);
        $tambah('dl', DB::table('drm_master_dl')->where('detail', 'like', $pola)->limit(8)->get(), fn ($r) => ['id' => (string) $r->id, 'label' => $r->detail, 'sub' => $r->site.' · '.$r->lokasi]);
        $tambah('pja', DB::table('drm_master_lokasi_pja')->where('nama_pja', 'like', $pola)->distinct()->limit(8)->pluck('nama_pja'), fn ($n) => ['id' => (string) $n, 'label' => (string) $n, 'sub' => 'PJA']);
        foreach ($this->cariLabel($q) as $l) {
            $hasil[] = $l;
        }

        return $hasil;
    }

    // ------------------------------------------------------------ titik masuk

    private function dariAktivitas(int $aid): ?string
    {
        $baris = DB::table('drm_master_baris')->where('aid', $aid)->orderBy('sub')->orderBy('bahaya')->get();

        return $baris->isEmpty() ? null : $this->dariBaris($baris, 'aktivitas');
    }

    /**
     * Alur lengkap untuk sekumpulan baris HIRA: aktivitas → sub → bahaya → label → grup → DL → PJA.
     * $fokus menentukan simpul pusat (aktivitas pertama atau bahaya pertama).
     */
    private function dariBaris(Collection $baris, string $fokus): ?string
    {
        if ($baris->isEmpty()) {
            return null;
        }
        $akt = DB::table('drm_master_aktivitas')->whereIn('aid', $baris->pluck('aid')->unique())->get()->keyBy('aid');
        $grupPerRi = $this->grupPerBaris($baris->pluck('ri')->all());
        $grupDipakai = [];
        foreach ($this->batasi('bahaya', $baris) as $b) {
            $a = $akt[$b->aid] ?? null;
            $na = $this->simpulAktivitas((int) $b->aid, (string) ($a->aktivitas ?? $b->aktivitas), (string) ($a->kelompok ?? 'K0'));
            $ns = $this->simpulSub((int) $b->aid, (string) $b->sub);
            $nb = $this->simpulBahaya($b);
            $this->sambung($na, $ns);
            $this->sambung($ns, $nb);
            foreach ($this->labelBaris($b) as [$jenis, $teks]) {
                $this->sambung($nb, $this->simpulLabel($jenis, $teks));
            }
            foreach ($grupPerRi[(int) $b->ri] ?? [] as [$gid, $nama]) {
                $this->sambung($nb, $this->simpulGrup($gid, $nama));
                $grupDipakai[$gid] = true;
            }
        }
        $this->lanjutDariGrup(array_keys($grupDipakai));
        $pertama = $baris->first();

        return $fokus === 'bahaya' ? 'b:'.$pertama->ri : 'a:'.$pertama->aid;
    }

    private function dariKelompok(string $kode): ?string
    {
        if (! DB::table('drm_master_kelompok')->where('kode', $kode)->exists()) {
            return null;
        }
        $akt = DB::table('drm_master_aktivitas')->where('kelompok', $kode)->orderBy('aktivitas')->get();
        $grupPerAkt = $this->grupPerAktivitas($akt->pluck('aid')->all());
        $pusat = null;
        foreach ($this->batasi('aktivitas', $akt) as $a) {
            $na = $this->simpulAktivitas((int) $a->aid, (string) $a->aktivitas, $kode);
            $pusat ??= $na;
            foreach ($grupPerAkt[(int) $a->aid] ?? [] as [$gid, $nama]) {
                $this->sambung($na, $this->simpulGrup($gid, $nama));
            }
        }

        return $pusat;
    }

    /** Grup lokasi: aktivitas yang berlaku → grup → detail lokasi → PJA (sub & bahaya dibuka lewat aktivitas). */
    private function dariGrup(int $gid): ?string
    {
        $g = DB::table('drm_master_grup')->where('gid', $gid)->first();
        if ($g === null) {
            return null;
        }
        $pusat = $this->simpulGrup($gid, (string) $g->nama);
        $akt = DB::table('drm_master_grup_baris as gb')->join('drm_master_baris as b', 'b.ri', '=', 'gb.ri')->join('drm_master_aktivitas as a', 'a.aid', '=', 'b.aid')
            ->where('gb.gid', $gid)->selectRaw('a.aid, a.aktivitas, a.kelompok, count(*) n')->groupBy('a.aid', 'a.aktivitas', 'a.kelompok')->orderByDesc('n')->get();
        foreach ($this->batasi('aktivitas', $akt) as $a) {
            $this->sambung($this->simpulAktivitas((int) $a->aid, (string) $a->aktivitas, (string) $a->kelompok, (int) $a->n), $pusat);
        }
        $this->lanjutDariGrup([$gid], self::MAKS);

        return $pusat;
    }

    private function dariDl(int $id): ?string
    {
        $d = DB::table('drm_master_dl')->where('id', $id)->first();
        if ($d === null) {
            return null;
        }
        $this->dariGrup((int) $d->gid);
        // Hanya detail lokasi ini (bukan semua DL grup) di kolom detail lokasi.
        $this->buangKolom('dl');
        $this->buangKolom('pja');
        $nd = $this->simpulDl($d);
        $g = DB::table('drm_master_grup')->where('gid', $d->gid)->first();
        if ($g !== null) {
            $this->sambung($this->simpulGrup((int) $g->gid, (string) $g->nama), $nd);
        }
        foreach ($this->pjaDariDl(collect([$d])) as $p) {
            $this->sambung($nd, $this->simpulPja($p));
        }

        return $nd;
    }

    /** PJA: detail lokasinya → grup lokasi → aktivitas yang berlaku (dibaca ke kiri). */
    private function dariPja(string $nama): ?string
    {
        $lok = DB::table('drm_master_lokasi_pja')->where('nama_pja', $nama)->where('aktif', true)->get(['site', 'lokasi']);
        if ($lok->isEmpty()) {
            return null;
        }
        $pusat = $this->simpulPja($nama);
        $dl = DB::table('drm_master_dl')->where('aktif', true)->where(function ($q) use ($lok) {
            foreach ($lok as $l) {
                $q->orWhere(fn ($w) => $w->where('site', $l->site)->where('lokasi', $l->lokasi));
            }
        })->get();
        $grup = DB::table('drm_master_grup')->whereIn('gid', $dl->pluck('gid')->unique())->pluck('nama', 'gid');
        foreach ($this->batasi('dl', $dl) as $d) {
            $nd = $this->simpulDl($d);
            $this->sambung($nd, $pusat);
            if (isset($grup[$d->gid])) {
                $this->sambung($this->simpulGrup((int) $d->gid, (string) $grup[$d->gid]), $nd);
            }
        }
        $akt = DB::table('drm_master_grup_baris as gb')->join('drm_master_baris as b', 'b.ri', '=', 'gb.ri')->join('drm_master_aktivitas as a', 'a.aid', '=', 'b.aid')
            ->whereIn('gb.gid', $grup->keys())->selectRaw('gb.gid, a.aid, a.aktivitas, a.kelompok, count(*) n')->groupBy('gb.gid', 'a.aid', 'a.aktivitas', 'a.kelompok')->orderByDesc('n')->get();
        foreach ($this->batasi('aktivitas', $akt) as $a) {
            $this->sambung($this->simpulAktivitas((int) $a->aid, (string) $a->aktivitas, (string) $a->kelompok), $this->simpulGrup((int) $a->gid, (string) $grup[$a->gid]));
        }

        return $pusat;
    }

    /**
     * Gabungan dari dropdown multi-select "Tandai" di toolbar (keputusan user 27 Nov 2026, dikoreksi dari
     * percobaan pertama yang cuma menyorot node yang kebetulan sudah tampil — ternyata maksud user, centang
     * banyak nilai harus benar-benar MENGGABUNGKAN pohon relasi semuanya ke satu kanvas). Memanggil dariAktivitas/
     * dariGrup/dariDl/dariPja berkali-kali TANPA reset $this->node/$this->edge di antaranya — method-method itu
     * sendiri sudah idempoten lewat simpul()/sambung() yang memoized per key, jadi gabungannya otomatis tanpa
     * duplikat, tidak perlu logika penggabungan khusus.
     *
     * @param  array{aktivitas?: list<string>, grup?: list<string>, dl?: list<string>, pja?: list<string>}  $jenisId  id per kategori (lihat daftarDropdown)
     */
    private function dariMulti(array $jenisId): ?string
    {
        // PENTING: setiap item HARUS tetap dipanggil meski $pusat sudah terisi — `??=` cuma menahan hasil
        // panggilan sebelumnya, BUKAN alasan untuk melewati panggilan berikutnya (kalau pakai `$pusat ??= f()`
        // langsung, `f()` untuk item ke-2 dst tidak akan pernah jalan karena null-coalescing-assignment
        // short-circuit begitu $pusat sudah bukan null — baris itu sendiri yang membangun pohonnya).
        $pusat = null;
        foreach ((array) ($jenisId['aktivitas'] ?? []) as $aid) {
            $hasil = $this->dariAktivitas((int) $aid);
            $pusat ??= $hasil;
        }
        foreach ((array) ($jenisId['grup'] ?? []) as $gid) {
            $hasil = $this->dariGrup((int) $gid);
            $pusat ??= $hasil;
        }
        foreach ((array) ($jenisId['dl'] ?? []) as $id) {
            $hasil = $this->dariDl((int) $id);
            $pusat ??= $hasil;
        }
        foreach ((array) ($jenisId['pja'] ?? []) as $nama) {
            $hasil = $this->dariPja((string) $nama);
            $pusat ??= $hasil;
        }

        return $pusat;
    }

    /**
     * Site dan/atau Perusahaan (filter toolbar korelasi, keputusan user 26 Nov 2026): BUKAN menyaring simpul di
     * alur yang sedang terbuka, tapi alur baru dari nol dibaca dari sisi lokasi — Site/Perusahaan (lewat PJA di
     * drm_master_lokasi_pja, PERUSAHAAN ASLI bukan nama_pja) → Detail Lokasi yang cocok → Grup Lokasi → Bahaya
     * (lewat Sub aktivitas & Label SAP, sama pola dengan dariBaris) → Aktivitas yang berlaku (dibaca ke kiri,
     * sama pola dengan dariGrup/dariPja). DL/PJA DIBATASI ke yang benar-benar cocok site/perusahaan (TIDAK
     * memakai lanjutDariGrup — itu akan menampilkan semua DL grup termasuk site lain).
     * $id format "site||perusahaan" (salah satu boleh kosong = tidak dibatasi; tidak boleh dua-duanya kosong).
     */
    private function dariSitePerusahaan(string $id): ?string
    {
        [$site, $perusahaan] = array_pad(explode(self::PEMISAH_SITE_PERUSAHAAN, $id, 2), 2, '');
        $site = trim($site);
        $perusahaan = trim($perusahaan);
        if ($site === '' && $perusahaan === '') {
            return null;
        }
        // PJA Child disaring ke perusahaan ini saja (lihat simpulPja/$filterPerusahaanAnak) — ditemukan user 26
        // Nov 2026: tanpa ini, PJA Child yang tampil masih ikut subkontraktor lain yang kebetulan satu induk PJA.
        $this->filterPerusahaanAnak = $perusahaan !== '' ? $perusahaan : null;
        $q = DB::table('drm_master_lokasi_pja')->where('aktif', true);
        if ($site !== '') {
            $q->where('site', $site);
        }
        // PJA yang melekat ke tiap lokasi TIDAK disaring perusahaan di sini (koreksi user 27 Nov 2026) — PJA
        // menaungi lokasi fisik terlepas dari subkontraktor mana yang ditugaskan di detlok mana saja di
        // bawahnya; perusahaan diputuskan PER DETLOK lewat drm_master_lokasi_pic (PIC resmi) di bawah, bukan
        // lewat kolom perusahaan (personil) milik PJA induknya.
        $pjaPerLokasi = [];
        foreach ($q->get(['site', 'lokasi', 'nama_pja']) as $p) {
            $k = mb_strtolower(trim((string) $p->site).'|'.trim((string) $p->lokasi));
            $pjaPerLokasi[$k][(string) $p->nama_pja] = true;
        }
        if ($pjaPerLokasi === []) {
            return null;
        }
        $dl = DB::table('drm_master_dl')->where('aktif', true)->get()
            ->filter(fn ($d) => isset($pjaPerLokasi[mb_strtolower(trim((string) $d->site).'|'.trim((string) $d->lokasi))]))->values();
        if ($perusahaan !== '') {
            // PIC RESMI per detlok (drm_master_lokasi_pic, dari PIC_Detail_Lokasi ClickHouse — keputusan user 27
            // Nov 2026, ditemukan lewat filter Site=BMO1 x Perusahaan=BUMA: Detail Lokasi "(B PMO) Warehouse KDC"
            // masih tampil padahal PIC resminya KDC, bukan BUMA, cuma karena PJA induknya punya personil BUMA
            // di detlok LAIN yang dinaunginya). Detlok tanpa data PIC sama sekali tidak ikut (bukan fallback ke
            // personil) — "ganti total", bukan berdampingan.
            $picPerDetlok = [];
            foreach (DB::table('drm_master_lokasi_pic')->where('aktif', true)->select('site', 'lokasi', 'detail', 'pic_perusahaan')->get() as $p) {
                $k = mb_strtolower(trim((string) $p->site).'|'.trim((string) $p->lokasi).'|'.trim((string) $p->detail));
                $picPerDetlok[$k] = DrmMasterDb::pisahLabel($p->pic_perusahaan ?? '');
            }
            $dl = $dl->filter(function ($d) use ($picPerDetlok, $perusahaan) {
                $k = mb_strtolower(trim((string) $d->site).'|'.trim((string) $d->lokasi).'|'.trim((string) $d->detail));

                return in_array($perusahaan, $picPerDetlok[$k] ?? [], true);
            })->values();
        }
        if ($dl->isEmpty()) {
            return null;
        }
        $gids = $dl->pluck('gid')->unique()->values()->all();
        $namaGrup = DB::table('drm_master_grup')->whereIn('gid', $gids)->pluck('nama', 'gid');
        // Sub aktivitas, Bahaya-risiko & Label SAP (sebelumnya hilang di alur ini — cuma Aktivitas langsung ke
        // Grup — ditemukan user 26 Nov 2026 lewat filter Site: kolom Bahaya-risiko/Label SAP kosong).
        $grupPerRi = [];
        foreach (DB::table('drm_master_grup_baris')->whereIn('gid', $gids)->get(['ri', 'gid']) as $g) {
            $grupPerRi[(int) $g->ri][] = (int) $g->gid;
        }
        if ($grupPerRi === []) {
            return null;
        }
        // Jatah round-robin ADIL lintas grup (bukan flat global take(60), dan BUKAN pula jatah tetap per grup —
        // dua-duanya sempat dicoba dan sama-sama salah, lihat ambilAdilLintasGrup) supaya grup yang kebagian
        // dikit baris tidak sampai NOL (ditemukan user 26 Nov 2026: grup "Hauling Road" kosong total di kolom
        // Aktivitas/Sub/Bahaya-risiko/Label SAP — "Berasal dari: —"), TANPA memotong situs yang cuma punya
        // sedikit grup jadi terlalu kecil (ditemukan user 26 Nov 2026 juga: EKSPLORASI cuma 1 grup tapi 63
        // baris mencakup 15 aktivitas — jatah tetap 12/grup sempat memotongnya jadi cuma ~2 aktivitas, lebih
        // parah dari sebelum ada perbaikan apa pun).
        $riPerGid = [];
        foreach ($grupPerRi as $ri => $gidList) {
            foreach ($gidList as $gid) {
                $riPerGid[$gid][] = $ri;
            }
        }
        $risTerpilih = array_values(array_unique($this->ambilAdilLintasGrup('bahaya', $riPerGid, self::MAKS)));
        $baris = DB::table('drm_master_baris')->whereIn('ri', $risTerpilih)->get();
        $akt = DB::table('drm_master_aktivitas')->whereIn('aid', $baris->pluck('aid')->unique())->get()->keyBy('aid');
        $pusat = null;
        foreach ($baris as $b) {
            $a = $akt[$b->aid] ?? null;
            $na = $this->simpulAktivitas((int) $b->aid, (string) ($a->aktivitas ?? $b->aktivitas), (string) ($a->kelompok ?? 'K0'));
            $pusat ??= $na;
            $ns = $this->simpulSub((int) $b->aid, (string) $b->sub);
            $nb = $this->simpulBahaya($b);
            $this->sambung($na, $ns);
            $this->sambung($ns, $nb);
            foreach ($this->labelBaris($b) as [$jenis, $teks]) {
                $this->sambung($nb, $this->simpulLabel($jenis, $teks));
            }
            foreach ($grupPerRi[(int) $b->ri] ?? [] as $gid) {
                if (! isset($namaGrup[$gid])) {
                    continue;
                }
                $this->sambung($nb, $this->simpulGrup($gid, (string) $namaGrup[$gid]));
            }
        }
        if ($pusat === null) {
            return null;
        }
        // Round-robin adil juga (sama alasannya dengan bahaya di atas) — satu DL cuma milik satu grup, jadi
        // aman di-groupBy tanpa duplikat lintas grup (beda dengan ri yang bisa dipakai >1 grup).
        foreach ($this->ambilAdilLintasGrup('dl', $dl->groupBy('gid')->all(), self::MAKS) as $d) {
            if (! isset($namaGrup[$d->gid])) {
                continue;
            }
            $nd = $this->simpulDl($d);
            $this->sambung($this->simpulGrup((int) $d->gid, (string) $namaGrup[$d->gid]), $nd);
            $k = mb_strtolower(trim((string) $d->site).'|'.trim((string) $d->lokasi));
            foreach (array_keys($pjaPerLokasi[$k] ?? []) as $nama) {
                $this->sambung($nd, $this->simpulPja($nama));
            }
        }

        return $pusat;
    }

    /**
     * Jatah ROUND-ROBIN lintas grup (giliran satu-satu per grup sampai jatah tiap grup habis atau total
     * $maksTotal tercapai) — dipakai dariSitePerusahaan() untuk bahaya-risiko (jenisPotong='bahaya') dan Detail
     * Lokasi (jenisPotong='dl'). Dua pendekatan lain sudah dicoba dan sama-sama salah (keduanya ditemukan user
     * 26 Nov 2026):
     * - Flat global take($maksTotal) SEBELUM tahu baris milik grup mana: grup yang baris-nya kebetulan "telat"
     *   di urutan hasil query bisa kebagian NOL (kasus grup "Hauling Road").
     * - Jatah TETAP per grup (mis. 12/grup): situs dengan SEDIKIT grup tapi BANYAK baris per grup jadi
     *   terpotong parah dibanding sebelumnya (kasus EKSPLORASI: 1 grup, 63 baris/15 aktivitas, terpotong jadi
     *   ~12 baris/2 aktivitas — lebih parah dari flat global take(60) yang justru meloloskan 60).
     * Round-robin menjamin keduanya: grup manapun yang masih punya kandidat selalu dapat giliran (tidak nol
     * selama $maksTotal >= jumlah grup), DAN grup yang sendirian (sedikit grup lain berebut jatah) tetap bisa
     * tumbuh sampai $maksTotal — persis seperti flat global take() untuk kasus itu.
     *
     * @param  array<int|string, iterable<mixed>>  $perGid  gid => daftar item milik grup itu (urutan apa adanya)
     * @return list<mixed>
     */
    private function ambilAdilLintasGrup(string $jenisPotong, array $perGid, int $maksTotal): array
    {
        $antrian = array_values(array_filter(array_map(fn ($items) => array_values(is_array($items) ? $items : iterator_to_array($items)), $perGid), fn ($items) => $items !== []));
        $totalKandidat = array_sum(array_map('count', $antrian));
        $hasil = [];
        while ($antrian !== [] && count($hasil) < $maksTotal) {
            foreach ($antrian as $i => $items) {
                if (count($hasil) >= $maksTotal) {
                    break;
                }
                $hasil[] = array_shift($items);
                if ($items === []) {
                    unset($antrian[$i]);
                } else {
                    $antrian[$i] = $items;
                }
            }
            $antrian = array_values($antrian);
        }
        if ($totalKandidat > $maksTotal) {
            $this->potong[$jenisPotong] = ($this->potong[$jenisPotong] ?? 0) + $totalKandidat - $maksTotal;
        }

        return $hasil;
    }

    /** Label SAP: baris yang ditangkap label ini, dengan aktivitas & sub di kiri dan grup lokasi di kanan. */
    private function dariLabel(string $jenis, string $teks): ?string
    {
        $baris = $this->barisDenganLabel($jenis, $teks);
        if ($baris->isEmpty()) {
            return null;
        }
        $pusat = $this->simpulLabel($jenis, $teks);
        $akt = DB::table('drm_master_aktivitas')->whereIn('aid', $baris->pluck('aid')->unique())->get()->keyBy('aid');
        $grupPerRi = $this->grupPerBaris($baris->pluck('ri')->all());
        $grupDipakai = [];
        foreach ($this->batasi('bahaya', $baris) as $b) {
            $a = $akt[$b->aid] ?? null;
            $na = $this->simpulAktivitas((int) $b->aid, (string) ($a->aktivitas ?? $b->aktivitas), (string) ($a->kelompok ?? 'K0'));
            $ns = $this->simpulSub((int) $b->aid, (string) $b->sub);
            $nb = $this->simpulBahaya($b);
            $this->sambung($na, $ns);
            $this->sambung($ns, $nb);
            $this->sambung($nb, $pusat);
            foreach ($grupPerRi[(int) $b->ri] ?? [] as [$gid, $nama]) {
                $this->sambung($nb, $this->simpulGrup($gid, $nama));
                $grupDipakai[$gid] = true;
            }
        }
        $this->lanjutDariGrup(array_keys($grupDipakai));

        return $pusat;
    }

    /** Grup → detail lokasi (maks per grup) → PJA pemiliknya. */
    private function lanjutDariGrup(array $gids, int $maksPerGrup = self::MAKS_DL_PER_GRUP): void
    {
        if ($gids === []) {
            return;
        }
        $nama = DB::table('drm_master_grup')->whereIn('gid', $gids)->pluck('nama', 'gid');
        $semua = collect();
        foreach (DB::table('drm_master_dl')->whereIn('gid', $gids)->where('aktif', true)->orderBy('site')->orderBy('detail')->get()->groupBy('gid') as $gid => $dl) {
            foreach ($this->batasi('dl', $dl, $maksPerGrup) as $d) {
                $this->sambung($this->simpulGrup((int) $gid, (string) $nama[$gid]), $this->simpulDl($d));
                $semua->push($d);
            }
        }
        foreach ($semua->groupBy(fn ($d) => mb_strtolower(trim($d->site).'|'.trim($d->lokasi))) as $kelompokDl) {
            foreach ($this->pjaDariDl($kelompokDl) as $p) {
                foreach ($kelompokDl as $d) {
                    $this->sambung($this->simpulDl($d), $this->simpulPja($p));
                }
            }
        }
    }

    // ------------------------------------------------------------ simpul

    private function simpulAktivitas(int $aid, string $nama, string $kelompok, ?int $n = null): string
    {
        $this->namaKelompok ??= DB::table('drm_master_kelompok')->pluck('nama', 'kode')->all();

        return $this->simpul('a:'.$aid, 'aktivitas', 0, (string) $aid, $nama, (string) ($this->namaKelompok[$kelompok] ?? $kelompok).($n !== null ? ' · '.$n.' bahaya di grup' : ''));
    }

    /** id = "aid|teks sub persis" (dipakai modal edit untuk mengganti nama sub). */
    private function simpulSub(int $aid, string $sub): string
    {
        $tampil = trim($sub) === '' ? '(tanpa sub aktivitas)' : $sub;

        return $this->simpul('s:'.$aid.':'.md5(mb_strtolower($sub)), 'sub', 1, $aid.'|'.$sub, $tampil, 'Sub aktivitas');
    }

    private function simpulBahaya(object $b): string
    {
        $st = $this->statusSap()['hid'][(string) $b->ri] ?? null;

        return $this->simpul('b:'.$b->ri, 'bahaya', 2, (string) $b->ri, (string) $b->bahaya, 'Risiko awal '.($b->kb ?: '-'), ['kb' => (string) $b->kb, 'status' => $st]);
    }

    private function simpulLabel(string $jenis, string $teks): string
    {
        $j = (string) array_search($jenis, self::LABEL_JENIS, true);

        return $this->simpul('l:'.$j.':'.md5($this->kunciLabel($jenis, $teks)), $j, 3, $teks, $teks, self::NAMA_LABEL[$j]);
    }

    private function simpulGrup(int $gid, string $nama): string
    {
        return $this->simpul('g:'.$gid, 'grup', 4, (string) $gid, $nama, 'Grup lokasi');
    }

    private function simpulDl(object $d): string
    {
        return $this->simpul('d:'.$d->id, 'dl', 5, (string) $d->id, (string) $d->detail, $d->site.' · '.$d->lokasi,
            ['site' => (string) $d->site, 'pic' => $this->picUntukDetlok((string) $d->site, (string) $d->lokasi, (string) $d->detail)]);
    }

    /**
     * PIC Perusahaan RESMI per detlok (drm_master_lokasi_pic, dari PIC_Detail_Lokasi ClickHouse — lihat migrasi
     * 2026_11_27_100000, BUKAN dari personil PJA/Mitra sejak koreksi 27 Nov 2026) untuk Detail Lokasi di diagram
     * Alur Baseline. Di-cache sekali per alur(), bukan query per simpul.
     *
     * @return list<string>
     */
    private function picUntukDetlok(string $site, string $lokasi, string $detail): array
    {
        if ($this->picPerLokasi === null) {
            $this->picPerLokasi = [];
            foreach (DB::table('drm_master_lokasi_pic')->where('aktif', true)->select('site', 'lokasi', 'detail', 'pic_perusahaan')->get() as $p) {
                $k = mb_strtolower(trim((string) $p->site).'|'.trim((string) $p->lokasi).'|'.trim((string) $p->detail));
                $this->picPerLokasi[$k] = DrmMasterDb::pisahLabel($p->pic_perusahaan ?? '');
            }
        }

        return $this->picPerLokasi[mb_strtolower(trim($site).'|'.trim($lokasi).'|'.trim($detail))] ?? [];
    }

    /**
     * Simpul PJA; menyambung PJA Child-nya (Mitra, satu level) sekali saja saat simpul ini PERTAMA dibuat —
     * simpulPja() dipanggil berkali-kali untuk PJA yang sama dari banyak tempat (lanjutDariGrup per DL, dariPja,
     * dariSitePerusahaan, dst), jadi wiring anak tidak boleh diulang atau "potong" (batasi()) akan salah hitung.
     */
    private function simpulPja(string $nama): string
    {
        $kunci = 'p:'.md5($nama);
        $baru = ! isset($this->node[$kunci]);
        $kunci = $this->simpul($kunci, 'pja', 6, $nama, $nama, 'PJA', ['perusahaan' => $this->perusahaanUntukPja($nama)]);
        if ($baru) {
            $anak = $this->anakUntukPja($nama);
            // Filter Perusahaan aktif (lihat $filterPerusahaanAnak): cuma anak yang perusahaan LANGSUNGnya
            // sendiri cocok — bukan semua anak PJA induk ini (ditemukan user 26 Nov 2026: filter BUMA BMO1
            // masih menampilkan anak dari PT lain yang kebetulan satu induk).
            if ($this->filterPerusahaanAnak !== null) {
                $anak = array_values(array_filter(
                    $anak,
                    fn ($nm) => in_array($this->filterPerusahaanAnak, $this->perusahaanLangsungUntukPja($nm), true)
                ));
            }
            foreach ($this->batasi('pja_anak', $anak, self::MAKS_ANAK_PER_PJA) as $nm) {
                $this->sambung($kunci, $this->simpulPjaAnak($nm));
            }
        }

        return $kunci;
    }

    /** Leaf, bukan fokus (lihat FOKUS di korelasi.blade.php) — identitas PJA anaknya sendiri, bukan atribut gabungan. */
    private function simpulPjaAnak(string $nama): string
    {
        return $this->simpul('pa:'.md5($nama), 'pja_anak', 7, $nama, $nama, 'PJA Child');
    }

    /**
     * Nama perusahaan (BUKAN nama PJA/nama_pja — itu judul dokumen, bukan perusahaan; lihat migrasi
     * 2026_11_24_100000_add_perusahaan_to_drm_master_lokasi_pja_table) untuk filter Perusahaan di diagram.
     * Satu nama PJA bisa dipakai di banyak lokasi, jadi digabung semua perusahaan yang pernah tercatat untuknya
     * (di-cache sekali per alur(), bukan query per simpul).
     *
     * @return list<string>
     */
    private function perusahaanUntukPja(string $nama): array
    {
        if ($this->perusahaanPerPja === null) {
            $this->perusahaanPerPja = [];
            foreach (DB::table('drm_master_lokasi_pja')->where('aktif', true)->select('nama_pja', 'perusahaan')->get() as $p) {
                $k = mb_strtolower(trim((string) $p->nama_pja));
                foreach (DrmMasterDb::pisahLabel($p->perusahaan ?? '') as $nm) {
                    $this->perusahaanPerPja[$k][$nm] = true;
                }
            }
        }
        $daftar = array_keys($this->perusahaanPerPja[mb_strtolower(trim($nama))] ?? []);
        sort($daftar, SORT_NATURAL | SORT_FLAG_CASE);

        return $daftar;
    }

    /**
     * Nama PJA Child (Mitra, satu level, id_parent di obds.m_pja) untuk kolom "PJA Child" di diagram (keputusan
     * user 26 Nov 2026). Sumbernya kolom pja_anak (lihat migrasi 2026_11_26_100000_add_pja_anak_to_drm_master_
     * lokasi_pja_table), sama pola cache dengan perusahaanUntukPja().
     *
     * @return list<string>
     */
    private function anakUntukPja(string $nama): array
    {
        if ($this->anakPerPja === null) {
            $this->anakPerPja = [];
            foreach (DB::table('drm_master_lokasi_pja')->where('aktif', true)->select('nama_pja', 'pja_anak')->get() as $p) {
                $k = mb_strtolower(trim((string) $p->nama_pja));
                foreach (DrmMasterDb::pisahLabel($p->pja_anak ?? '') as $nm) {
                    $this->anakPerPja[$k][$nm] = true;
                }
            }
        }
        $daftar = array_keys($this->anakPerPja[mb_strtolower(trim($nama))] ?? []);
        sort($daftar, SORT_NATURAL | SORT_FLAG_CASE);

        return $daftar;
    }

    /**
     * Perusahaan LANGSUNG (bukan gabungan induk/anak) milik nama PJA ini sendiri — dari drm_master_pja_perusahaan
     * (keputusan user 26 Nov 2026). Dipakai simpulPja() untuk menyaring PJA Child ke perusahaan filter yang
     * sedang aktif ($filterPerusahaanAnak); bisa dipakai untuk PJA induk maupun anak (satu tabel, satu pola).
     *
     * @return list<string>
     */
    private function perusahaanLangsungUntukPja(string $nama): array
    {
        if ($this->perusahaanLangsungPerPja === null) {
            $this->perusahaanLangsungPerPja = [];
            foreach (DB::table('drm_master_pja_perusahaan')->select('nama_pja', 'perusahaan')->get() as $p) {
                $k = mb_strtolower(trim((string) $p->nama_pja));
                foreach (DrmMasterDb::pisahLabel($p->perusahaan ?? '') as $nm) {
                    $this->perusahaanLangsungPerPja[$k][$nm] = true;
                }
            }
        }

        return array_keys($this->perusahaanLangsungPerPja[mb_strtolower(trim($nama))] ?? []);
    }

    /** @param array<string, mixed> $ekstra */
    private function simpul(string $kunci, string $jenis, int $kolom, string $id, string $label, string $sub, array $ekstra = []): string
    {
        $this->node[$kunci] ??= ['key' => $kunci, 'jenis' => $jenis, 'kolom' => $kolom, 'id' => $id, 'label' => $label, 'sub' => $sub] + $ekstra;

        return $kunci;
    }

    /** Relasi berarah kiri → kanan (dari kolom lebih kecil). */
    private function sambung(string $a, string $b): void
    {
        if ($a === $b) {
            return;
        }
        [$from, $to] = ($this->node[$a]['kolom'] ?? 0) <= ($this->node[$b]['kolom'] ?? 0) ? [$a, $b] : [$b, $a];
        $this->edge[$from.'|'.$to] ??= ['from' => $from, 'to' => $to];
    }

    /** Hapus semua simpul satu jenis beserta relasinya (dipakai fokus detail lokasi). */
    private function buangKolom(string $jenis): void
    {
        foreach ($this->node as $k => $n) {
            if ($n['jenis'] === $jenis) {
                unset($this->node[$k]);
            }
        }
        $this->edge = array_filter($this->edge, fn ($e) => isset($this->node[$e['from']], $this->node[$e['to']]));
        unset($this->potong[$jenis]);
    }

    private function batasi(string $jenis, $rows, int $maks = self::MAKS): Collection
    {
        $rows = collect($rows)->values();
        if ($rows->count() > $maks) {
            $this->potong[$jenis] = ($this->potong[$jenis] ?? 0) + $rows->count() - $maks;
        }

        return $rows->take($maks);
    }

    // ------------------------------------------------------------ relasi master

    /** @return Collection<int, string> nama PJA pemilik detail lokasi (lewat site + lokasi, M07/M08) */
    private function pjaDariDl($dl): Collection
    {
        $kunci = collect($dl)->map(fn ($d) => mb_strtolower(trim($d->site).'|'.trim($d->lokasi)))->unique()->flip();
        if ($kunci->isEmpty()) {
            return collect();
        }

        return DB::table('drm_master_lokasi_pja')->where('aktif', true)->whereIn('site', collect($dl)->pluck('site')->unique())->get()
            ->filter(fn ($p) => isset($kunci[mb_strtolower(trim($p->site).'|'.trim($p->lokasi))]))->pluck('nama_pja')->unique()->sort()->values();
    }

    /** @return array<int, list<array{0: int, 1: string}>> ri => [[gid, nama grup]] */
    private function grupPerBaris(array $ri): array
    {
        $hasil = [];
        foreach (DB::table('drm_master_grup_baris as g')->join('drm_master_grup as m', 'm.gid', '=', 'g.gid')->whereIn('g.ri', $ri)->get(['g.ri', 'm.gid', 'm.nama']) as $r) {
            $hasil[(int) $r->ri][] = [(int) $r->gid, (string) $r->nama];
        }

        return $hasil;
    }

    /** @return array<int, list<array{0: int, 1: string}>> aid => [[gid, nama grup]] */
    private function grupPerAktivitas(array $aids): array
    {
        $hasil = [];
        foreach (DB::table('drm_master_grup_baris as g')->join('drm_master_baris as b', 'b.ri', '=', 'g.ri')->join('drm_master_grup as m', 'm.gid', '=', 'g.gid')
            ->whereIn('b.aid', $aids)->distinct()->get(['b.aid', 'm.gid', 'm.nama']) as $r) {
            $hasil[(int) $r->aid][] = [(int) $r->gid, (string) $r->nama];
        }

        return $hasil;
    }

    /** @return list<array{0: string, 1: string}> [OBS/OAK/KET, teks label] dari kolom item sumber baris */
    private function labelBaris(object $b): array
    {
        if (! DrmMasterDb::labelDiBaris()) {
            return [];
        }
        $hasil = [];
        foreach (DrmMasterDb::KOLOM_LABEL as $jenis => $kolom) {
            foreach (DrmMasterDb::pisahLabel($b->{$kolom} ?? null) as $l) {
                $hasil[] = [$jenis, $l];
            }
        }

        return $hasil;
    }

    /** Baris HIRA yang memuat label ini (normalisasi sama dengan sync). */
    private function barisDenganLabel(string $jenis, string $teks): Collection
    {
        if (! DrmMasterDb::labelDiBaris()) {
            return collect();
        }
        $kolom = DrmMasterDb::KOLOM_LABEL[$jenis];
        $kunci = $this->kunciLabel($jenis, $teks);
        // Saring kasar dengan kata terpanjang, lalu cocokkan persis per label.
        $kata = collect(preg_split('/\W+/u', $teks))->sortByDesc(fn ($k) => mb_strlen($k))->first() ?: $teks;

        return DB::table('drm_master_baris')->where($kolom, 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $kata).'%')->orderBy('aid')->get()
            ->filter(fn ($b) => collect(DrmMasterDb::pisahLabel($b->{$kolom}))->contains(fn ($l) => $this->kunciLabel($jenis, $l) === $kunci))->values();
    }

    private function kunciLabel(string $jenis, string $teks): string
    {
        if ($jenis === 'KET') {
            [$k, $sk] = DrmMasterDb::pisahKet($teks);

            return 'KET|'.DrmMaster::norm($k).' / '.DrmMaster::norm($sk);
        }

        return $jenis.'|'.DrmMaster::normTeks($teks);
    }

    /** @return list<array{jenis: string, id: string, label: string, sub: string}> */
    private function cariLabel(string $q): array
    {
        if (! DrmMasterDb::labelDiBaris()) {
            return [];
        }
        $hasil = [];
        foreach (DrmMasterDb::KOLOM_LABEL as $jenis => $kolom) {
            $temu = [];
            foreach (DB::table('drm_master_baris')->where($kolom, 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%')->limit(200)->pluck($kolom) as $isi) {
                foreach (DrmMasterDb::pisahLabel($isi) as $l) {
                    if (mb_stripos($l, $q) !== false) {
                        $temu[$this->kunciLabel($jenis, $l)] ??= $l;
                    }
                }
            }
            $j = (string) array_search($jenis, self::LABEL_JENIS, true);
            foreach (array_slice(array_values($temu), 0, 8) as $l) {
                $hasil[] = ['jenis' => $j, 'id' => $l, 'label' => $l, 'sub' => self::NAMA_LABEL[$j]];
            }
        }

        return $hasil;
    }

    /**
     * Status SAP per baris HIRA di cakupan seluruh BC (rincian.json lingkup ALL), minggu penuh terakhir.
     * K = terkontrol, G = ada gap, T = tidak teridentifikasi; status terburuk bila baris ada di >1 kelompok.
     *
     * @return array{minggu: ?string, hid: array<string, string>}
     */
    private function statusSap(): array
    {
        if ($this->status !== null) {
            return $this->status;
        }
        $path = $this->repo->pathRincian();
        if (! is_file($path)) {
            return $this->status = ['minggu' => null, 'hid' => []];
        }
        $hitung = function () use ($path) {
            $r = json_decode((string) file_get_contents($path), true);
            $minggu = (array) ($r['minggu'] ?? []);
            $awal = (string) @file_get_contents($this->repo->pathMeta(), false, null, 0, 2048);
            $sampai = preg_match('/"sampai"\s*:\s*"(\d{4}-\d{2}-\d{2})"/', $awal, $m) ? $m[1] : now()->toDateString();
            $w = DrmRaporPja::mingguDefault($minggu, $sampai);
            $hid = [];
            foreach ((array) ($r['lingkup']['ALL']['u'] ?? []) as $x) {
                $kode = (string) ($x[5] ?? '');
                $k = $kode[$w] ?? 'T';
                $lama = $hid[(string) $x[1]] ?? null;
                $hid[(string) $x[1]] = $lama === null ? $k : (str_contains($lama.$k, 'G') ? 'G' : (str_contains($lama.$k, 'T') ? 'T' : 'K'));
            }

            return ['minggu' => $minggu[$w] ?? null, 'hid' => $hid];
        };
        try {
            return $this->status = Cache::remember('drm-korelasi-status:'.filemtime($path), now()->addDay(), $hitung);
        } catch (Throwable) {
            return $this->status = $hitung();
        }
    }
}
