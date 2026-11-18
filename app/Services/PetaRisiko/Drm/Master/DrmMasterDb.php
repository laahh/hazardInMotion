<?php

namespace App\Services\PetaRisiko\Drm\Master;

use App\Services\PetaRisiko\Drm\DrmMaster;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Tabel master mapping DRM 2.1 di database (lihat migrasi 2026_11_17_100000_create_drm_master_tables).
 *
 * - isi(): mengisi semua tabel dari baseline awal (workbook master + regex config + PJA aaj_lokasi_dan_pja).
 * - data(): membaca tabel ke bentuk data DrmMaster (dipakai peta-risiko:sync), termasuk koreksi manual:
 *   kelompok per aktivitas, daftar kelompok + regex, tema + regex, tema → baris, lokasi → PJA.
 * - catat(): riwayat perubahan (drm_master_log).
 * Hanya baris/pasangan berstatus aktif yang ikut data().
 */
final class DrmMasterDb
{
    public const TABEL_UTAMA = 'drm_master_baris';

    /** Kurasi aktivitas OAK → aktivitas HIRA (migrasi terpisah 2026_11_18_100000). */
    public const TABEL_OAK_AKTIVITAS = 'drm_master_oak_aktivitas';

    /** @var array<string, list<string>>|false|null cache oakAktivitas() per proses (false = belum dibaca) */
    private static array|false|null $cacheOak = false;

    /** Lupakan cache oakAktivitas() (setelah tabel kurasi diubah dalam proses yang sama). */
    public static function lupakanCacheOak(): void
    {
        self::$cacheOak = false;
    }

    private const PECAH = 500;

    /** Pesan error terakhir dari siap() (diagnosis halaman master bila pengecekan tabel gagal). */
    public static ?string $errorSiap = null;

    /** Tabel sudah dimigrasi? */
    public static function siap(): bool
    {
        try {
            self::$errorSiap = null;

            return Schema::hasTable(self::TABEL_UTAMA) && Schema::hasTable('drm_master_log');
        } catch (\Throwable $e) {
            self::$errorSiap = mb_substr($e->getMessage(), 0, 300);

            return false;
        }
    }

    /** Tabel sudah dimigrasi dan berisi baseline? (bila ya, sync memakai database, bukan master.json) */
    public static function terisi(): bool
    {
        return self::siap() && DB::table(self::TABEL_UTAMA)->exists();
    }

    /**
     * Isi semua tabel master dari baseline awal. Menimpa isi lama (dalam satu transaksi).
     *
     * @param  array<string, mixed>  $cfg  config peta_risiko_drm
     * @param  list<array{0: string, 1: string, 2: string}>  $lokasiPja  [site, lokasi, nama PJA]
     * @return array<string, int>  jumlah baris per tabel
     */
    public function isi(DrmMaster $master, array $cfg, array $lokasiPja): array
    {
        if (! self::siap()) {
            throw new RuntimeException('Tabel master belum dimigrasi (2026_11_17_100000_create_drm_master_tables).');
        }
        $d = $master->data;
        $sekarang = now();
        $cap = fn (array $r) => $r + ['created_at' => $sekarang, 'updated_at' => $sekarang];
        $kelBaris = $master->kelompokBaris($cfg['proses_bisnis']);

        $isi = [
            'drm_master_baris' => array_map(fn ($b) => $cap([
                'ri' => $b['ri'], 'aid' => $b['aid'], 'aktivitas' => mb_substr($b['aktivitas'], 0, 500), 'sub' => mb_substr($b['sub'], 0, 500),
                'bahaya' => mb_substr($b['bahaya'], 0, 1000), 'kb' => $b['kb'], 'umum' => $b['umum'], 'idh' => implode(',', $b['idh']), 'aktif' => true,
            ]), $d['baris']),
            'drm_master_kelompok' => array_map(fn ($kode, $k) => $cap(['kode' => $kode, 'nama' => $k[0], 'regex' => $k[2], 'urutan' => $kode === 'K0' ? 999 : (int) substr($kode, 1)]),
                array_keys($cfg['proses_bisnis']), $cfg['proses_bisnis']),
            'drm_master_aktivitas' => array_values(array_reduce($d['baris'], function (array $c, array $b) use ($kelBaris, $cap) {
                $c[$b['aid']] ??= $cap(['aid' => $b['aid'], 'aktivitas' => mb_substr($b['aktivitas'], 0, 500), 'kelompok' => $kelBaris[$b['ri']] ?? 'K0']);

                return $c;
            }, [])),
            'drm_master_grup' => array_map(fn ($gid, $nama) => $cap(['gid' => (int) $gid, 'nama' => $nama, 'aktif' => true]), array_keys($d['grup']), $d['grup']),
            'drm_master_grup_baris' => $this->rata($d['grupBaris'], fn ($gid, $ri) => $cap(['gid' => (int) $gid, 'ri' => $ri])),
            'drm_master_dl' => array_map(fn ($x) => $cap(['site' => $x['site'], 'lokasi' => $x['lokasi'], 'detail' => $x['detail'], 'gid' => $x['gid'], 'aktif' => true]), $d['dl']),
            'drm_master_lokasi_pja' => array_map(fn ($x) => $cap(['site' => $x[0], 'lokasi' => $x[1], 'nama_pja' => $x[2], 'aktif' => true]), $lokasiPja),
            'drm_master_label' => array_merge([], ...array_map(fn ($l) => array_map(fn ($ri) => $cap([
                'ketidaksesuaian' => mb_substr($l['k'], 0, 300), 'subketidaksesuaian' => mb_substr($l['sk'], 0, 300), 'ri' => $ri, 'sumber' => mb_substr($l['sumber'], 0, 100), 'aktif' => true,
            ]), $l['ri']), $d['label'])),
            'drm_master_teks' => array_merge(
                [],
                ...array_map(fn ($t) => array_map(fn ($ri) => $cap(['jenis' => 'OBS', 'teks' => mb_substr($t['teks'], 0, 500), 'ri' => $ri, 'aktif' => true]), $t['ri']), $d['obs']),
                ...array_map(fn ($t) => array_map(fn ($ri) => $cap(['jenis' => 'OAK', 'teks' => mb_substr($t['teks'], 0, 500), 'ri' => $ri, 'aktif' => true]), $t['ri']), $d['oak']),
            ),
            'drm_master_tema' => array_map(fn ($id, $t) => $cap(['id' => (int) $id, 'nama' => $t[0], 'regex_teks' => $t[1], 'regex_bahaya' => $t[2]]),
                array_keys($cfg['tema_checklist']), $cfg['tema_checklist']),
            'drm_master_tema_baris' => $this->rata($master->temaBaris($cfg['tema_checklist']), fn ($id, $ri) => $cap(['tema_id' => (int) $id, 'ri' => $ri])),
        ];

        if (Schema::hasTable(self::TABEL_OAK_AKTIVITAS)) {
            $isi[self::TABEL_OAK_AKTIVITAS] = $this->barisOakAktivitas($cfg, $cap);
        }

        DB::transaction(function () use ($isi) {
            foreach (array_reverse(array_keys($isi)) as $tabel) {
                DB::table($tabel)->delete();
            }
            foreach ($isi as $tabel => $rows) {
                foreach (array_chunk($rows, self::PECAH) as $potong) {
                    DB::table($tabel)->insert($potong);
                }
            }
        });
        $jumlah = array_map('count', $isi);
        $this->catat('*', '*', 'isi', null, $jumlah);

        return $jumlah;
    }

    /**
     * Data master (bentuk DrmMaster::$data) dari tabel. Kunci tambahan untuk koreksi manual:
     *   kelompokAkt [aid => kode], kelompokDaftar [kode => [nama, 'inti', regex]], tema [id => [nama, regex teks, regex bahaya]],
     *   temaBarisDb [id => [ri]], lokasiPja [[site, lokasi, nama PJA]].
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $baris = DB::table('drm_master_baris')->where('aktif', true)->orderBy('ri')->get();
        $riAktif = array_flip($baris->pluck('ri')->map(fn ($v) => (int) $v)->all());
        $grupAktif = DB::table('drm_master_grup')->where('aktif', true)->orderBy('gid')->pluck('nama', 'gid')->all();
        $kelompok = DB::table('drm_master_kelompok')->orderBy('urutan')->orderBy('kode')->get();

        $kelompokkan = function (iterable $rows, string $k, string $v) use ($riAktif): array {
            $hasil = [];
            foreach ($rows as $r) {
                if (isset($riAktif[(int) $r->{$v}])) {
                    $hasil[$r->{$k}][] = (int) $r->{$v};
                }
            }

            return $hasil;
        };
        $teks = fn (string $jenis) => array_values(array_map(fn ($t) => ['teks' => $t['teks'], 'ri' => array_values(array_unique($t['ri']))],
            array_reduce(DB::table('drm_master_teks')->where('aktif', true)->where('jenis', $jenis)->get()->all(), function (array $c, $r) use ($riAktif) {
                if (isset($riAktif[(int) $r->ri])) {
                    $kunci = DrmMaster::norm($r->teks);
                    $c[$kunci] ??= ['teks' => $r->teks, 'ri' => []];
                    $c[$kunci]['ri'][] = (int) $r->ri;
                }

                return $c;
            }, [])));
        $label = array_reduce(DB::table('drm_master_label')->where('aktif', true)->get()->all(), function (array $c, $r) use ($riAktif) {
            if (isset($riAktif[(int) $r->ri])) {
                $kunci = DrmMaster::norm($r->ketidaksesuaian).' / '.DrmMaster::norm($r->subketidaksesuaian);
                $c[$kunci] ??= ['k' => $r->ketidaksesuaian, 'sk' => $r->subketidaksesuaian, 'ri' => [], 'sumber' => (string) $r->sumber];
                $c[$kunci]['ri'][] = (int) $r->ri;
            }

            return $c;
        }, []);

        return [
            'dibuat' => (string) (DB::table('drm_master_log')->max('dibuat') ?? now()->toIso8601String()),
            'sumber' => 'database (tabel drm_master_*)',
            'baris' => $baris->map(fn ($b) => [
                'ri' => (int) $b->ri, 'aid' => (int) $b->aid, 'aktivitas' => (string) $b->aktivitas, 'sub' => (string) $b->sub, 'bahaya' => (string) $b->bahaya,
                'kb' => (string) $b->kb, 'kelompokM01' => '', 'umum' => (bool) $b->umum,
                'idh' => array_values(array_map('intval', array_filter(preg_split('/[^0-9]+/', (string) $b->idh) ?: [], 'strlen'))),
            ])->all(),
            'grup' => array_map('strval', $grupAktif),
            'grupBaris' => array_intersect_key($kelompokkan(DB::table('drm_master_grup_baris')->get(), 'gid', 'ri'), $grupAktif),
            'dl' => DB::table('drm_master_dl')->where('aktif', true)->whereIn('gid', array_keys($grupAktif))->orderBy('id')->get()
                ->map(fn ($x) => ['id' => (int) $x->id, 'site' => (string) $x->site, 'lokasi' => (string) $x->lokasi, 'detail' => (string) $x->detail, 'gid' => (int) $x->gid])->all(),
            'label' => array_values(array_map(fn ($l) => [...$l, 'ri' => array_values(array_unique($l['ri']))], $label)),
            'obs' => $teks('OBS'),
            'oak' => $teks('OAK'),
            'kelompokAkt' => DB::table('drm_master_aktivitas')->pluck('kelompok', 'aid')->mapWithKeys(fn ($v, $k) => [(int) $k => (string) $v])->all(),
            'kelompokDaftar' => $kelompok->mapWithKeys(fn ($k) => [(string) $k->kode => [(string) $k->nama, 'inti', (string) $k->regex]])->all(),
            'tema' => DB::table('drm_master_tema')->orderBy('id')->get()->mapWithKeys(fn ($t) => [(int) $t->id => [(string) $t->nama, (string) $t->regex_teks, (string) $t->regex_bahaya]])->all(),
            'temaBarisDb' => $kelompokkan(DB::table('drm_master_tema_baris')->get(), 'tema_id', 'ri'),
            'lokasiPja' => DB::table('drm_master_lokasi_pja')->where('aktif', true)->get()
                ->map(fn ($x) => [(string) $x->site, (string) $x->lokasi, (string) $x->nama_pja])->all(),
        ];
    }

    /**
     * Kurasi aktivitas OAK → [aktivitas HIRA] dari tabel, hanya baris aktif. null bila tabel belum dimigrasi atau
     * belum pernah diisi (sync lalu memakai config oak_aktivitas_hira). Dibaca langsung, terlepas dari apakah master
     * lain sudah dari database. Disimpan per proses.
     *
     * @return array<string, list<string>>|null
     */
    public static function oakAktivitas(): ?array
    {
        if (self::$cacheOak !== false) {
            return self::$cacheOak;
        }
        try {
            if (! Schema::hasTable(self::TABEL_OAK_AKTIVITAS) || ! DB::table(self::TABEL_OAK_AKTIVITAS)->exists()) {
                return self::$cacheOak = null;
            }
            $peta = [];
            foreach (DB::table(self::TABEL_OAK_AKTIVITAS)->where('aktif', true)->orderBy('aktivitas_oak')->orderBy('id')->get() as $r) {
                $peta[trim((string) $r->aktivitas_oak)][] = trim((string) $r->aktivitas_hira);
            }

            return self::$cacheOak = array_map(fn ($d) => array_values(array_unique($d)), $peta);
        } catch (\Throwable) {
            return self::$cacheOak = null;
        }
    }

    /**
     * Isi (timpa) hanya tabel kurasi aktivitas OAK dari config oak_aktivitas_hira. Tabel master lain tidak tersentuh.
     *
     * @param  array<string, mixed>  $cfg
     */
    public function isiOakAktivitas(array $cfg): int
    {
        if (! Schema::hasTable(self::TABEL_OAK_AKTIVITAS)) {
            throw new RuntimeException('Tabel '.self::TABEL_OAK_AKTIVITAS.' belum dimigrasi (2026_11_18_100000_create_drm_master_oak_aktivitas_table).');
        }
        $sekarang = now();
        $rows = $this->barisOakAktivitas($cfg, fn (array $r) => $r + ['created_at' => $sekarang, 'updated_at' => $sekarang]);
        DB::transaction(function () use ($rows) {
            DB::table(self::TABEL_OAK_AKTIVITAS)->delete();
            foreach (array_chunk($rows, self::PECAH) as $potong) {
                DB::table(self::TABEL_OAK_AKTIVITAS)->insert($potong);
            }
        });
        $this->catat(self::TABEL_OAK_AKTIVITAS, '*', 'isi', null, ['baris' => count($rows)]);
        self::lupakanCacheOak();

        return count($rows);
    }

    /** Nilai kolom sumber untuk label OAK hasil penurunan kurasi aktivitas. */
    public const SUMBER_TURUNAN_OAK = 'kurasi aktivitas OAK';

    /**
     * Turunkan kurasi aktivitas OAK → aktivitas HIRA menjadi label OAK EKSPLISIT per baris di drm_master_teks:
     * tiap baris HIRA beraktivitas terkurasi mendapat label "aktivitas OAK / sub aktivitas" untuk setiap sub aktif
     * aktivitas OAK itu. Label yang sudah ada (kunci DrmMaster::normTeks sama, baris sama) tidak diduplikasi; turunan
     * lama (sumber = SUMBER_TURUNAN_OAK) diganti. Setelah itu label bisa dihapus satu per satu di editor per baris.
     *
     * @param  array<string, list<string>>  $kurasi  aktivitas OAK → [aktivitas HIRA]
     * @param  array<string, list<string>>  $subPerAktivitas  aktivitas OAK → [sub aktivitas aktif] (master OAK)
     * @return array{baris: int, label: int}  baris HIRA tersentuh, label baru
     */
    public function turunkanOak(array $kurasi, array $subPerAktivitas): array
    {
        if (! self::siap() || ! Schema::hasColumn('drm_master_teks', 'sumber')) {
            throw new RuntimeException('Tabel master / kolom drm_master_teks.sumber belum dimigrasi (2026_11_18_110000_add_sumber_to_drm_master_teks_table).');
        }
        $riPerAkt = [];
        foreach (DB::table('drm_master_baris')->where('aktif', true)->get(['ri', 'aktivitas']) as $b) {
            $riPerAkt[DrmMaster::normTeks((string) $b->aktivitas)][] = (int) $b->ri;
        }
        $subNorm = [];
        foreach ($subPerAktivitas as $akt => $subs) {
            $subNorm[DrmMaster::normTeks((string) $akt)] = [(string) $akt, (array) $subs];
        }
        $ada = [];
        foreach (DB::table('drm_master_teks')->where('jenis', 'OAK')->where(fn ($q) => $q->whereNull('sumber')->orWhere('sumber', '!=', self::SUMBER_TURUNAN_OAK))->get(['teks', 'ri']) as $t) {
            $ada[DrmMaster::crcTeks((string) $t->teks).'|'.(int) $t->ri] = true;
        }

        $sekarang = now();
        $rows = [];
        $barisSentuh = [];
        foreach ($kurasi as $oak => $daftarHira) {
            [$namaOak, $subs] = $subNorm[DrmMaster::normTeks((string) $oak)] ?? [(string) $oak, []];
            foreach ((array) $daftarHira as $hira) {
                foreach ($riPerAkt[DrmMaster::normTeks((string) $hira)] ?? [] as $ri) {
                    foreach ($subs as $sub) {
                        $teks = trim($namaOak).' / '.trim((string) $sub);
                        $kunci = DrmMaster::crcTeks($teks).'|'.$ri;
                        if (isset($ada[$kunci])) {
                            continue;
                        }
                        $ada[$kunci] = true;
                        $barisSentuh[$ri] = true;
                        $rows[] = ['jenis' => 'OAK', 'teks' => mb_substr($teks, 0, 500), 'ri' => $ri, 'sumber' => self::SUMBER_TURUNAN_OAK,
                            'aktif' => true, 'created_at' => $sekarang, 'updated_at' => $sekarang];
                    }
                }
            }
        }
        DB::transaction(function () use ($rows) {
            DB::table('drm_master_teks')->where('jenis', 'OAK')->where('sumber', self::SUMBER_TURUNAN_OAK)->delete();
            foreach (array_chunk($rows, self::PECAH) as $potong) {
                DB::table('drm_master_teks')->insert($potong);
            }
        });
        $hasil = ['baris' => count($barisSentuh), 'label' => count($rows)];
        $this->catat('drm_master_teks', 'OAK:'.self::SUMBER_TURUNAN_OAK, 'isi', null, $hasil);

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return list<array<string, mixed>>
     */
    private function barisOakAktivitas(array $cfg, callable $cap): array
    {
        $rows = [];
        // Sumber: oak_aktivitas_hira bila diisi, selain itu draf oak_aktivitas_hira_usulan.
        foreach ((array) (($cfg['oak_aktivitas_hira'] ?? []) ?: ($cfg['oak_aktivitas_hira_usulan'] ?? [])) as $oak => $daftar) {
            foreach (array_unique((array) $daftar) as $hira) {
                $rows[] = $cap(['aktivitas_oak' => mb_substr((string) $oak, 0, 300), 'aktivitas_hira' => mb_substr((string) $hira, 0, 500), 'aktif' => true]);
            }
        }

        return $rows;
    }

    /** Catat perubahan ke drm_master_log. */
    public function catat(string $tabel, string $kunci, string $aksi, ?array $sebelum, ?array $sesudah): void
    {
        $user = Auth::user();
        DB::table('drm_master_log')->insert([
            'tabel' => mb_substr($tabel, 0, 40), 'kunci' => mb_substr($kunci, 0, 100), 'aksi' => $aksi,
            'sebelum' => $sebelum === null ? null : json_encode($sebelum, JSON_UNESCAPED_UNICODE),
            'sesudah' => $sesudah === null ? null : json_encode($sesudah, JSON_UNESCAPED_UNICODE),
            'user_id' => $user?->getAuthIdentifier(), 'user_nama' => $user ? mb_substr((string) ($user->name ?? $user->email ?? ''), 0, 150) : 'sistem',
            'dibuat' => now(),
        ]);
    }

    /**
     * @param  array<int|string, list<int>>  $peta
     * @return list<array<string, mixed>>
     */
    private function rata(array $peta, callable $baris): array
    {
        $hasil = [];
        foreach ($peta as $k => $daftar) {
            foreach (array_unique($daftar) as $v) {
                $hasil[] = $baris($k, $v);
            }
        }

        return $hasil;
    }
}
