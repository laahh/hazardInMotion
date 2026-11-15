<?php

namespace App\Services\PetaRisiko\Drm;

use Carbon\CarbonImmutable;

/**
 * Beranda PJA (DRM 2.1): status mingguan per baris kelompok aktivitas × detail lokasi × bahaya-risiko.
 *
 * Bukti datang dari dua query per site (lihat DrmSql::berandaKelompok / berandaSpesifik) dalam bentuk
 * bit jarang "indeks:bit,...":
 *   kelompok (dl|kel): 1 = kelompok terdeteksi (Obs/OAK/label spesifik), 16 = OAK Stop Pekerjaan
 *   spesifik (dl|hid): 2 = teridentifikasi spesifik, 4 = belum closed, 8 = berulang, 32 = butir tidak sesuai,
 *                      64 = (harian) insiden yang root cause-nya terpetakan ke bahaya ini.
 * Status baris = BERMASALAH bila ada bit 4/8/32 atau OAK stop kelompok; TERKONTROL bila terdeteksi
 * tanpa masalah; selain itu TANPA LAPORAN. Gap = bermasalah + tanpa laporan.
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

    /** Urutan angka per level di tabel perbandingan: terkontrol, bermasalah, tanpa laporan, rincian bermasalah. */
    public const KOLOM_MGMT = ['ok', 'bm', 'none', 'closed', 'ulang', 'stop', 'tidak_sesuai'];

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
        $masalah = ($s & (self::BELUM_CLOSED | self::BERULANG | self::TIDAK_SESUAI)) | ($k & self::OAK_STOP);
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
     * Ringkasan satu PJA untuk tabel "Perbandingan antar PJA" (tanpa flag): per minggu per level risiko
     * angka [ok, bermasalah, tanpa laporan, belum closed, berulang, OAK stop, tidak sesuai].
     *
     * @param  array<string, mixed>  $berkasBeranda  hasil berkas()
     * @param  list<array<string, mixed>>  $insiden  insiden di detail lokasi PJA (tanggal Y-m-d)
     * @return array<string, mixed>
     */
    public function ringkasMgmt(array $dlIds, array $hidPerDl, array $hira, array $berkasBeranda, array $insiden): array
    {
        $nW = (int) $this->cfg['beranda']['minggu'];
        $kd = (array) $berkasBeranda['kd'];
        $sp = (array) $berkasBeranda['sp'];
        $umum = array_map(fn ($l) => array_flip($l), (array) $berkasBeranda['umum']);
        $kosong = array_fill(0, count(self::KOLOM_MGMT), 0);
        $w = array_fill(0, $nW, []);
        $kwCache = [];

        foreach ($dlIds as $d) {
            foreach ($hidPerDl[$d] ?? [] as $hid) {
                $h = $hira[$hid] ?? null;
                if ($h === null) {
                    continue;
                }
                $kel = (string) ($h['proses'] ?? 'X');
                $grp = (string) ($this->grupDl[$d] ?? '');
                if (isset($umum[$grp][$hid])) {
                    continue;
                }
                $lv = (string) $h['n1'];
                $kk = $d.'|'.$kel;
                $kw = $kwCache[$kk] ??= self::urai((string) ($kd[$kk][0] ?? ''));
                $sw = self::urai((string) ($sp[$d.'|'.$hid][0] ?? ''));
                for ($i = 0; $i < $nW; $i++) {
                    $w[$i][$lv] ??= $kosong;
                    [$st, $m] = self::status($kw[$i] ?? 0, $sw[$i] ?? 0);
                    $w[$i][$lv][$st === 'ok' ? 0 : ($st === 'bm' ? 1 : 2)]++;
                    if ($st === 'bm') {
                        $w[$i][$lv][3] += (int) (bool) ($m & self::BELUM_CLOSED);
                        $w[$i][$lv][4] += (int) (bool) ($m & self::BERULANG);
                        $w[$i][$lv][5] += (int) (bool) ($m & self::OAK_STOP);
                        $w[$i][$lv][6] += (int) (bool) ($m & self::TIDAK_SESUAI);
                    }
                }
            }
        }

        $batasLama = $this->sampai->subDays((int) $this->cfg['beranda']['lokasi_lama_hari'] - 1)->toDateString();
        $hari0 = $this->hari0();
        $dlast = (array) $berkasBeranda['dlast'];

        return [
            'w' => $w,
            'lama' => count(array_filter($dlIds, fn ($d) => ($dlast[$d] ?? '') === '' || $dlast[$d] < $batasLama)),
            'insiden' => count(array_filter($insiden, fn ($i) => (string) $i['tanggal'] >= $hari0 && (string) $i['tanggal'] <= $this->sampai->toDateString())),
        ];
    }

}
