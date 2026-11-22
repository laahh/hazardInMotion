<?php

namespace App\Services\PetaRisiko\Drm;

use Carbon\CarbonImmutable;

/**
 * Beranda PJA (DRM 2.1): status mingguan per baris kelompok aktivitas × detail lokasi × bahaya-risiko.
 *
 * Bukti datang dari dua query per site (lihat DrmSql::berandaKelompok / berandaSpesifik) dalam bentuk
 * bit jarang "indeks:bit,...":
 *   kelompok (dl|kel): 1 = kelompok terdeteksi (label Hazard/Inspeksi spesifik)
 *   spesifik (dl|hid): 2 = teridentifikasi spesifik, 4 = belum closed (status kini bukan CLOSED/DONE),
 *                      8 = berulang (bahaya-risiko ini juga root cause insiden di lokasi mana pun, ≤ 365 hari sebelum temuan),
 *                      16 = OAK Stop Pekerjaan pada OAK yang labelnya (M12) dipetakan ke baris ini,
 *                      32 = butir checklist OAK Tidak Sesuai (lookup 10187) — ikut dihitung gap,
 *                      64 = (harian) insiden yang root cause-nya terpetakan ke bahaya ini.
 * Status baris = BERMASALAH bila ada bit 4/8/16/32 (gap = belum closed | berulang | OAK Stop | butir OAK Tidak
 * Sesuai); TERKONTROL bila terdeteksi tanpa masalah; selain itu TANPA LAPORAN. Bit 16 kelompok (snapshot lama) tetap dibaca.
 * Sumber deteksi per jenis laporan (17 Nov 2026, dokumentasi Risk Matrix 5x5): label persis di master — Hazard/Inspeksi
 * M09 (tanpa sumber yang dikecualikan), OBS jenis kegiatan M11, OAK aktivitas / sub M12 (bcbeats) — semuanya label
 * persis per baris, tanpa tebakan. Bit 16 = Stop Pekerjaan; bit 32 hanya bila config oak_tidak_sesuai_gap = true.
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
    public const KOLOM_MGMT = ['tot', 'full', 'part', 'gaponly', 'none', 'closed', 'ulang', 'stop', 'tidak_sesuai', 'cak',
        'idk', 'idg', 'tid', 'turun', 'laten'];

    /**
     * Status gabungan antar detail lokasi untuk kartu KPI (gaya dashboard DRM 2.1 lama, G > K > T):
     *   idk   = teridentifikasi & terkontrol (terdeteksi di ≥1 DL, tidak bermasalah di DL mana pun)
     *   idg   = teridentifikasi, ada gap (bermasalah di ≥1 DL: belum closed / berulang / OAK Stop)
     *   tid   = tidak teridentifikasi / open risk (tanpa laporan di semua DL)
     *   turun = idk dan level risiko residual < risiko awal (berhasil dikontrol)
     *   laten = tid dan pernah terkait insiden 12 bulan terakhir di salah satu DL (blind spot SAP)
     */
    private const LEVEL = ['Low' => 1, 'Medium' => 2, 'High' => 3, 'Significant' => 4];

    /** Batas pita cakupan (mode pita, ringkasan baseline unik): ≥80% DL terkontrol, 20–80%, >0–20%, 0%. */
    public const PITA_ATAS = 0.8;

    public const PITA_BAWAH = 0.2;

    /** @var array<string, string> detail lokasi → grup lokasi (diisi oleh denganGrup) */
    private array $grupDl = [];

    /** @var array<string, int|true> "dl|hid" → jumlah insiden 12 bulan terakhir yang terkait (diisi oleh denganInsiden) */
    private array $insidenPasangan = [];

    /**
     * Volume laporan per minggu untuk modal Baseline (diisi oleh indeks, tidak masuk berkas PJA):
     *   ["dl|hid"] = "wi:obs:oak:ketidaksesuaian:item obs:item oak:item label,..." — laporan yang menangkap bahaya ini.
     *
     * @var array<string, string>
     */
    private array $vol = [];

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

    /** @param array<string, int|true> $pasangan "dl|hid" → jumlah insiden 12 bulan terakhir yang terkait */
    public function denganInsiden(array $pasangan): self
    {
        $this->insidenPasangan = $pasangan;

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
        // Gap: belum closed | berulang | OAK Stop Pekerjaan | butir checklist OAK Tidak Sesuai (lookup 10187).
        $masalah = ($s & (self::BELUM_CLOSED | self::BERULANG | self::OAK_STOP | self::TIDAK_SESUAI)) | ($k & self::OAK_STOP);
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
        $this->vol = [];
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
            if ((string) ($r['v'] ?? '') !== '') {
                $this->vol[$r['dl'].'|'.$r['hid']] = (string) $r['v'];
            }
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
     *   gk[minggu][level]["grup|kel"] = angka KOLOM_MGMT kelompok aktivitas di dalam satu grup lokasi
     *   akt[minggu][level][aktivitas] / gka[minggu][level]["grup|aktivitas"] = angka KOLOM_MGMT per aktivitas HIRA
     *     (id_aktivitas master M03), di semua grup / di dalam satu grup
     *   dlw[minggu][level][dl] = angka KOLOM_MGMT per detail lokasi (satu baris = kelompok × bahaya di DL itu;
     *     kategori hanya penuh / hanya bermasalah / tanpa laporan)
     *   cak (indeks 9) = jumlah cakupan per mil (rata-rata = cak / 1000 / tot); $pita = true memakai kategori pita cakupan
     *   mx[minggu][level][kel] = ['a' => [sel => n], 'f' => [sel => n]] matriks 5x5 awal / final per kelompok,
     *     sel = kekerapan×5 + konsekuensi (matriks total = jumlah semua kelompok)
     *   kelTot[level][kel]    = baris ketat per kelompok;  kelGap[minggu][level][kel] = baris ketat bukan terkontrol penuh
     *   kgTot[level][i]       = baris rincian per kombinasi kelompok × grup lokasi ke-i
     *   u4[minggu][level][i]  = baris rincian kombinasi ke-i yang tidak terkontrol 4 minggu berturut-turut
     *
     * @param  array<string, mixed>  $berkasBeranda  hasil berkas()
     * @param  list<array<string, mixed>>  $insiden  insiden di detail lokasi PJA (tanggal Y-m-d)
     * @return array<string, mixed>
     */
    public function ringkasMgmt(array $dlIds, array $hidPerDl, array $hira, array $berkasBeranda, array $insiden, bool $pita = false, bool $rincian = false): array
    {
        $nW = (int) $this->cfg['beranda']['minggu'];
        [$unik, $kgTot, $u4, $unikGrup, $dlW] = $this->statusBaris($dlIds, $hidPerDl, $hira, $berkasBeranda, $nW, $rincian);

        $w = $kelGap = $kelW = $grpW = $gk = $akt = $gka = $mx = array_fill(0, $nW, []);
        $kelTot = [];
        foreach ($unik as $ku => $x) {
            [$lv, $kel] = [$x['lv'], $x['kel']];
            $kelTot[$lv][$kel] = ($kelTot[$lv][$kel] ?? 0) + 1;
            $h = $hira[substr($ku, strpos($ku, '|') + 1)] ?? [];
            $selAwal = self::sel((string) ($h['k1'] ?? ''), (string) ($h['c1'] ?? ''));
            $selSisa = self::sel((string) ($h['k2'] ?? ''), (string) ($h['c2'] ?? ''));
            foreach ($x['w'] as $i => $st) {
                [$kat, $cak] = self::nilai($st, $pita);
                $ek = self::ekstra($st, $h, $x['inc'] > 0);
                self::tambah($w[$i][$lv], $kat, $st[3], $cak, $ek);
                self::tambah($kelW[$i][$lv][$kel], $kat, $st[3], $cak, $ek);
                self::tambah($akt[$i][$lv][(string) ($h['a'] ?? '')], $kat, $st[3], $cak, $ek);
                if ($kat !== 1) {
                    $kelGap[$i][$lv][$kel] = ($kelGap[$i][$lv][$kel] ?? 0) + 1;
                }
                // Matriks 5x5: awal = risiko awal HIRA; final = residual bila terkontrol penuh, selain itu awal.
                if ($selAwal !== null) {
                    $mx[$i][$lv][$kel]['a'][$selAwal] = ($mx[$i][$lv][$kel]['a'][$selAwal] ?? 0) + 1;
                    $f = $kat === 1 && $selSisa !== null ? $selSisa : $selAwal;
                    $mx[$i][$lv][$kel]['f'][$f] = ($mx[$i][$lv][$kel]['f'][$f] ?? 0) + 1;
                }
            }
        }
        foreach ($unikGrup as $x) {
            $a = (string) ($hira[$x['hid']]['a'] ?? '');
            foreach ($x['w'] as $i => $st) {
                [$kat, $cak] = self::nilai($st, $pita);
                $ek = self::ekstra($st, $hira[$x['hid']] ?? [], $x['inc'] > 0);
                self::tambah($gka[$i][$x['lv']][$x['grp'].'|'.$a], $kat, $st[3], $cak, $ek);
                self::tambah($grpW[$i][$x['lv']][$x['grp']], $kat, $st[3], $cak, $ek);
                self::tambah($gk[$i][$x['lv']][$x['grp'].'|'.$x['kel']], $kat, $st[3], $cak, $ek);
            }
        }

        $batasLama = $this->sampai->subDays((int) $this->cfg['beranda']['lokasi_lama_hari'] - 1)->toDateString();
        $hari0 = $this->hari0();
        $dlast = (array) $berkasBeranda['dlast'];

        return [
            // Daftar baris per status untuk modal kartu KPI (hanya ringkasan unik; lihat rincianBaris).
            ...($rincian ? ['rincian' => self::rincianBaris($unik, $unikGrup, $hira, $pita)] : []),
            'w' => $w,
            'kel' => $kelW,
            'grp' => $grpW,
            'gk' => $gk,
            'akt' => $akt,
            'gka' => $gka,
            'dlw' => $dlW,
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
    private function statusBaris(array $dlIds, array $hidPerDl, array $hira, array $berkasBeranda, int $nW, bool $volume = false): array
    {
        $kd = (array) $berkasBeranda['kd'];
        $sp = (array) $berkasBeranda['sp'];
        $umum = array_map(fn ($l) => array_flip($l), (array) $berkasBeranda['umum']);
        $unik = $unikGrup = $kgIdx = $kgTot = [];
        $u4 = $dlW = array_fill(0, $nW, []);
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
                $unik[$ku] ??= ['lv' => $lv, 'kel' => $kel, 'inc' => 0, 'w' => array_fill(0, $nW, [0, 0, 0, 0])];
                $unikGrup[$kgu] ??= ['lv' => $lv, 'grp' => $grp, 'kel' => $kel, 'hid' => (string) $hid, 'inc' => 0, 'w' => array_fill(0, $nW, [0, 0, 0, 0])];
                // Jumlah insiden terkait, dijumlah antar detail lokasi (satu insiden hanya di satu DL).
                $nInc = (int) ($this->insidenPasangan[$d.'|'.$hid] ?? 0);
                $unik[$ku]['inc'] += $nInc;
                $unikGrup[$kgu]['inc'] += $nInc;
                if ($volume) {
                    // [obs, oak, ketidaksesuaian, item obs, item oak, item label] per minggu, dijumlah antar detail lokasi.
                    foreach (self::uraiVol($this->vol[$d.'|'.$hid] ?? '') as $i => $n) {
                        $unik[$ku]['v'][$i] = self::jumlahVol($unik[$ku]['v'][$i] ?? null, $n);
                        $unikGrup[$kgu]['v'][$i] = self::jumlahVol($unikGrup[$kgu]['v'][$i] ?? null, $n);
                    }
                }
                $ok = [];
                for ($i = 0; $i < $nW; $i++) {
                    [$st, $m] = self::status($kw[$i] ?? 0, $sw[$i] ?? 0);
                    $ok[$i] = $st === 'ok';
                    $unik[$ku]['w'][$i] = self::langkah($unik[$ku]['w'][$i], $ok[$i], $st === 'bm', $m);
                    $unikGrup[$kgu]['w'][$i] = self::langkah($unikGrup[$kgu]['w'][$i], $ok[$i], $st === 'bm', $m);
                    // Per detail lokasi: satu baris = kelompok × bahaya di DL ini → penuh / hanya bermasalah / tanpa laporan.
                    self::tambah($dlW[$i][$lv][$d], self::kategori([1, (int) $ok[$i], (int) ($st === 'bm')]), $m);
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

        return [$unik, $kgTot, $u4, $unikGrup, $dlW];
    }

    /**
     * Baris gabungan per status mingguan untuk modal kartu KPI. Kode minggu (indeks 0 = minggu berjalan):
     * K = teridentifikasi & terkontrol, G = ada gap, T = tidak teridentifikasi. inc = jumlah insiden 12 bulan terkait
     * (laten bila T), tr = residual HIRA lebih rendah dari risiko awal (turun kategori bila K).
     *   u: [kel, hid, level, inc, tr, kode, gap, vol]          — baseline unik (Overview / Kelompok Aktivitas / Semua PJA)
     *   g: [grup, kel, hid, level, inc, tr, kode, gap, vol]    — per grup lokasi (tab Group Lokasi)
     * gap = satu digit hex per minggu dari bit masalah >> 2 (1 belum closed, 2 berulang, 4 OAK Stop, 8 butir tidak sesuai).
     * vol = "wi:obs:oak:ketidaksesuaian:item obs:item oak:item label,..." (minggu tanpa laporan tidak ditulis);
     *       laporan dijumlah antar detail lokasi; item = label OBS (M11) / OAK (M12) / Hazard (M09) BERBEDA di semua DL.
     *
     * @return array{u: list<list<int|string>>, g: list<list<int|string>>}
     */
    private static function rincianBaris(array $unik, array $unikGrup, array $hira, bool $pita = false): array
    {
        // Kategori (1 terkontrol penuh … 4) per minggu, aturan sama dengan kartu & matriks 5x5 (final = residual bila 1).
        $kat = fn (array $w) => implode('', array_map(fn ($st) => (string) self::nilai($st, $pita)[0], $w));
        $kode = fn (array $w) => implode('', array_map(fn ($st) => $st[2] > 0 ? 'G' : ($st[1] > 0 ? 'K' : 'T'), $w));
        $gap = fn (array $w) => implode('', array_map(fn ($st) => dechex(($st[3] >> 2) & 0xF), $w));
        $vol = function (array $v): string {
            ksort($v);

            // [laporan obs, oak, ket, item obs, item oak, item label] — item = jumlah label berbeda.
            return implode(',', array_map(fn ($i, $n) => $i.':'.implode(':', [$n[0], $n[1], $n[2], count($n[3]), count($n[4]), count($n[5])]),
                array_keys($v), $v));
        };
        $turun = function (string $hid) use ($hira): int {
            $h = $hira[$hid] ?? [];
            $n2 = self::LEVEL[(string) ($h['n2'] ?? '')] ?? 0;

            return (int) ($n2 > 0 && $n2 < (self::LEVEL[(string) ($h['n1'] ?? '')] ?? 0));
        };
        $u = $g = [];
        foreach ($unik as $ku => $x) {
            $hid = substr($ku, strpos($ku, '|') + 1);
            $u[] = [$x['kel'], $hid, $x['lv'], $x['inc'], $turun($hid), $kode($x['w']), $gap($x['w']), $vol($x['v'] ?? []), $kat($x['w'])];
        }
        foreach ($unikGrup as $x) {
            $g[] = [$x['grp'], $x['kel'], $x['hid'], $x['lv'], $x['inc'], $turun($x['hid']), $kode($x['w']), $gap($x['w']), $vol($x['v'] ?? []), $kat($x['w'])];
        }

        return ['u' => $u, 'g' => $g];
    }

    /**
     * "wi:obs:oak:ket:id.id:id:id.id,..." → [wi => [obs, oak, ket, {id label OBS}, {id label OAK}, {id label Hazard}]].
     * Tiga angka pertama = jumlah laporan; tiga sisanya himpunan ID label (CRC) supaya item dihitung sebagai label
     * BERBEDA lintas detail lokasi, bukan dijumlah per detail lokasi.
     *
     * @return array<int, array{0: int, 1: int, 2: int, 3: array<string, true>, 4: array<string, true>, 5: array<string, true>}>
     */
    private static function uraiVol(string $jarang): array
    {
        $hasil = [];
        $himpunan = fn (string $x) => $x === '' ? [] : array_fill_keys(explode('.', $x), true);
        foreach ($jarang === '' ? [] : explode(',', $jarang) as $p) {
            $x = explode(':', $p) + array_fill(0, 7, '');
            $hasil[(int) $x[0]] = [(int) $x[1], (int) $x[2], (int) $x[3], $himpunan($x[4]), $himpunan($x[5]), $himpunan($x[6])];
        }

        return $hasil;
    }

    /** Gabung volume dua detail lokasi: jumlah laporan dijumlah, himpunan label digabung. */
    private static function jumlahVol(?array $a, array $b): array
    {
        return $a === null ? $b : [$a[0] + $b[0], $a[1] + $b[1], $a[2] + $b[2], $a[3] + $b[3], $a[4] + $b[4], $a[5] + $b[5]];
    }

    /** Tambah satu detail lokasi ke status baris ketat [n DL, n terkontrol, n bermasalah, bit]. */
    private static function langkah(array $c, bool $ok, bool $bm, int $m): array
    {
        return [$c[0] + 1, $c[1] + (int) $ok, $c[2] + (int) $bm, $c[3] | $m];
    }

    /** Kategori satuan ketat dari [n DL, n terkontrol, n bermasalah, bit]: 1 penuh, 2 sebagian, 3 hanya bermasalah, 4 tanpa laporan. */
    /**
     * Kategori + cakupan (per mil) satu baris dari [n DL, n terkontrol, n bermasalah, bit].
     * Ketat (spec, per PJA): 1 penuh, 2 sebagian, 3 hanya bermasalah, 4 tanpa laporan.
     * Pita (ringkasan baseline unik lintas banyak detail lokasi, keputusan user 17 Nov 2026):
     *   1 = ≥80% DL terkontrol, 2 = 20–80%, 3 = >0–20%, 4 = 0% (tidak terkontrol di DL mana pun).
     *
     * @return array{0: int, 1: int}
     */
    private static function nilai(array $st, bool $pita): array
    {
        $r = $st[0] > 0 ? $st[1] / $st[0] : 0.0;
        $cak = (int) round(1000 * $r);
        if (! $pita) {
            return [self::kategori($st), $cak];
        }

        return [$r >= self::PITA_ATAS ? 1 : ($r >= self::PITA_BAWAH ? 2 : ($r > 0 ? 3 : 4)), $cak];
    }

    private static function kategori(array $st): int
    {
        [$n, $ok, $bm] = $st;

        return $ok === $n ? 1 : ($ok > 0 ? 2 : ($bm > 0 ? 3 : 4));
    }

    /** Tambah satu baris ketat ke vektor KOLOM_MGMT. */
    /**
     * [idk, idg, tid, turun, laten] satu baris gabungan dari [n DL, n terkontrol, n bermasalah, bit].
     *
     * @param  array<string, mixed>  $h  baris HIRA (n1 = risiko awal, n2 = residual)
     * @return list<int>
     */
    private static function ekstra(array $st, array $h, bool $insiden): array
    {
        $gap = $st[2] > 0;
        $k = ! $gap && $st[1] > 0;
        $t = ! $gap && $st[1] === 0;
        $turun = $k && (self::LEVEL[(string) ($h['n2'] ?? '')] ?? 0) > 0
            && self::LEVEL[(string) $h['n2']] < (self::LEVEL[(string) ($h['n1'] ?? '')] ?? 0);

        return [(int) $k, (int) $gap, (int) $t, (int) $turun, (int) ($t && $insiden)];
    }

    /** @param list<int> $ekstra [idk, idg, tid, turun, laten] (kosong untuk baris per detail lokasi) */
    private static function tambah(?array &$v, int $kat, int $m, int $cak = 0, array $ekstra = []): void
    {
        $v ??= array_fill(0, count(self::KOLOM_MGMT), 0);
        $v[9] += $cak;
        foreach ($ekstra as $i => $x) {
            $v[10 + $i] += $x;
        }
        $v[0]++;
        $v[$kat]++;
        $v[5] += (int) (bool) ($m & self::BELUM_CLOSED);
        $v[6] += (int) (bool) ($m & self::BERULANG);
        $v[7] += (int) (bool) ($m & self::OAK_STOP);
        $v[8] += (int) (bool) ($m & self::TIDAK_SESUAI);
    }

    /** Indeks sel matriks 5x5 (baris = kekerapan Hampir Pasti → Langka, kolom = konsekuensi), null bila tidak dikenal. */
    /** Indeks sel matriks 5x5 (kekerapan × 5 + konsekuensi), null bila nilai HIRA tidak dikenal. */
    public static function sel(string $kekerapan, string $konsekuensi): ?int
    {
        $i = array_search($kekerapan, DrmSnapshotBuilder::KEKERAPAN, true);
        $j = array_search($konsekuensi, DrmSnapshotBuilder::KONSEKUENSI, true);

        return $i === false || $j === false ? null : $i * 5 + $j;
    }

}
