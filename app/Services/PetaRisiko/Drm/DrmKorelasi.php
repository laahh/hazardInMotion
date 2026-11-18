<?php

declare(strict_types=1);

namespace App\Services\PetaRisiko\Drm;

use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Peta korelasi master DRM 2.1 (graf): grup lokasi ↔ detail lokasi ↔ PJA, grup ↔ aktivitas HIRA (M05) ↔ kelompok (M02),
 * aktivitas ↔ bahaya-risiko (baris HIRA) ↔ label SAP (item sumber Observasi / OAK / Ketidaksesuaian).
 * Mode fokus: satu simpul pusat + tetangga yang relevan, dibatasi per jenis supaya tetap terbaca.
 * Status SAP bahaya (K/G/T) dari snapshot terakhir (rincian.json cakupan ALL, minggu penuh terakhir).
 */
final class DrmKorelasi
{
    public const JENIS = ['grup', 'dl', 'pja', 'kelompok', 'aktivitas', 'bahaya', 'obs', 'oak', 'ket'];

    private const MAKS = 60;

    private const LABEL_JENIS = ['obs' => 'OBS', 'oak' => 'OAK', 'ket' => 'KET'];

    /** @var array<string, array<string, mixed>> */
    private array $node = [];

    /** @var array<string, array{from: string, to: string}> */
    private array $edge = [];

    /** @var array<string, int> jenis => jumlah yang tidak ditampilkan */
    private array $potong = [];

    /** @var array<string, string>|null kode kelompok => nama */
    private ?array $namaKelompok = null;

    /** @var array{minggu: ?string, hid: array<string, string>}|null */
    private ?array $status = null;

    public function __construct(private readonly DrmRepository $repo)
    {
    }

    /** Pusat bawaan: grup lokasi dengan baris HIRA terbanyak. */
    public function pusatBawaan(): array
    {
        $gid = DB::table('drm_master_grup_baris')->selectRaw('gid, count(*) n')->groupBy('gid')->orderByDesc('n')->value('gid');

        return ['jenis' => 'grup', 'id' => (string) ($gid ?? 1)];
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
        $tambah('grup', DB::table('drm_master_grup')->where('nama', 'like', $pola)->limit(8)->get(), fn ($r) => ['id' => (string) $r->gid, 'label' => $r->nama, 'sub' => 'Grup lokasi']);
        $tambah('kelompok', DB::table('drm_master_kelompok')->where('nama', 'like', $pola)->limit(8)->get(), fn ($r) => ['id' => $r->kode, 'label' => $r->nama, 'sub' => 'Kelompok aktivitas']);
        $tambah('aktivitas', DB::table('drm_master_aktivitas')->where('aktivitas', 'like', $pola)->limit(8)->get(), fn ($r) => ['id' => (string) $r->aid, 'label' => $r->aktivitas, 'sub' => 'Aktivitas HIRA']);
        $tambah('bahaya', DB::table('drm_master_baris')->where('bahaya', 'like', $pola)->limit(8)->get(), fn ($r) => ['id' => (string) $r->ri, 'label' => $r->bahaya, 'sub' => 'Bahaya · '.$r->aktivitas]);
        $tambah('dl', DB::table('drm_master_dl')->where('detail', 'like', $pola)->limit(8)->get(), fn ($r) => ['id' => (string) $r->id, 'label' => $r->detail, 'sub' => $r->site.' · '.$r->lokasi]);
        $tambah('pja', DB::table('drm_master_lokasi_pja')->where('nama_pja', 'like', $pola)->distinct()->limit(8)->pluck('nama_pja'), fn ($n) => ['id' => (string) $n, 'label' => (string) $n, 'sub' => 'PJA']);
        foreach ($this->cariLabel($q) as $l) {
            $hasil[] = $l;
        }

        return $hasil;
    }

    /**
     * Graf fokus untuk satu simpul pusat.
     *
     * @return array{pusat: string, node: list<array<string, mixed>>, edge: list<array{from: string, to: string}>, potong: array<string, int>, minggu: ?string}
     */
    public function graf(string $jenis, string $id): array
    {
        if (! in_array($jenis, self::JENIS, true)) {
            throw new InvalidArgumentException('Jenis simpul tidak dikenal.');
        }
        $this->node = $this->edge = $this->potong = [];
        $pusat = match ($jenis) {
            'grup' => $this->dariGrup((int) $id),
            'dl' => $this->dariDl((int) $id),
            'pja' => $this->dariPja($id),
            'kelompok' => $this->dariKelompok($id),
            'aktivitas' => $this->dariAktivitas((int) $id),
            'bahaya' => $this->dariBahaya((int) $id),
            default => $this->dariLabel(self::LABEL_JENIS[$jenis], $id),
        };
        if ($pusat === null) {
            throw new InvalidArgumentException('Simpul tidak ditemukan di master.');
        }
        $this->node[$pusat]['pusat'] = true;

        return ['pusat' => $pusat, 'node' => array_values($this->node), 'edge' => array_values($this->edge), 'potong' => $this->potong, 'minggu' => $this->statusSap()['minggu']];
    }

    // ------------------------------------------------------------ titik masuk per jenis

    private function dariGrup(int $gid): ?string
    {
        $g = DB::table('drm_master_grup')->where('gid', $gid)->first();
        if ($g === null) {
            return null;
        }
        $pusat = $this->simpulGrup($gid, (string) $g->nama);
        $dl = DB::table('drm_master_dl')->where('gid', $gid)->where('aktif', true)->orderBy('site')->orderBy('detail')->get();
        foreach ($this->batasi('dl', $dl) as $d) {
            $this->sambung($pusat, $this->simpulDl($d));
        }
        foreach ($this->batasi('pja', $this->pjaDariDl($dl)) as $p) {
            $this->sambung($pusat, $this->simpulPja($p));
        }
        $akt = DB::table('drm_master_grup_baris as g')->join('drm_master_baris as b', 'b.ri', '=', 'g.ri')->join('drm_master_aktivitas as a', 'a.aid', '=', 'b.aid')
            ->where('g.gid', $gid)->selectRaw('a.aid, a.aktivitas, a.kelompok, count(*) n')->groupBy('a.aid', 'a.aktivitas', 'a.kelompok')->orderByDesc('n')->get();
        foreach ($this->batasi('aktivitas', $akt) as $a) {
            $na = $this->simpulAktivitas((int) $a->aid, (string) $a->aktivitas, (int) $a->n);
            $this->sambung($pusat, $na);
            $this->sambung($na, $this->simpulKelompok((string) $a->kelompok));
        }

        return $pusat;
    }

    private function dariDl(int $id): ?string
    {
        $d = DB::table('drm_master_dl')->where('id', $id)->first();
        if ($d === null) {
            return null;
        }
        $pusat = $this->simpulDl($d);
        $g = DB::table('drm_master_grup')->where('gid', $d->gid)->first();
        if ($g !== null) {
            $ng = $this->simpulGrup((int) $g->gid, (string) $g->nama);
            $this->sambung($pusat, $ng);
            $akt = DB::table('drm_master_grup_baris as gb')->join('drm_master_baris as b', 'b.ri', '=', 'gb.ri')->join('drm_master_aktivitas as a', 'a.aid', '=', 'b.aid')
                ->where('gb.gid', $g->gid)->selectRaw('a.aid, a.aktivitas, count(*) n')->groupBy('a.aid', 'a.aktivitas')->orderByDesc('n')->get();
            foreach ($this->batasi('aktivitas', $akt, 30) as $a) {
                $this->sambung($ng, $this->simpulAktivitas((int) $a->aid, (string) $a->aktivitas, (int) $a->n));
            }
        }
        foreach ($this->pjaDariDl(collect([$d])) as $p) {
            $this->sambung($pusat, $this->simpulPja($p));
        }

        return $pusat;
    }

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
            $this->sambung($pusat, $nd);
            if (isset($grup[$d->gid])) {
                $this->sambung($nd, $this->simpulGrup((int) $d->gid, (string) $grup[$d->gid]));
            }
        }

        return $pusat;
    }

    private function dariKelompok(string $kode): ?string
    {
        if (! DB::table('drm_master_kelompok')->where('kode', $kode)->exists()) {
            return null;
        }
        $pusat = $this->simpulKelompok($kode);
        $akt = DB::table('drm_master_aktivitas as a')->leftJoin('drm_master_baris as b', 'b.aid', '=', 'a.aid')->where('a.kelompok', $kode)
            ->selectRaw('a.aid, a.aktivitas, count(b.ri) n')->groupBy('a.aid', 'a.aktivitas')->orderByDesc('n')->get();
        $tampil = $this->batasi('aktivitas', $akt);
        $grupPerAkt = $this->grupPerAktivitas($tampil->pluck('aid')->all());
        foreach ($tampil as $a) {
            $na = $this->simpulAktivitas((int) $a->aid, (string) $a->aktivitas, (int) $a->n);
            $this->sambung($pusat, $na);
            foreach ($grupPerAkt[(int) $a->aid] ?? [] as [$gid, $nama]) {
                $this->sambung($na, $this->simpulGrup($gid, $nama));
            }
        }

        return $pusat;
    }

    private function dariAktivitas(int $aid): ?string
    {
        $a = DB::table('drm_master_aktivitas')->where('aid', $aid)->first();
        if ($a === null) {
            return null;
        }
        $baris = DB::table('drm_master_baris')->where('aid', $aid)->orderBy('bahaya')->get();
        $pusat = $this->simpulAktivitas($aid, (string) $a->aktivitas, $baris->count());
        $this->sambung($pusat, $this->simpulKelompok((string) $a->kelompok));
        foreach ($this->grupPerAktivitas([$aid])[$aid] ?? [] as [$gid, $nama]) {
            $this->sambung($pusat, $this->simpulGrup($gid, $nama));
        }
        foreach ($this->batasi('bahaya', $baris) as $b) {
            $nb = $this->simpulBahaya($b);
            $this->sambung($pusat, $nb);
            foreach ($this->labelBaris($b) as [$jenis, $teks]) {
                $this->sambung($nb, $this->simpulLabel($jenis, $teks));
            }
        }

        return $pusat;
    }

    private function dariBahaya(int $ri): ?string
    {
        $b = DB::table('drm_master_baris')->where('ri', $ri)->first();
        if ($b === null) {
            return null;
        }
        $pusat = $this->simpulBahaya($b);
        $a = DB::table('drm_master_aktivitas')->where('aid', $b->aid)->first();
        if ($a !== null) {
            $na = $this->simpulAktivitas((int) $a->aid, (string) $a->aktivitas, null);
            $this->sambung($pusat, $na);
            $this->sambung($na, $this->simpulKelompok((string) $a->kelompok));
        }
        foreach (DB::table('drm_master_grup_baris as g')->join('drm_master_grup as m', 'm.gid', '=', 'g.gid')->where('g.ri', $ri)->get(['m.gid', 'm.nama']) as $g) {
            $this->sambung($pusat, $this->simpulGrup((int) $g->gid, (string) $g->nama));
        }
        // Label SAP baris ini, dan baris lain yang ditangkap label yang sama (ke mana label itu juga mengarah).
        foreach ($this->labelBaris($b) as [$jenis, $teks]) {
            $nl = $this->simpulLabel($jenis, $teks);
            $this->sambung($pusat, $nl);
            foreach ($this->batasi('bahaya', $this->barisDenganLabel($jenis, $teks)->where('ri', '!=', $ri), 6) as $lain) {
                $this->sambung($nl, $this->simpulBahaya($lain));
            }
        }

        return $pusat;
    }

    private function dariLabel(string $jenis, string $teks): ?string
    {
        $baris = $this->barisDenganLabel($jenis, $teks);
        $pusat = $this->simpulLabel($jenis, $teks);
        $aktNama = DB::table('drm_master_aktivitas')->whereIn('aid', $baris->pluck('aid')->unique())->pluck('aktivitas', 'aid');
        foreach ($this->batasi('bahaya', $baris) as $b) {
            $nb = $this->simpulBahaya($b);
            $this->sambung($pusat, $nb);
            if (isset($aktNama[$b->aid])) {
                $this->sambung($nb, $this->simpulAktivitas((int) $b->aid, (string) $aktNama[$b->aid], null));
            }
        }

        return $pusat;
    }

    // ------------------------------------------------------------ simpul

    private function simpulGrup(int $gid, string $nama): string
    {
        return $this->simpul('g:'.$gid, 'grup', (string) $gid, $nama, 'Grup lokasi');
    }

    private function simpulDl(object $d): string
    {
        return $this->simpul('d:'.$d->id, 'dl', (string) $d->id, (string) $d->detail, $d->site.' · '.$d->lokasi);
    }

    private function simpulPja(string $nama): string
    {
        return $this->simpul('p:'.md5($nama), 'pja', $nama, $nama, 'PJA');
    }

    private function simpulKelompok(string $kode): string
    {
        $this->namaKelompok ??= DB::table('drm_master_kelompok')->pluck('nama', 'kode')->all();

        return $this->simpul('k:'.$kode, 'kelompok', $kode, (string) ($this->namaKelompok[$kode] ?? $kode), 'Kelompok aktivitas');
    }

    private function simpulAktivitas(int $aid, string $nama, ?int $n): string
    {
        return $this->simpul('a:'.$aid, 'aktivitas', (string) $aid, $nama, 'Aktivitas HIRA'.($n !== null ? ' · '.$n.' bahaya' : ''));
    }

    private function simpulBahaya(object $b): string
    {
        $st = $this->statusSap()['hid'][(string) $b->ri] ?? null;

        return $this->simpul('b:'.$b->ri, 'bahaya', (string) $b->ri, (string) $b->bahaya, trim($b->sub.' · '.$b->kb, ' ·'), ['kb' => (string) $b->kb, 'status' => $st]);
    }

    private function simpulLabel(string $jenis, string $teks): string
    {
        $j = array_search($jenis, self::LABEL_JENIS, true);
        $nama = ['obs' => 'Label Observasi', 'oak' => 'Label OAK', 'ket' => 'Label Ketidaksesuaian'][$j];

        return $this->simpul('l:'.$j.':'.md5($this->kunciLabel($jenis, $teks)), (string) $j, $teks, $teks, $nama);
    }

    /** @param array<string, mixed> $ekstra */
    private function simpul(string $kunci, string $jenis, string $id, string $label, string $sub, array $ekstra = []): string
    {
        $this->node[$kunci] ??= ['key' => $kunci, 'jenis' => $jenis, 'id' => $id, 'label' => $label, 'sub' => $sub] + $ekstra;

        return $kunci;
    }

    private function sambung(string $a, string $b): void
    {
        if ($a !== $b) {
            $k = strcmp($a, $b) < 0 ? $a.'|'.$b : $b.'|'.$a;
            $this->edge[$k] ??= ['from' => $a, 'to' => $b];
        }
    }

    /** Batasi jumlah simpul satu jenis per graf; sisanya dihitung di $potong. */
    private function batasi(string $jenis, $rows, int $maks = self::MAKS)
    {
        $rows = collect($rows)->values();
        if ($rows->count() > $maks) {
            $this->potong[$jenis] = ($this->potong[$jenis] ?? 0) + $rows->count() - $maks;
        }

        return $rows->take($maks);
    }

    // ------------------------------------------------------------ relasi

    /** @return \Illuminate\Support\Collection<int, string> nama PJA pemilik detail lokasi (lewat site + lokasi, M07/M08) */
    private function pjaDariDl($dl)
    {
        $kunci = collect($dl)->map(fn ($d) => mb_strtolower(trim($d->site).'|'.trim($d->lokasi)))->unique()->flip();
        if ($kunci->isEmpty()) {
            return collect();
        }

        return DB::table('drm_master_lokasi_pja')->where('aktif', true)->whereIn('site', collect($dl)->pluck('site')->unique())->get()
            ->filter(fn ($p) => isset($kunci[mb_strtolower(trim($p->site).'|'.trim($p->lokasi))]))->pluck('nama_pja')->unique()->sort()->values();
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

    /** @return list<array{0: string, 1: string}> [jenis OBS/OAK/KET, teks label] dari kolom item sumber baris */
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

    /** Baris HIRA yang memuat label ini (dicocokkan dengan normalisasi yang sama dengan sync). */
    private function barisDenganLabel(string $jenis, string $teks)
    {
        if (! DrmMasterDb::labelDiBaris()) {
            return collect();
        }
        $kolom = DrmMasterDb::KOLOM_LABEL[$jenis];
        $kunci = $this->kunciLabel($jenis, $teks);
        // Saring kasar dengan LIKE kata terpanjang, lalu cocokkan persis per label.
        $kata = collect(preg_split('/\W+/u', $teks))->sortByDesc(fn ($k) => mb_strlen($k))->first() ?: $teks;

        return DB::table('drm_master_baris')->where($kolom, 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $kata).'%')->get()
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
            $j = array_search($jenis, self::LABEL_JENIS, true);
            foreach (array_slice(array_values($temu), 0, 8) as $l) {
                $hasil[] = ['jenis' => (string) $j, 'id' => $l, 'label' => $l, 'sub' => ['obs' => 'Label Observasi', 'oak' => 'Label OAK', 'ket' => 'Label Ketidaksesuaian'][$j]];
            }
        }

        return $hasil;
    }

    /**
     * Status SAP per baris HIRA di cakupan seluruh BC (rincian.json lingkup ALL), minggu penuh terakhir.
     * K = teridentifikasi & terkontrol, G = ada gap, T = tidak teridentifikasi. Kosong bila snapshot belum ada.
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
                // Satu baris bisa di beberapa kelompok: status terburuk (G > T > K) yang dipakai.
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
