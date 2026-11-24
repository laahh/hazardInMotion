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
