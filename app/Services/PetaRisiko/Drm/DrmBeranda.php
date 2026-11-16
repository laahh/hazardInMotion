<?php

namespace App\Services\PetaRisiko\Drm;

use Carbon\CarbonImmutable;

/**
 * Beranda PJA (DRM 2.1): status mingguan per baris kelompok aktivitas × detail lokasi × bahaya-risiko.
 *
 * Bukti datang dari dua query per site (lihat DrmSql::berandaKelompok / berandaSpesifik) dalam bentuk
 * bit jarang "indeks:bit,...":
 *   kelompok (dl|kel): 1 = kelompok terdeteksi (Obs/OAK/label spesifik), 16 = OAK Stop Pekerjaan
 *   spesifik (dl|hid): 2 = teridentifikasi spesifik, 4 = belum closed (status kini bukan CLOSED/DONE),
 *                      8 = berulang (≥3 laporan label sama di detail lokasi dalam minggu itu),
 *                      32 = butir checklist tidak sesuai (hanya informasi, bukan gap),
 *                      64 = (harian) insiden yang root cause-nya terpetakan ke bahaya ini.
 * Status baris = BERMASALAH bila ada bit 4/8 atau OAK stop kelompok (aturan DRM 2.1: gap = belum closed |
 * berulang | OAK Stop); TERKONTROL bila terdeteksi tanpa masalah; selain itu TANPA LAPORAN.
 *
 * Logika yang sama dipakai JS halaman PJA (pja.blade.php → fungsi statusBit); ubah keduanya bersamaan.
 */
class DrmBeranda
{
    public const DETEKSI = 1;

    public const SPESIFIK = 2;

    public const BELUM_CLOSED = 4;

    public const BERULANG = 8;

    public const OAK_STOP = 16;

    public const TIDAK_SESUAI = 32;

    public const INSIDEN = 64;

    /** Urutan angka per level di tabel perbandingan (satuan ketat): total, 4 kategori, baris dengan tiap jenis masalah. */
    public const KOLOM_MGMT = ['tot', 'full', 'part', 'gaponly', 'none', 'closed', 'ulang', 'stop', 'tidak_sesuai'];

    /** @var array<string, string> detail lokasi → grup lokasi (diisi oleh denganGrup) */
    private array $grupDl = [];

    /** @param array<string, mixed> $cfg */
    public function __construct(private readonly array $cfg, private readonly CarbonImmutable $sampai)
    {
    }

    /** @param array<string, array<string, mixed>> $dl */
    public function denganGrup(array $dl): self
    {
        $this->grupDl = array_map(fn ($r) => (string) ($r['grp'] ?? ''), $dl);

        return $this;
    }

    /** @return list<string> tanggal Minggu awal tiap minggu, indeks 0 = minggu berjalan */
    public function minggu(): array
    {
        $wk0 = $this->sampai->startOfWeek(CarbonImmutable::SUNDAY);

        return array_map(fn (int $i) => $wk0->subDays(7 * $i)->toDateString(), range(0, (int) $this->cfg['beranda']['minggu'] - 1));
    }

    public function hari0(): string
    {
        return $this->sampai->subDays((int) $this->cfg['beranda']['hari'] - 1)->toDateString();
    }

    /** Status satu baris dari bit kelompok (K) dan bit spesifik (S): ['ok'|'bm'|'none', bit masalah]. */
    public static function status(int $k, int $s): array
    {
        $masalah = ($s & (self::BELUM_CLOSED | self::BERULANG)) | ($k & self::OAK_STOP);
        if ($masalah) {
            return ['bm', $masalah];
        }

        return [($k & self::DETEKSI) || ($s & self::SPESIFIK) ? 'ok' : 'none', 0];
    }

    /** @return array<int, int> "0:3,2:17" → [0 => 3, 2 => 17] */
    public static function urai(string $jarang): array
    {
        $hasil = [];
        foreach ($jarang === '' ? [] : explode(',', $jarang) as $p) {
            [$i, $b] = explode(':', $p) + [1 => '0'];
            $hasil[(int) $i] = ($hasil[(int) $i] ?? 0) | (int) $b;
        }

        return $hasil;
    }

    /**
     * Indeks baris query berandaKelompok / berandaSpesifik.
     *
     * @param  list<array<string, mixed>>  $kelompok
     * @param  list<array<string, mixed>>  $spesifik
     * @return array{kd: array<string, array{0: string, 1: string, 2: string}>, sp: array<string, array{0: string, 1: string}>, dlast: array<string, string>}
     */
    public function indeks(array $kelompok, array $spesifik): array
    {
        $kd = $dlast = $sp = [];
        foreach ($kelompok as $r) {
            $last = (string) $r['last'];
            if ((string) $r['kel'] === '') {
                $dlast[(string) $r['dl']] = $last;
            } else {
                $kd[$r['dl'].'|'.$r['kel']] = [(string) $r['w'], (string) $r['h'], $last];
            }
        }
        foreach ($spesifik as $r) {
            $sp[$r['dl'].'|'.$r['hid']] = [(string) $r['w'], (string) $r['h']];
        }

        return ['kd' => $kd, 'sp' => $sp, 'dlast' => $dlast];
    }

    /**
     * Blok `beranda` di file satu PJA.
     *
     * @param  list<string>  $dlIds
     * @param  array<string, list<string>>  $hidPerDl  pasangan baseline di detail lokasi PJA
     * @param  array<string, array<string, mixed>>  $hira
     * @param  array<string, array<string, mixed>>  $dl
     * @param  array{kd: array<string, mixed>, sp: array<string, mixed>, dlast: array<string, string>}  $idx
     * @param  array<string, list<array<string, mixed>>>  $rcPerDl
     * @return array<string, mixed>
     */
    public function berkas(array $dlIds, array $hidPerDl, array $hira, array $dl, array $idx, array $rcPerDl): array
    {
        $kd = $sp = $dlast = [];
        foreach ($dlIds as $d) {
            $dlast[$d] = $idx['dlast'][$d] ?? '';
            foreach ($hidPerDl[$d] ?? [] as $hid) {
                $kel = (string) ($hira[$hid]['proses'] ?? 'X');
                if (isset($idx['kd'][$d.'|'.$kel])) {
                    $kd[$d.'|'.$kel] = $idx['kd'][$d.'|'.$kel];
                }
                if (isset($idx['sp'][$d.'|'.$hid])) {
                    $sp[$d.'|'.$hid] = $idx['sp'][$d.'|'.$hid];
                }
            }
        }
        $rc = array_merge(...array_map(fn ($d) => $rcPerDl[$d] ?? [], $dlIds));
        usort($rc, fn ($a, $b) => strcmp((string) $b['tanggal'], (string) $a['tanggal']) ?: strcmp((string) $a['no'], (string) $b['no']));

        return [
            'kd' => (object) $kd,
            'sp' => (object) $sp,
            'dlast' => (object) $dlast,
            'umum' => (object) $this->bahayaUmum($dlIds, $hidPerDl, $hira, $dl),
            'rc' => array_map(fn ($r) => [
                'no' => (string) $r['no'], 'dl' => (string) $r['dl'], 'tanggal' => (string) $r['tanggal'], 'kategori' => (string) $r['kategori'],
                'detlok' => (string) $r['detlok'], 'lokasi' => (string) $r['lokasi'], 'label' => (string) $r['label'],
                'sebelum' => (int) $r['sebelum'], 'bulan' => (int) $r['bulan'], 'sesudah' => (int) $r['sesudah'],
                'kSebelum' => (int) $r['k_sebelum'], 'kSesudah' => (int) $r['k_sesudah'],
                'terakhir' => in_array((string) ($r['terakhir'] ?? ''), ['', '1970-01-01'], true) ? '' : (string) $r['terakhir'],
                'belum' => (int) $r['belum_closed'],
            ], $rc),
        ];
    }

    /**
     * Bahaya umum per grup lokasi: teks bahaya yang sama muncul di ≥ N kelompok aktivitas dalam grup itu
     * (di detail lokasi PJA ini). Ditampilkan terpisah dan tidak masuk pembagi.
     *
     * @return array<string, list<string>>  grup => daftar hid
     */
    public function bahayaUmum(array $dlIds, array $hidPerDl, array $hira, array $dl): array
    {
        $min = (int) $this->cfg['beranda']['umum_min_kelompok'];
        $perGrup = [];
        foreach ($dlIds as $d) {
            $g = (string) ($dl[$d]['grp'] ?? '');
            foreach ($hidPerDl[$d] ?? [] as $hid) {
                $teks = mb_strtolower(trim((string) ($hira[$hid]['bn'] ?? '')));
                $perGrup[$g][$teks]['kel'][(string) ($hira[$hid]['proses'] ?? 'X')] = true;
                $perGrup[$g][$teks]['hid'][$hid] = true;
            }
        }
        $hasil = [];
        foreach ($perGrup as $g => $teks) {
            foreach ($teks as $t) {
                if (count($t['kel']) >= $min) {
                    $hasil[$g] = [...($hasil[$g] ?? []), ...array_map('strval', array_keys($t['hid']))];
                }
            }
        }

        return $hasil;
    }

    /**
     * Ringkasan satu PJA untuk tabel "Perbandingan antar PJA" (tanpa flag), satuan KETAT:
     * satu baris = kelompok aktivitas × bahaya-risiko, dihitung sekali walau ada di beberapa detail lokasi.
     * Terkontrol penuh bila semua detail lokasinya terkontrol; sebagian bila sebagian; hanya bermasalah bila
     * tidak ada yang terkontrol tetapi ada laporan bermasalah; selain itu tanpa laporan.
     *
     * Semua angka bisa dijumlahkan antar level risiko, sehingga filter level dihitung di browser:
     *   w[minggu][level]      = angka sesuai KOLOM_MGMT
     *   kel[minggu][level][kel] / grp[minggu][level][grup] = angka KOLOM_MGMT per kelompok / per grup lokasi
     *     (grup: satu baris = grup × kelompok × bahaya, status dihitung dari detail lokasi grup itu saja)
     *   mx[minggu][level]     = ['a' => [sel => n], 'f' => [sel => n]] matriks 5x5 awal / final, sel = kekerapan×5 + konsekuensi
     *   kelTot[level][kel]    = baris ketat per kelompok;  kelGap[minggu][level][kel] = baris ketat bukan terkontrol penuh
     *   kgTot[level][i]       = baris rincian per kombinasi kelompok × grup lokasi ke-i
     *   u4[minggu][level][i]  = baris rincian kombinasi ke-i yang tidak terkontrol 4 minggu berturut-turut
     *
     * @param  array<string, mixed>  $berkasBeranda  hasil berkas()
     * @param  list<array<string, mixed>>  $insiden  insiden di detail lokasi PJA (tanggal Y-m-d)
     * @return array<string, mixed>
     */
    public function ringkasMgmt(array $dlIds, array $hidPerDl, array $hira, array $berkasBeranda, array $insiden): array
    {
        $nW = (int) $this->cfg['beranda']['minggu'];
        [$unik, $kgTot, $u4, $unikGrup] = $this->statusBaris($dlIds, $hidPerDl, $hira, $berkasBeranda, $nW);

        $w = $kelGap = $kelW = $grpW = $mx = array_fill(0, $nW, []);
        $kelTot = [];
        foreach ($unik as $ku => $x) {
            [$lv, $kel] = [$x['lv'], $x['kel']];
            $kelTot[$lv][$kel] = ($kelTot[$lv][$kel] ?? 0) + 1;
            $h = $hira[substr($ku, strpos($ku, '|') + 1)] ?? [];
            $selAwal = self::sel((string) ($h['k1'] ?? ''), (string) ($h['c1'] ?? ''));
            $selSisa = self::sel((string) ($h['k2'] ?? ''), (string) ($h['c2'] ?? ''));
            foreach ($x['w'] as $i => $st) {
                $kat = self::kategori($st);
                self::tambah($w[$i][$lv], $kat, $st[3]);
                self::tambah($kelW[$i][$lv][$kel], $kat, $st[3]);
                if ($kat !== 1) {
                    $kelGap[$i][$lv][$kel] = ($kelGap[$i][$lv][$kel] ?? 0) + 1;
                }
                // Matriks 5x5: awal = risiko awal HIRA; final = residual bila terkontrol penuh, selain itu awal.
                if ($selAwal !== null) {
                    $mx[$i][$lv]['a'][$selAwal] = ($mx[$i][$lv]['a'][$selAwal] ?? 0) + 1;
                    $f = $kat === 1 && $selSisa !== null ? $selSisa : $selAwal;
                    $mx[$i][$lv]['f'][$f] = ($mx[$i][$lv]['f'][$f] ?? 0) + 1;
                }
            }
        }
        foreach ($unikGrup as $x) {
            foreach ($x['w'] as $i => $st) {
                self::tambah($grpW[$i][$x['lv']][$x['grp']], self::kategori($st), $st[3]);
            }
        }

        $batasLama = $this->sampai->subDays((int) $this->cfg['beranda']['lokasi_lama_hari'] - 1)->toDateString();
        $hari0 = $this->hari0();
        $dlast = (array) $berkasBeranda['dlast'];

        return [
            'w' => $w,
            'kel' => $kelW,
            'grp' => $grpW,
            'mx' => $mx,
            'kelTot' => $kelTot,
            'kelGap' => $kelGap,
            'kgTot' => $kgTot,
            'u4' => $u4,
            'lama' => count(array_filter($dlIds, fn ($d) => ($dlast[$d] ?? '') === '' || $dlast[$d] < $batasLama)),
            'insiden' => count(array_filter($insiden, fn ($i) => (string) $i['tanggal'] >= $hari0 && (string) $i['tanggal'] <= $this->sampai->toDateString())),
        ];
    }

    /**
     * Status per minggu tiap baris rincian (detail lokasi × bahaya, tanpa bahaya umum), digabung ke baris ketat.
     *
     * @return array{0: array<string, array{lv: string, kel: string, w: list<array{0: int, 1: int, 2: int, 3: int}>}>, 1: array<string, array<int, int>>, 2: list<array<string, array<int, int>>>}
     *   [baris ketat (n, ok, bermasalah, bit masalah per minggu), kgTot, u4]
     */
    private function statusBaris(array $dlIds, array $hidPerDl, array $hira, array $berkasBeranda, int $nW): array
    {
        $kd = (array) $berkasBeranda['kd'];
        $sp = (array) $berkasBeranda['sp'];
        $umum = array_map(fn ($l) => array_flip($l), (array) $berkasBeranda['umum']);
        $unik = $unikGrup = $kgIdx = $kgTot = [];
        $u4 = array_fill(0, $nW, []);
        $kwCache = [];

        foreach ($dlIds as $d) {
            $grp = (string) ($this->grupDl[$d] ?? '');
            foreach ($hidPerDl[$d] ?? [] as $hid) {
                $h = $hira[$hid] ?? null;
                if ($h === null || isset($umum[$grp][$hid])) {
                    continue;
                }
                $kel = (string) ($h['proses'] ?? 'X');
                $lv = (string) $h['n1'];
                $kw = $kwCache[$d.'|'.$kel] ??= self::urai((string) ($kd[$d.'|'.$kel][0] ?? ''));
                $sw = self::urai((string) ($sp[$d.'|'.$hid][0] ?? ''));
                $ku = $kel.'|'.$hid;
                $kgu = $grp.'|'.$ku;
                $unik[$ku] ??= ['lv' => $lv, 'kel' => $kel, 'w' => array_fill(0, $nW, [0, 0, 0, 0])];
                $unikGrup[$kgu] ??= ['lv' => $lv, 'grp' => $grp, 'w' => array_fill(0, $nW, [0, 0, 0, 0])];
                $ok = [];
                for ($i = 0; $i < $nW; $i++) {
                    [$st, $m] = self::status($kw[$i] ?? 0, $sw[$i] ?? 0);
                    $ok[$i] = $st === 'ok';
                    $unik[$ku]['w'][$i] = self::langkah($unik[$ku]['w'][$i], $ok[$i], $st === 'bm', $m);
                    $unikGrup[$kgu]['w'][$i] = self::langkah($unikGrup[$kgu]['w'][$i], $ok[$i], $st === 'bm', $m);
                }
                $kg = $kgIdx[$kel.'|'.$grp] ??= count($kgIdx);
                $kgTot[$lv][$kg] = ($kgTot[$lv][$kg] ?? 0) + 1;
                for ($i = 0; $i + 3 < $nW; $i++) {
                    if (! $ok[$i] && ! $ok[$i + 1] && ! $ok[$i + 2] && ! $ok[$i + 3]) {
                        $u4[$i][$lv][$kg] = ($u4[$i][$lv][$kg] ?? 0) + 1;
                    }
                }
            }
        }

        return [$unik, $kgTot, $u4, $unikGrup];
    }

    /** Tambah satu detail lokasi ke status baris ketat [n DL, n terkontrol, n bermasalah, bit]. */
    private static function langkah(array $c, bool $ok, bool $bm, int $m): array
    {
        return [$c[0] + 1, $c[1] + (int) $ok, $c[2] + (int) $bm, $c[3] | $m];
    }

    /** Kategori satuan ketat dari [n DL, n terkontrol, n bermasalah, bit]: 1 penuh, 2 sebagian, 3 hanya bermasalah, 4 tanpa laporan. */
    private static function kategori(array $st): int
    {
        [$n, $ok, $bm] = $st;

        return $ok === $n ? 1 : ($ok > 0 ? 2 : ($bm > 0 ? 3 : 4));
    }

    /** Tambah satu baris ketat ke vektor KOLOM_MGMT. */
    private static function tambah(?array &$v, int $kat, int $m): void
    {
        $v ??= array_fill(0, count(self::KOLOM_MGMT), 0);
        $v[0]++;
        $v[$kat]++;
        $v[5] += (int) (bool) ($m & self::BELUM_CLOSED);
        $v[6] += (int) (bool) ($m & self::BERULANG);
        $v[7] += (int) (bool) ($m & self::OAK_STOP);
        $v[8] += (int) (bool) ($m & self::TIDAK_SESUAI);
    }

    /** Indeks sel matriks 5x5 (baris = kekerapan Hampir Pasti → Langka, kolom = konsekuensi), null bila tidak dikenal. */
    private static function sel(string $kekerapan, string $konsekuensi): ?int
    {
        $i = array_search($kekerapan, DrmSnapshotBuilder::KEKERAPAN, true);
        $j = array_search($konsekuensi, DrmSnapshotBuilder::KONSEKUENSI, true);

        return $i === false || $j === false ? null : $i * 5 + $j;
    }

}
