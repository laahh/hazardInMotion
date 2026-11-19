<?php

declare(strict_types=1);

namespace App\Services\PetaRisiko\Drm\Master;

use App\Services\PetaRisiko\Drm\DrmMaster;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Edit master DRM 2.1 dari halaman Alur baseline (/peta-risiko-pja/korelasi), lewat modal per simpul:
 *   aktivitas (nama, kelompok M02) · sub aktivitas (nama) · bahaya-risiko (teks, risiko awal, aktif)
 *   label SAP per bahaya (item_sumber_*) · grup lokasi per bahaya (M05) · detail lokasi (grup, aktif)
 *   PJA per lokasi (drm_master_lokasi_pja, dipakai sync sebagai M07/M08).
 * Semua dalam transaksi + drm_master_log; berlaku pada peta-risiko:sync berikutnya.
 */
final class DrmAlurEdit
{
    public const KB = ['Significant', 'High', 'Medium', 'Low'];

    public function __construct(private readonly DrmMasterDb $db, private readonly DrmMasterGrup $grup)
    {
    }

    // ------------------------------------------------------------ baca (isi modal)

    /** @return array<string, mixed> nilai yang bisa diedit untuk satu simpul alur */
    public function simpul(string $jenis, string $id): array
    {
        return match ($jenis) {
            'aktivitas' => $this->bacaAktivitas((int) $id),
            'sub' => $this->bacaSub($id),
            'bahaya' => $this->bacaBahaya((int) $id),
            'dl' => $this->bacaDl((int) $id),
            'obs', 'oak', 'ket' => $this->bacaLabel(strtoupper($jenis), $id),
            default => throw new InvalidArgumentException('Simpul ini tidak diedit dari sini.'),
        };
    }

    /** Label SAP: bahaya-risiko yang memakainya (normalisasi sama dengan sync). */
    private function bacaLabel(string $jenis, string $teks): array
    {
        $baris = $this->barisDenganLabel($jenis, $teks);

        return ['jenis' => $jenis, 'teks' => $teks, 'labelDiBaris' => DrmMasterDb::labelDiBaris(),
            'baris' => array_map(fn ($b) => ['ri' => (int) $b->ri, 'aktivitas' => (string) $b->aktivitas, 'sub' => (string) $b->sub, 'bahaya' => (string) $b->bahaya, 'kb' => (string) $b->kb], $baris)];
    }

    /**
     * Ganti teks satu label di SEMUA baris yang memakainya (mis. membetulkan salah ketik).
     * Baris yang sudah memuat label baru tidak digandakan.
     */
    public function gantiLabel(string $jenis, string $lama, string $baru): string
    {
        $baru = $this->rapikanLabel($jenis, $baru);
        if ($this->kunciLabel($jenis, $lama) === $this->kunciLabel($jenis, $baru) && trim($lama) === $baru) {
            return 'Tidak ada perubahan.';
        }
        $kolom = DrmMasterDb::KOLOM_LABEL[$jenis];

        return DB::transaction(function () use ($jenis, $lama, $baru, $kolom) {
            $n = 0;
            foreach ($this->barisDenganLabel($jenis, $lama) as $b) {
                $ada = DrmMasterDb::pisahLabel($b->{$kolom});
                $hasil = [];
                foreach ($ada as $l) {
                    $l = $this->kunciLabel($jenis, $l) === $this->kunciLabel($jenis, $lama) ? $baru : $l;
                    if (! in_array($this->kunciLabel($jenis, $l), array_map(fn ($x) => $this->kunciLabel($jenis, $x), $hasil), true)) {
                        $hasil[] = $l;
                    }
                }
                DB::table('drm_master_baris')->where('ri', $b->ri)->update([$kolom => implode("\n", $hasil), 'diubah_oleh' => $this->user(), 'updated_at' => now()]);
                $this->db->catat('drm_master_baris', (string) $b->ri, 'ubah', [$kolom => $b->{$kolom}], [$kolom => implode("\n", $hasil)]);
                $n++;
            }
            if ($n === 0) {
                throw new InvalidArgumentException('Label tidak ditemukan di baris mana pun.');
            }

            return "Label diganti di {$n} bahaya-risiko.";
        });
    }

    /** Hapus satu label dari SEMUA baris yang memakainya. */
    public function hapusLabelSemua(string $jenis, string $teks): string
    {
        $kolom = $this->kolomLabel($jenis);
        $kunci = $this->kunciLabel($jenis, $teks);

        return DB::transaction(function () use ($jenis, $teks, $kolom, $kunci) {
            $n = 0;
            foreach ($this->barisDenganLabel($jenis, $teks) as $b) {
                $sisa = array_values(array_filter(DrmMasterDb::pisahLabel($b->{$kolom}), fn ($l) => $this->kunciLabel($jenis, $l) !== $kunci));
                DB::table('drm_master_baris')->where('ri', $b->ri)->update([$kolom => implode("\n", $sisa), 'diubah_oleh' => $this->user(), 'updated_at' => now()]);
                $this->db->catat('drm_master_baris', (string) $b->ri, 'ubah', [$kolom => $b->{$kolom}], [$kolom => implode("\n", $sisa)]);
                $n++;
            }

            return $n ? "Label dihapus dari {$n} bahaya-risiko." : 'Label tidak ditemukan di baris mana pun.';
        });
    }

    /** @return list<object> baris yang memuat label (saring kasar LIKE kata terpanjang, lalu cocok persis ternormalisasi). */
    private function barisDenganLabel(string $jenis, string $teks): array
    {
        $kolom = $this->kolomLabel($jenis);
        $kunci = $this->kunciLabel($jenis, $teks);
        $kata = collect(preg_split('/\W+/u', $teks))->sortByDesc(fn ($k) => mb_strlen($k))->first() ?: $teks;

        return DB::table('drm_master_baris')->where($kolom, 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $kata).'%')->orderBy('aktivitas')->orderBy('ri')->get()
            ->filter(fn ($b) => collect(DrmMasterDb::pisahLabel($b->{$kolom}))->contains(fn ($l) => $this->kunciLabel($jenis, $l) === $kunci))->values()->all();
    }

    private function kolomLabel(string $jenis): string
    {
        if (! isset(DrmMasterDb::KOLOM_LABEL[$jenis]) || ! DrmMasterDb::labelDiBaris()) {
            throw new InvalidArgumentException('Label per baris belum aktif di master.');
        }

        return DrmMasterDb::KOLOM_LABEL[$jenis];
    }

    /** Teks label dirapikan + divalidasi (dipakai tambah & ganti). */
    private function rapikanLabel(string $jenis, string $teks): string
    {
        $this->kolomLabel($jenis);
        $teks = trim(preg_replace('/\s+/u', ' ', $teks) ?? $teks);
        if ($teks === '' || mb_strlen($teks) > 500) {
            throw new InvalidArgumentException('Teks label wajib diisi (maks 500 karakter).');
        }
        if ($jenis === 'KET' && ! str_contains($teks, ' / ')) {
            throw new InvalidArgumentException('Label Ketidaksesuaian ditulis "Ketidaksesuaian / Sub ketidaksesuaian".');
        }

        return $teks;
    }

    /** Pilihan untuk isian modal: kelompok, grup lokasi, nama PJA yang dikenal. */
    public function opsi(): array
    {
        return [
            'kelompok' => DB::table('drm_master_kelompok')->orderBy('urutan')->get(['kode', 'nama'])->map(fn ($k) => ['kode' => (string) $k->kode, 'nama' => (string) $k->nama])->all(),
            'grup' => DB::table('drm_master_grup')->orderBy('nama')->get(['gid', 'nama'])->map(fn ($g) => ['gid' => (int) $g->gid, 'nama' => (string) $g->nama])->all(),
            'pja' => DB::table('drm_master_lokasi_pja')->distinct()->orderBy('nama_pja')->pluck('nama_pja')->map(fn ($n) => (string) $n)->all(),
            'kb' => self::KB,
        ];
    }

    private function bacaAktivitas(int $aid): array
    {
        $a = DB::table('drm_master_aktivitas')->where('aid', $aid)->first() ?? throw new InvalidArgumentException('Aktivitas tidak ditemukan.');

        return ['aid' => $aid, 'nama' => (string) $a->aktivitas, 'kelompok' => (string) $a->kelompok,
            'baris' => DB::table('drm_master_baris')->where('aid', $aid)->count()];
    }

    /** $id sub = "aid|nama sub" (kosong = tanpa sub). */
    private function bacaSub(string $id): array
    {
        [$aid, $sub] = $this->pisahSub($id);

        return ['aid' => $aid, 'sub' => $sub, 'baris' => DB::table('drm_master_baris')->where('aid', $aid)->where('sub', $sub)->count()];
    }

    private function bacaBahaya(int $ri): array
    {
        $b = DB::table('drm_master_baris')->where('ri', $ri)->first() ?? throw new InvalidArgumentException('Baris HIRA tidak ditemukan.');
        $label = [];
        foreach (DrmMasterDb::KOLOM_LABEL as $jenis => $kolom) {
            $label[$jenis] = DrmMasterDb::pisahLabel($b->{$kolom} ?? null);
        }

        return ['ri' => $ri, 'aktivitas' => (string) $b->aktivitas, 'sub' => (string) $b->sub, 'bahaya' => (string) $b->bahaya, 'kb' => (string) $b->kb,
            'aktif' => (bool) $b->aktif, 'label' => $label, 'labelDiBaris' => DrmMasterDb::labelDiBaris(),
            'grup' => DB::table('drm_master_grup_baris as g')->join('drm_master_grup as m', 'm.gid', '=', 'g.gid')->where('g.ri', $ri)->orderBy('m.nama')
                ->get(['m.gid', 'm.nama'])->map(fn ($g) => ['gid' => (int) $g->gid, 'nama' => (string) $g->nama])->all()];
    }

    private function bacaDl(int $id): array
    {
        $d = DB::table('drm_master_dl')->where('id', $id)->first() ?? throw new InvalidArgumentException('Detail lokasi tidak ditemukan.');

        return ['id' => $id, 'site' => (string) $d->site, 'lokasi' => (string) $d->lokasi, 'detail' => (string) $d->detail, 'gid' => (int) $d->gid, 'aktif' => (bool) $d->aktif,
            'pja' => $this->pjaLokasi((string) $d->site, (string) $d->lokasi),
            'dlSeLokasi' => DB::table('drm_master_dl')->where('site', $d->site)->where('lokasi', $d->lokasi)->count()];
    }

    /** @return list<string> */
    private function pjaLokasi(string $site, string $lokasi): array
    {
        return DB::table('drm_master_lokasi_pja')->where('site', $site)->where('lokasi', $lokasi)->where('aktif', true)
            ->orderBy('nama_pja')->pluck('nama_pja')->map(fn ($n) => (string) $n)->unique()->values()->all();
    }

    // ------------------------------------------------------------ ubah

    /** Nama (juga teks aktivitas di semua barisnya) dan/atau kelompok aktivitas. */
    public function ubahAktivitas(int $aid, string $nama, string $kelompok): string
    {
        $a = DB::table('drm_master_aktivitas')->where('aid', $aid)->first() ?? throw new InvalidArgumentException('Aktivitas tidak ditemukan.');
        $nama = trim($nama);
        if ($nama === '') {
            throw new InvalidArgumentException('Nama aktivitas wajib diisi.');
        }

        return DB::transaction(function () use ($a, $aid, $nama, $kelompok) {
            $hasil = [];
            if ($nama !== (string) $a->aktivitas) {
                DB::table('drm_master_aktivitas')->where('aid', $aid)->update(['aktivitas' => $nama, 'diubah_oleh' => $this->user(), 'updated_at' => now()]);
                $n = DB::table('drm_master_baris')->where('aid', $aid)->update(['aktivitas' => $nama, 'diubah_oleh' => $this->user(), 'updated_at' => now()]);
                $this->db->catat('drm_master_aktivitas', (string) $aid, 'ubah', ['aktivitas' => (string) $a->aktivitas], ['aktivitas' => $nama, 'baris' => $n]);
                $hasil[] = 'nama diganti';
            }
            if ($this->grup->ubahKelompok($aid, $kelompok)) {
                $hasil[] = 'kelompok diganti';
            }

            return $hasil ? 'Aktivitas: '.implode(', ', $hasil).'.' : 'Tidak ada perubahan.';
        });
    }

    /** Ganti nama sub aktivitas untuk semua bahaya di sub itu. */
    public function ubahSub(string $id, string $baru): string
    {
        [$aid, $lama] = $this->pisahSub($id);
        $baru = trim($baru);
        if ($baru === $lama) {
            return 'Tidak ada perubahan.';
        }

        return DB::transaction(function () use ($aid, $lama, $baru) {
            $n = DB::table('drm_master_baris')->where('aid', $aid)->where('sub', $lama)->update(['sub' => $baru, 'diubah_oleh' => $this->user(), 'updated_at' => now()]);
            if ($n === 0) {
                throw new InvalidArgumentException('Sub aktivitas tidak ditemukan.');
            }
            $this->db->catat('drm_master_baris', 'aid:'.$aid, 'ubah', ['sub' => $lama], ['sub' => $baru, 'baris' => $n]);

            return "Sub aktivitas diganti untuk {$n} bahaya.";
        });
    }

    public function ubahBahaya(int $ri, string $bahaya, string $kb, bool $aktif): string
    {
        $b = DB::table('drm_master_baris')->where('ri', $ri)->first() ?? throw new InvalidArgumentException('Baris HIRA tidak ditemukan.');
        $bahaya = trim($bahaya);
        if ($bahaya === '' || ! in_array($kb, self::KB, true)) {
            throw new InvalidArgumentException('Teks bahaya wajib diisi dan risiko awal harus Significant/High/Medium/Low.');
        }
        $lama = ['bahaya' => (string) $b->bahaya, 'kb' => (string) $b->kb, 'aktif' => (bool) $b->aktif];
        $baru = ['bahaya' => $bahaya, 'kb' => $kb, 'aktif' => $aktif];
        if ($lama === $baru) {
            return 'Tidak ada perubahan.';
        }
        DB::transaction(function () use ($ri, $lama, $baru) {
            DB::table('drm_master_baris')->where('ri', $ri)->update($baru + ['diubah_oleh' => $this->user(), 'updated_at' => now()]);
            $this->db->catat('drm_master_baris', (string) $ri, 'ubah', array_diff_assoc($lama, $baru), array_diff_assoc($baru, $lama));
        });

        return 'Bahaya-risiko disimpan.';
    }

    /** Tambah / lepas satu label SAP (OBS / OAK / KET) di satu baris; dicocokkan dengan normalisasi yang sama dengan sync. */
    public function label(int $ri, string $jenis, string $teks, bool $tambah): string
    {
        // Format hanya diwajibkan saat menambah; label lama yang tidak rapi tetap bisa dilepas.
        $teks = $tambah ? $this->rapikanLabel($jenis, $teks) : trim($teks);
        $kolom = $this->kolomLabel($jenis);

        return DB::transaction(function () use ($ri, $jenis, $teks, $tambah, $kolom) {
            $b = DB::table('drm_master_baris')->where('ri', $ri)->lockForUpdate()->first() ?? throw new InvalidArgumentException('Baris HIRA tidak ditemukan.');
            $ada = DrmMasterDb::pisahLabel($b->{$kolom});
            $kunci = $this->kunciLabel($jenis, $teks);
            $sudah = array_filter($ada, fn ($l) => $this->kunciLabel($jenis, $l) === $kunci) !== [];
            if ($tambah === $sudah) {
                return $tambah ? 'Label sudah ada di baris ini.' : 'Label tidak ditemukan di baris ini.';
            }
            $sisa = $tambah ? [...$ada, $teks] : array_values(array_filter($ada, fn ($l) => $this->kunciLabel($jenis, $l) !== $kunci));
            DB::table('drm_master_baris')->where('ri', $ri)->update([$kolom => implode("\n", $sisa), 'diubah_oleh' => $this->user(), 'updated_at' => now()]);
            $this->db->catat('drm_master_baris', (string) $ri, 'ubah', [$kolom => $b->{$kolom}], [$kolom => implode("\n", $sisa)]);

            return $tambah ? 'Label ditambahkan.' : 'Label dilepas.';
        });
    }

    /** Tambah / keluarkan baris dari grup lokasi (M05). */
    public function grupBaris(int $ri, int $gid, bool $tambah): string
    {
        $aid = DB::table('drm_master_baris')->where('ri', $ri)->value('aid');
        if ($aid === null) {
            throw new InvalidArgumentException('Baris HIRA tidak ditemukan.');
        }
        $n = $tambah ? $this->grup->tambah($gid, (int) $aid, [$ri]) : $this->grup->hapus($gid, [$ri]);

        return $n ? ($tambah ? 'Bahaya ditambahkan ke grup lokasi.' : 'Bahaya dikeluarkan dari grup lokasi.') : 'Tidak ada perubahan.';
    }

    /** Pindah grup lokasi dan/atau aktif detail lokasi. */
    public function ubahDl(int $id, int $gid, bool $aktif): string
    {
        $d = DB::table('drm_master_dl')->where('id', $id)->first() ?? throw new InvalidArgumentException('Detail lokasi tidak ditemukan.');
        if (! DB::table('drm_master_grup')->where('gid', $gid)->exists()) {
            throw new InvalidArgumentException('Grup lokasi tidak ditemukan.');
        }
        $lama = ['gid' => (int) $d->gid, 'aktif' => (bool) $d->aktif];
        $baru = ['gid' => $gid, 'aktif' => $aktif];
        if ($lama === $baru) {
            return 'Tidak ada perubahan.';
        }
        DB::transaction(function () use ($id, $lama, $baru) {
            DB::table('drm_master_dl')->where('id', $id)->update($baru + ['diubah_oleh' => $this->user(), 'updated_at' => now()]);
            $this->db->catat('drm_master_dl', (string) $id, 'ubah', $lama, $baru);
        });

        return 'Detail lokasi disimpan.';
    }

    /** Tambah / lepas PJA penanggung jawab lokasi (berlaku untuk semua detail lokasi di site + lokasi itu). */
    public function pjaLokasiUbah(string $site, string $lokasi, string $nama, bool $tambah): string
    {
        $nama = trim($nama);
        if ($nama === '') {
            throw new InvalidArgumentException('Nama PJA wajib diisi.');
        }
        if ($tambah && ! DB::table('drm_master_lokasi_pja')->where('nama_pja', $nama)->exists()) {
            throw new InvalidArgumentException('Nama PJA harus dipilih dari daftar PJA yang dikenal (dipakai untuk mencari ID PJA).');
        }

        return DB::transaction(function () use ($site, $lokasi, $nama, $tambah) {
            $baris = DB::table('drm_master_lokasi_pja')->where('site', $site)->where('lokasi', $lokasi)->where('nama_pja', $nama)->first();
            if ($tambah) {
                if ($baris !== null && $baris->aktif) {
                    return 'PJA sudah terdaftar di lokasi ini.';
                }
                if ($baris !== null) {
                    DB::table('drm_master_lokasi_pja')->where('id', $baris->id)->update(['aktif' => true, 'diubah_oleh' => $this->user(), 'updated_at' => now()]);
                    $this->db->catat('drm_master_lokasi_pja', (string) $baris->id, 'ubah', ['aktif' => false], ['aktif' => true]);
                } else {
                    $nilai = ['site' => $site, 'lokasi' => $lokasi, 'nama_pja' => $nama, 'aktif' => true, 'diubah_oleh' => $this->user(), 'created_at' => now(), 'updated_at' => now()];
                    $id = DB::table('drm_master_lokasi_pja')->insertGetId($nilai);
                    $this->db->catat('drm_master_lokasi_pja', (string) $id, 'tambah', null, ['site' => $site, 'lokasi' => $lokasi, 'nama_pja' => $nama]);
                }

                return 'PJA ditambahkan ke lokasi.';
            }
            if ($baris === null || ! $baris->aktif) {
                return 'PJA tidak terdaftar di lokasi ini.';
            }
            DB::table('drm_master_lokasi_pja')->where('id', $baris->id)->update(['aktif' => false, 'diubah_oleh' => $this->user(), 'updated_at' => now()]);
            $this->db->catat('drm_master_lokasi_pja', (string) $baris->id, 'ubah', ['aktif' => true], ['aktif' => false]);

            return 'PJA dilepas dari lokasi.';
        });
    }

    // ------------------------------------------------------------ bantu

    /** @return array{0: int, 1: string} */
    private function pisahSub(string $id): array
    {
        $p = strpos($id, '|');
        if ($p === false || ! ctype_digit(substr($id, 0, $p))) {
            throw new InvalidArgumentException('ID sub aktivitas tidak valid.');
        }

        return [(int) substr($id, 0, $p), substr($id, $p + 1)];
    }

    private function kunciLabel(string $jenis, string $teks): string
    {
        if ($jenis === 'KET') {
            [$k, $sk] = DrmMasterDb::pisahKet($teks);

            return DrmMaster::norm($k).' / '.DrmMaster::norm($sk);
        }

        return DrmMaster::normTeks($teks);
    }

    private function user(): ?int
    {
        $id = auth()->id();

        return $id === null ? null : (int) $id;
    }
}
