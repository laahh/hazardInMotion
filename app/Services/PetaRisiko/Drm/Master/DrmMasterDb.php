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

    /** Daftar aktivitas/sub aktivitas OAK terkait IKK (migrasi terpisah 2026_11_25_100000). */
    public const TABEL_IKK = 'drm_master_ikk';

    /** PIC Perusahaan per lokasi, dari PJA Child (migrasi terpisah 2026_11_26_110000). */
    public const TABEL_LOKASI_PIC = 'drm_master_lokasi_pic';

    /** Perusahaan langsung (tidak digabung) per nama PJA apa pun — buat saring PJA Child per perusahaan di Alur Baseline. */
    public const TABEL_PJA_PERUSAHAAN = 'drm_master_pja_perusahaan';

    /**
     * Seed awal daftar IKK (keputusan user 25 Nov 2026, dari daftar "aktivitas/sub aktivitas pekerjaan OAK terkait
     * IKK"): [aktivitas, sub aktivitas, cek_luar_workshop]. cek_luar_workshop = true untuk kategori "Perbaikan,
     * Perawatan & Fabrikasi" yang teksnya sendiri generik (tidak menyebut lokasi) — lihat ikkPerBaris().
     * Diisi lewat `peta-risiko:master-db --isi-ikk`, dikoreksi lebih lanjut di /peta-risiko-pja/master.
     */
    public const DAFTAR_IKK_AWAL = [
        ['Bekerja dengan Panas', '[CCV] Bekerja dengan Panas', false],
        ['Bekerja dengan Panas', 'Pemotongan', false],
        ['Bekerja dengan Panas', 'Pengelasan', false],
        ['Bekerja dengan Panas', 'Penggerindaan', false],
        ['Bekerja di Area Coal Processing Plant', '[CCV CPP] Perbaikan Unit di Luar Workshop (Area CPP)', false],
        ['Bekerja di Dekat Air', '[CCV] Bekerja di Dekat Air', false],
        ['Bekerja di Dekat Air', 'Bekerja di Dekat Air', false],
        ['Bekerja di Dekat Air', 'Pit Dewatering', false],
        ['Bekerja di Ketinggian', '[CCV] Bekerja di Ketinggian', false],
        ['Bekerja di Ketinggian', 'Ketinggian <10 m', false],
        ['Bekerja di Ketinggian', 'Ketinggian >10 m', false],
        ['Bekerja di Ketinggian', 'Pemasangan dan atau Penggunaan Perancah', false],
        ['Pengangkatan & Pengangkutan Unit/Material', '[CCV] Pengangkatan & Pengangkutan Unit/Material', false],
        ['Pengangkatan & Pengangkutan Unit/Material', 'Pengangkatan', false],
        ['Pengangkatan & Pengangkutan Unit/Material', 'Pengangkutan', false],
        ['Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang', '[CCV] Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang', true],
        ['Perbaikan, Perawatan & Fabrikasi Unit/Fasilitas Tambang', '[Trial CCV BMO1] Perbaikan dan fabrikasi unit/fasilitas tambang', true],
    ];

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
     * @param  list<array{id: string, nama: string, parent: string, tipe?: string, id_site?: string}>  $pjaList  dari DrmSql::pja() (hierarki BC/Mitra)
     * @param  list<array{pja_id: string, perusahaan: string}>  $perusahaanPerPja  dari DrmSql::perusahaanPerPja() (ClickHouse, per ID PJA)
     * @param  list<array{site: string, lokasi: string, detail: string, pic_lokasi: string, pic_detail_lokasi: string}>  $picMentah  dari DrmSql::picDetailLokasi()
     * @param  list<array{singkatan: string, perusahaan: string}>  $singkatanPerusahaan  dari DrmSql::singkatanPerusahaan()
     * @return array<string, int>  jumlah baris per tabel
     */
    public function isi(DrmMaster $master, array $cfg, array $lokasiPja, array $pjaList = [], array $perusahaanPerPja = [], array $picMentah = [], array $singkatanPerusahaan = []): array
    {
        if (! self::siap()) {
            throw new RuntimeException('Tabel master belum dimigrasi (2026_11_17_100000_create_drm_master_tables).');
        }
        $d = $master->data;
        $sekarang = now();
        $cap = fn (array $r) => $r + ['created_at' => $sekarang, 'updated_at' => $sekarang];
        $kelBaris = $master->kelompokBaris($cfg['proses_bisnis']);
        // nama PJA (dinormalisasi) => daftar nama perusahaan unik personilnya (PJA itu + mitra anaknya digabung).
        $perusahaanPerNamaPja = self::perusahaanPerNamaPja($pjaList, $perusahaanPerPja);
        // nama PJA (dinormalisasi) => daftar nama PJA anak (Mitra) SATU level, untuk kolom "PJA Child" di Alur Baseline.
        $anakPerNamaPja = self::anakPerNamaPja($pjaList);

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
            'drm_master_lokasi_pja' => array_map(fn ($x) => $cap(['site' => $x[0], 'lokasi' => $x[1], 'nama_pja' => $x[2], 'aktif' => true,
                'perusahaan' => implode("\n", $perusahaanPerNamaPja[self::normNamaPja($x[2])] ?? []),
                'pja_anak' => implode("\n", $anakPerNamaPja[self::normNamaPja($x[2])] ?? [])]), $lokasiPja),
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
        if (Schema::hasTable(self::TABEL_LOKASI_PIC)) {
            $rows = [];
            foreach (self::picDetlokPerLokasi($picMentah, $singkatanPerusahaan) as $kunci => $perusahaan) {
                [$site, $lokasi, $detail] = explode('|', $kunci, 3);
                $rows[] = $cap(['site' => $site, 'lokasi' => $lokasi, 'detail' => $detail, 'pic_perusahaan' => implode("\n", $perusahaan), 'aktif' => true]);
            }
            $isi[self::TABEL_LOKASI_PIC] = $rows;
        }
        if (Schema::hasTable(self::TABEL_PJA_PERUSAHAAN)) {
            $isi[self::TABEL_PJA_PERUSAHAAN] = array_map(
                fn ($r) => $cap(['nama_pja' => $r['nama'], 'perusahaan' => implode("\n", $r['perusahaan'])]),
                self::perusahaanLangsungPerNamaPja($pjaList, $perusahaanPerPja)
            );
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

        // Master satu tabel: label dibaca dari kolom item_sumber_* di drm_master_baris bila sudah diisi.
        [$obs, $oak] = [null, null];
        if (self::labelDiBaris()) {
            [$obs, $oak, $label] = self::labelDariKolom($baris);
        }

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
            'obs' => $obs ?? $teks('OBS'),
            'oak' => $oak ?? $teks('OAK'),
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

    /** Isi (timpa) tabel daftar IKK dari DAFTAR_IKK_AWAL. Tabel master lain tidak tersentuh. */
    public function isiIkk(): int
    {
        if (! Schema::hasTable(self::TABEL_IKK)) {
            throw new RuntimeException('Tabel '.self::TABEL_IKK.' belum dimigrasi (2026_11_25_100000_create_drm_master_ikk_table).');
        }
        $sekarang = now();
        $rows = array_map(fn (array $r) => ['aktivitas' => $r[0], 'sub_aktivitas' => $r[1], 'cek_luar_workshop' => $r[2],
            'aktif' => true, 'created_at' => $sekarang, 'updated_at' => $sekarang], self::DAFTAR_IKK_AWAL);
        DB::transaction(function () use ($rows) {
            DB::table(self::TABEL_IKK)->delete();
            foreach (array_chunk($rows, self::PECAH) as $potong) {
                DB::table(self::TABEL_IKK)->insert($potong);
            }
        });
        $this->catat(self::TABEL_IKK, '*', 'isi', null, ['baris' => count($rows)]);

        return count($rows);
    }

    /** Kolom label di drm_master_baris (master satu tabel), per jenis. */
    public const KOLOM_LABEL = ['OBS' => 'item_sumber_observasi', 'OAK' => 'item_sumber_oak', 'KET' => 'item_sumber_ketidaksesuaian'];

    /** Label sudah disimpan di kolom item_sumber_* drm_master_baris? (kolom ada dan sudah diisi --isi-label-baris) */
    public static function labelDiBaris(): bool
    {
        try {
            return Schema::hasColumn(self::TABEL_UTAMA, 'item_sumber_observasi')
                && DB::table(self::TABEL_UTAMA)->whereNotNull('item_sumber_observasi')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Teks kolom label → daftar label (satu per baris teks, kosong & duplikat dibuang). @return list<string> */
    public static function pisahLabel(?string $teks): array
    {
        $hasil = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $teks) ?: [] as $l) {
            $l = trim($l);
            if ($l !== '' && ! isset($hasil[DrmMaster::norm($l)])) {
                $hasil[DrmMaster::norm($l)] = $l;
            }
        }

        return array_values($hasil);
    }

    /** "ketidaksesuaian / sub ketidaksesuaian" → [k, sk] (dipisah pada " / " pertama). @return array{0: string, 1: string} */
    public static function pisahKet(string $label): array
    {
        $i = strpos($label, ' / ');

        return $i === false ? [trim($label), ''] : [trim(substr($label, 0, $i)), trim(substr($label, $i + 3))];
    }

    /** Kunci pencocokan nama PJA (obds.aaj_lokasi_dan_pja.nama_pja ↔ DrmSql::pja().nama): huruf kecil, spasi rapat. */
    public static function normNamaPja(string $nama): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($nama)) ?? '');
    }

    /**
     * Dipakai bersama oleh perusahaanPerNamaPja() dan picPerusahaanPerNamaPja(): anak = parent id => [id anak
     * langsung]; perIdLangsung = id PJA => daftar perusahaan personil LANGSUNG id itu (belum digabung apa pun).
     *
     * @param  list<array{id: string, nama: string, parent: string}>  $pjaList
     * @param  list<array{pja_id: string, perusahaan: string}>  $perusahaanPerPja
     * @return array{0: array<string, list<string>>, 1: array<string, list<string>>}
     */
    private static function anakDanPerusahaanLangsung(array $pjaList, array $perusahaanPerPja): array
    {
        $anak = [];
        foreach ($pjaList as $p) {
            $parent = (string) ($p['parent'] ?? '0');
            if ($parent !== '0' && $parent !== '') {
                $anak[$parent][] = (string) $p['id'];
            }
        }
        $perIdLangsung = [];
        foreach ($perusahaanPerPja as $r) {
            $id = (string) ($r['pja_id'] ?? '');
            $nama = trim((string) ($r['perusahaan'] ?? ''));
            if ($id !== '' && $nama !== '' && ! in_array($nama, $perIdLangsung[$id] ?? [], true)) {
                $perIdLangsung[$id][] = $nama;
            }
        }

        return [$anak, $perIdLangsung];
    }

    /**
     * Nama PJA (APAPUN — induk maupun anak) => perusahaan personil LANGSUNG id itu saja, TIDAK digabung dengan
     * siapa pun (bukan induk, bukan anak). Dasar tabel drm_master_pja_perusahaan — supaya diagram Alur Baseline
     * bisa menyaring PJA Child ke perusahaan tertentu (keputusan user 26 Nov 2026, ditemukan lewat filter
     * Perusahaan=BUMA BMO1: PJA Child yang tampil masih ikut subkontraktor lain yang kebetulan anak dari PJA
     * induk yang sama, karena sebelumnya cuma tahu NAMA anak, tidak tahu perusahaan anak itu sendiri).
     * Nama dikembalikan APA ADANYA (trim saja, BUKAN dinormalisasi) supaya tetap terbaca di tabel — pencocokan
     * di sisi pembaca pakai normNamaPja() (sama pola dengan drm_master_lokasi_pja.nama_pja). Kalau beberapa ID
     * kebetulan punya nama sama persis (mis. "Non PJA" dipakai berkali-kali), cuma yang pertama diambil.
     *
     * @param  list<array{id: string, nama: string, parent: string}>  $pjaList  dari DrmSql::pja()
     * @param  list<array{pja_id: string, perusahaan: string}>  $perusahaanPerPja  dari DrmSql::perusahaanPerPja()
     * @return list<array{nama: string, perusahaan: list<string>}>
     */
    public static function perusahaanLangsungPerNamaPja(array $pjaList, array $perusahaanPerPja): array
    {
        [, $perIdLangsung] = self::anakDanPerusahaanLangsung($pjaList, $perusahaanPerPja);
        $hasil = [];
        $terpakai = [];
        foreach ($pjaList as $p) {
            $perusahaan = $perIdLangsung[(string) $p['id']] ?? [];
            $nama = trim((string) $p['nama']);
            $kunci = self::normNamaPja($nama);
            if ($perusahaan === [] || $nama === '' || isset($terpakai[$kunci])) {
                continue;
            }
            $terpakai[$kunci] = true;
            sort($perusahaan, SORT_NATURAL | SORT_FLAG_CASE);
            $hasil[] = ['nama' => $nama, 'perusahaan' => $perusahaan];
        }

        return $hasil;
    }

    /**
     * Nama PJA (dinormalisasi, normNamaPja) => daftar nama perusahaan personilnya, PJA itu sendiri DIGABUNG dengan
     * personil PJA Mitra anaknya (sama pola dengan DrmSnapshotBuilder::pjaBc()/berkasPja() di sync utama — "yang
     * parent atau BC atau Mitra Kerja semuanya masuk", keputusan user 24 Nov 2026: jangan cuma personil yang
     * ditugaskan langsung ke ID PJA itu, anak Mitra-nya juga harus ikut) — KECUALI induk bertipe payung/
     * koordinator (Superior/Inspektor/Koordinator, lihat pjaPayungKoordinator): anaknya tersebar di site lain
     * jadi TIDAK digabung (ditemukan 26 Nov 2026, kasus sama dengan picPerusahaanPerNamaPja), tapi personil
     * LANGSUNG induk itu sendiri tetap masuk — itu memang benar ditugaskan ke ID itu.
     *
     * @param  list<array{id: string, nama: string, parent: string, tipe?: string, id_site?: string}>  $pjaList  dari DrmSql::pja()
     * @param  list<array{pja_id: string, perusahaan: string}>  $perusahaanPerPja  dari DrmSql::perusahaanPerPja()
     * @return array<string, list<string>>
     */
    public static function perusahaanPerNamaPja(array $pjaList, array $perusahaanPerPja): array
    {
        [$anak, $perIdLangsung] = self::anakDanPerusahaanLangsung($pjaList, $perusahaanPerPja);
        $hasil = [];
        foreach ($pjaList as $p) {
            $id = (string) $p['id'];
            $gabung = $perIdLangsung[$id] ?? [];
            if (! self::pjaPayungKoordinator((string) $p['nama'])) {
                foreach ($anak[$id] ?? [] as $idAnak) {
                    foreach ($perIdLangsung[$idAnak] ?? [] as $nama) {
                        if (! in_array($nama, $gabung, true)) {
                            $gabung[] = $nama;
                        }
                    }
                }
            }
            if ($gabung === []) {
                continue;
            }
            sort($gabung, SORT_NATURAL | SORT_FLAG_CASE);
            $hasil[self::normNamaPja((string) $p['nama'])] = $gabung;
        }

        return $hasil;
    }

    /**
     * Nama PJA (dinormalisasi, normNamaPja) => daftar nama PJA anak (Mitra) SATU level langsung di bawahnya
     * (id_parent di obds.m_pja) — BUKAN digabung rekursif seperti perusahaanPerNamaPja, karena di sini yang
     * ditampilkan adalah identitas PJA anaknya sendiri (kolom "PJA Child" di Alur Baseline, keputusan user
     * 26 Nov 2026), bukan atribut gabungan.
     *
     * @param  list<array{id: string, nama: string, parent: string}>  $pjaList  dari DrmSql::pja()
     * @return array<string, list<string>>
     */
    public static function anakPerNamaPja(array $pjaList): array
    {
        $namaPerId = [];
        foreach ($pjaList as $p) {
            $namaPerId[(string) $p['id']] = (string) $p['nama'];
        }
        $hasil = [];
        foreach ($pjaList as $p) {
            $parent = (string) ($p['parent'] ?? '0');
            if ($parent === '0' || $parent === '' || ! isset($namaPerId[$parent])) {
                continue;
            }
            $kunci = self::normNamaPja($namaPerId[$parent]);
            $nama = trim((string) $p['nama']);
            if ($nama !== '' && ! in_array($nama, $hasil[$kunci] ?? [], true)) {
                $hasil[$kunci][] = $nama;
            }
        }
        foreach ($hasil as &$list) {
            sort($list, SORT_NATURAL | SORT_FLAG_CASE);
        }
        unset($list);

        return $hasil;
    }

    /**
     * Kata kunci nama PJA "payung/koordinator" (Superior, Inspektor, Koordinator) — terdaftar di SATU lokasi
     * administratif tapi anak Mitra-nya sebenarnya PJA operasional di site LAIN (ditemukan 26 Nov 2026: HO/
     * Dermaga ber-PIC 32 perusahaan dari "SUPERIOR HO & TRANSPORTASI BC" dkk, padahal anaknya tersebar di
     * BMO/GMO/LMO/dll — bukan benar-benar di Dermaga HO). 132 dari 338 lokasi (39%) punya PJA jenis ini.
     * Dipakai di perusahaanPerNamaPja() (induk tetap masuk pakai personil LANGSUNGnya sendiri, cuma anaknya yang
     * tidak digabung) — bukan dari pja_dikecualikan config, itu untuk ranking dashboard, beda tujuan. PIC lokasi
     * fisik (drm_master_lokasi_pic) sejak 27 Nov 2026 tidak lagi bersumber dari personil sama sekali (lihat
     * picDetlokPerLokasi), jadi kasus payung/koordinator ini otomatis tidak relevan lagi untuk PIC.
     */
    private const PJA_PAYUNG_KOORDINATOR = ['superior', 'inspekt', 'inspector', 'koordinator'];

    private static function pjaPayungKoordinator(string $nama): bool
    {
        $nama = mb_strtolower($nama);
        foreach (self::PJA_PAYUNG_KOORDINATOR as $kata) {
            if (mb_stripos($nama, $kata) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * (site, lokasi, detail) => daftar nama perusahaan PIC RESMI, dari PIC_Detail_Lokasi di sheet "Coverage Area
     * All Site" (lewat ClickHouse, DrmSql::picDetailLokasi()) — BUKAN dari personil PJA/Mitra (ditemukan user 27
     * Nov 2026: filter Site=BMO1 x Perusahaan=BUMA di Alur Baseline masih menampilkan Detail Lokasi "(B PMO)
     * Warehouse KDC" yang PIC resminya KDC, bukan BUMA — personil yang kebetulan tercatat di PJA induknya BUKAN
     * representasi lokasi fisik ini). Mengganti picPerusahaanPerNamaPja()/picPerusahaanPerLokasi() yang
     * sebelumnya bersumber dari personil (dihapus total, bukan dipertahankan berdampingan — keputusan user).
     * Satu (site,lokasi,detail) bisa muncul di lebih dari satu tabel sumber (lihat DrmSql::picDetailLokasi) —
     * PIC_Detail_Lokasi-nya digabung (union), bukan dari satu sumber "menang" begitu saja.
     *
     * @param  list<array{site: string, lokasi: string, detail: string, pic_lokasi: string, pic_detail_lokasi: string}>  $picMentah  dari DrmSql::picDetailLokasi()
     * @param  list<array{singkatan: string, perusahaan: string}>  $singkatanPerusahaan  dari DrmSql::singkatanPerusahaan()
     * @return array<string, list<string>>  kunci "site|lokasi|detail"
     */
    public static function picDetlokPerLokasi(array $picMentah, array $singkatanPerusahaan): array
    {
        $namaPerSingkatan = [];
        foreach ($singkatanPerusahaan as $s) {
            $kode = mb_strtoupper(trim((string) ($s['singkatan'] ?? '')));
            $nama = trim((string) ($s['perusahaan'] ?? ''));
            if ($kode !== '' && $nama !== '') {
                $namaPerSingkatan[$kode] = $nama;
            }
        }
        $set = [];
        foreach ($picMentah as $r) {
            $site = trim((string) ($r['site'] ?? ''));
            $detail = trim((string) ($r['detail'] ?? ''));
            if ($site === '' || $detail === '') {
                continue;
            }
            $kunci = $site.'|'.trim((string) ($r['lokasi'] ?? '')).'|'.$detail;
            foreach (preg_split('/[,\/]/', (string) ($r['pic_detail_lokasi'] ?? '')) as $kode) {
                $kode = mb_strtoupper(trim($kode));
                if ($kode !== '' && isset($namaPerSingkatan[$kode])) {
                    $set[$kunci][$namaPerSingkatan[$kode]] = true;
                }
            }
        }
        $hasil = [];
        foreach ($set as $kunci => $daftar) {
            $list = array_keys($daftar);
            sort($list, SORT_NATURAL | SORT_FLAG_CASE);
            $hasil[$kunci] = $list;
        }

        return $hasil;
    }

    /**
     * Label OBS / OAK / Ketidaksesuaian dari kolom item_sumber_* baris aktif, dalam bentuk data DrmMaster.
     *
     * @return array{0: list<array{teks: string, ri: list<int>}>, 1: list<array{teks: string, ri: list<int>}>, 2: array<string, array<string, mixed>>}
     */
    private static function labelDariKolom(iterable $baris): array
    {
        $teks = ['OBS' => [], 'OAK' => []];
        $ket = [];
        foreach ($baris as $b) {
            foreach (['OBS', 'OAK'] as $jenis) {
                foreach (self::pisahLabel($b->{self::KOLOM_LABEL[$jenis]} ?? '') as $l) {
                    $teks[$jenis][DrmMaster::norm($l)] ??= ['teks' => $l, 'ri' => []];
                    $teks[$jenis][DrmMaster::norm($l)]['ri'][] = (int) $b->ri;
                }
            }
            foreach (self::pisahLabel($b->{self::KOLOM_LABEL['KET']} ?? '') as $l) {
                [$k, $sk] = self::pisahKet($l);
                $kunci = DrmMaster::norm($k).' / '.DrmMaster::norm($sk);
                $ket[$kunci] ??= ['k' => $k, 'sk' => $sk, 'ri' => [], 'sumber' => ''];
                $ket[$kunci]['ri'][] = (int) $b->ri;
            }
        }
        $rapi = fn (array $d) => array_values(array_map(fn ($t) => [...$t, 'ri' => array_values(array_unique($t['ri']))], $d));

        return [$rapi($teks['OBS']), $rapi($teks['OAK']), $ket];
    }

    /**
     * Isi kolom item_sumber_* drm_master_baris dari drm_master_teks (OBS/OAK aktif) dan drm_master_label (aktif),
     * supaya master menjadi satu tabel. Semua baris diisi (baris tanpa label = teks kosong), menimpa isi kolom lama.
     *
     * @return array{baris: int, OBS: int, OAK: int, KET: int}
     */
    public function isiLabelBaris(): array
    {
        if (! self::siap() || ! Schema::hasColumn(self::TABEL_UTAMA, 'item_sumber_observasi')) {
            throw new RuntimeException('Kolom item_sumber_* belum dimigrasi (2026_11_18_120000_add_item_sumber_to_drm_master_baris_table).');
        }
        $per = [];
        foreach (DB::table('drm_master_teks')->where('aktif', true)->orderBy('teks')->get(['jenis', 'teks', 'ri']) as $t) {
            $per[(int) $t->ri][(string) $t->jenis][] = (string) $t->teks;
        }
        foreach (DB::table('drm_master_label')->where('aktif', true)->orderBy('ketidaksesuaian')->orderBy('subketidaksesuaian')->get() as $l) {
            $per[(int) $l->ri]['KET'][] = trim((string) $l->ketidaksesuaian).' / '.trim((string) $l->subketidaksesuaian);
        }
        $jumlah = ['baris' => 0, 'OBS' => 0, 'OAK' => 0, 'KET' => 0];
        DB::transaction(function () use ($per, &$jumlah) {
            foreach (DB::table(self::TABEL_UTAMA)->pluck('ri') as $ri) {
                $nilai = ['updated_at' => now()];
                foreach (self::KOLOM_LABEL as $jenis => $kolom) {
                    $daftar = self::pisahLabel(implode("\n", $per[(int) $ri][$jenis] ?? []));
                    $nilai[$kolom] = implode("\n", $daftar);
                    $jumlah[$jenis] += count($daftar);
                }
                DB::table(self::TABEL_UTAMA)->where('ri', $ri)->update($nilai);
                $jumlah['baris']++;
            }
        });
        $this->catat(self::TABEL_UTAMA, '*', 'isi', null, ['item_sumber_*' => $jumlah]);

        return $jumlah;
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

        // Master satu tabel: tambahkan ke kolom item_sumber_oak (tanpa duplikat; label yang sudah ada tidak dihapus).
        if (self::labelDiBaris()) {
            return $this->turunkanOakKeKolom($kurasi, $subNorm, $riPerAkt);
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
     * @param  array<string, list<string>>  $kurasi
     * @param  array<string, array{0: string, 1: list<string>}>  $subNorm
     * @param  array<string, list<int>>  $riPerAkt
     * @return array{baris: int, label: int}
     */
    private function turunkanOakKeKolom(array $kurasi, array $subNorm, array $riPerAkt): array
    {
        $tambah = [];
        foreach ($kurasi as $oak => $daftarHira) {
            [$namaOak, $subs] = $subNorm[DrmMaster::normTeks((string) $oak)] ?? [(string) $oak, []];
            foreach ((array) $daftarHira as $hira) {
                foreach ($riPerAkt[DrmMaster::normTeks((string) $hira)] ?? [] as $ri) {
                    foreach ($subs as $sub) {
                        $tambah[$ri][] = trim($namaOak).' / '.trim((string) $sub);
                    }
                }
            }
        }
        $jumlah = ['baris' => 0, 'label' => 0];
        DB::transaction(function () use ($tambah, &$jumlah) {
            foreach (DB::table(self::TABEL_UTAMA)->whereIn('ri', array_keys($tambah))->get(['ri', 'item_sumber_oak']) as $b) {
                $ada = self::pisahLabel($b->item_sumber_oak);
                $kunci = array_flip(array_map(fn ($l) => DrmMaster::crcTeks($l), $ada));
                $baru = [];
                foreach ($tambah[(int) $b->ri] as $l) {
                    $c = DrmMaster::crcTeks($l);
                    if (! isset($kunci[$c])) {
                        $kunci[$c] = true;
                        $baru[] = $l;
                    }
                }
                if ($baru !== []) {
                    DB::table(self::TABEL_UTAMA)->where('ri', $b->ri)->update(['item_sumber_oak' => implode("\n", [...$ada, ...$baru]), 'updated_at' => now()]);
                    $jumlah['baris']++;
                    $jumlah['label'] += count($baru);
                }
            }
        });
        $this->catat(self::TABEL_UTAMA, 'item_sumber_oak', 'isi', null, $jumlah);

        return $jumlah;
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
