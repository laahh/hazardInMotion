<?php

declare(strict_types=1);

namespace App\Services\PetaRisiko\Drm;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Rapor PJA untuk halaman publik /form/rapor-pja: cari SID → PJA tempat SID itu ditugaskan → performa,
 * ringkasan risiko (terkontrol / belum), temuan SAP open, dan baris baseline yang belum ditemukan.
 *
 * Semua angka dibaca dari snapshot `peta-risiko:sync` (storage/app/peta-risiko-drm) dan dihitung dengan
 * DrmBeranda::ringkasMgmt yang sama dengan tabel "Perbandingan antar PJA" (satuan ketat, tanpa flag).
 */
final class DrmRaporPja
{
    /** Level yang dihitung di ringkasan utama (sama dengan filter bawaan Beranda PJA). */
    public const LEVEL_UTAMA = ['Significant', 'High'];

    public const LEVEL = ['Significant', 'High', 'Medium', 'Low'];

    private const TEMUAN_MAKS = 50;

    private const BELUM_MAKS = 150;

    private const TREN_MINGGU = 8;

    /** Indeks kolom berkas['bahaya']: [hid, a, an, sn, bn, p, n1, k1, c1, n2, k2, c2, kt, faktor, proses]. */
    private const B_HID = 0, B_AN = 2, B_SN = 3, B_BN = 4, B_N1 = 6, B_K1 = 7, B_C1 = 8, B_N2 = 9, B_K2 = 10, B_C2 = 11, B_PROSES = 14;

    public function __construct(private readonly DrmRepository $repo)
    {
    }

    /** SID dinormalkan: huruf besar, tanpa spasi. Null bila format tidak wajar. */
    public static function normSid(string $sid): ?string
    {
        $s = strtoupper(trim($sid));

        return preg_match('/^[A-Z0-9]{3,20}$/', $s) ? $s : null;
    }

    /**
     * Penugasan SID di snapshot: data orang + daftar PJA (berkas BC) yang memuat SID itu.
     *
     * @return array{orang: array<string, mixed>, pja: list<array{id: string, nama: string, site: string}>}|null
     */
    public function cari(string $sid): ?array
    {
        $idx = $this->indeks();

        return $idx['sid'][$sid] ?? null;
    }

    /** Tanggal akhir data snapshot (Y-m-d) atau null bila snapshot belum ada. */
    public function sampai(): ?string
    {
        return $this->indeks()['sampai'] ?? null;
    }

    /**
     * Rapor satu PJA untuk satu SID. Null bila berkas PJA tidak ada.
     *
     * @return array<string, mixed>|null
     */
    public function rapor(string $pjaId, string $sid): ?array
    {
        $sampai = $this->sampai();
        $berkas = $this->repo->pja($pjaId);
        if ($sampai === null || $berkas === null) {
            return null;
        }
        $cfg = (array) config('peta_risiko_drm');
        $tgl = CarbonImmutable::parse($sampai);

        $hira = $this->hira($berkas['bahaya'] ?? []);
        $grupDl = [];
        foreach ($berkas['lokasi'] ?? [] as $l) {
            $grupDl[(string) $l['dl']] = ['grp' => (string) ($l['grp'] ?? '')];
        }
        $dlIds = array_keys($grupDl);
        $hidPerDl = [];
        foreach ((array) ($berkas['pasangan'] ?? []) as $dl => $isi) {
            $hidPerDl[(string) $dl] = $isi === '' ? [] : array_map(fn ($p) => (string) strstr($p.':', ':', true), explode(',', (string) $isi));
        }
        $insidenPasangan = [];
        foreach ($berkas['sorot'] ?? [] as $r) {
            if ((int) ($r['n_inc'] ?? 0) > 0) {
                $insidenPasangan[$r['dl'].'|'.$r['hid']] = (int) $r['n_inc'];
            }
        }

        $b = (new DrmBeranda($cfg, $tgl))->denganGrup($grupDl)->denganInsiden($insidenPasangan);
        $minggu = $b->minggu();
        $r = $b->ringkasMgmt($dlIds, $hidPerDl, $hira, (array) ($berkas['beranda'] ?? []), $berkas['insiden'] ?? [], false, true);
        $w = self::mingguDefault($minggu, $sampai);

        return [
            'pja' => [
                'id' => $pjaId,
                'nama' => (string) ($berkas['info']['nama'] ?? ''),
                'site' => (string) ($berkas['info']['site'] ?? ''),
                'mitra' => array_values(array_map(fn ($m) => (string) $m['nama'], $berkas['info']['mitra'] ?? [])),
                'dl' => count($dlIds),
                'personil' => count($berkas['personil'] ?? []),
            ],
            'orang' => $this->orang($berkas, $sid),
            'periode' => ['minggu' => $minggu[$w], 'sampai' => $sampai, 'berjalan' => $w === 0],
            'ringkas' => $this->ringkasLevel($r['w'][$w] ?? []),
            'tren' => $this->tren($r['w'], $minggu, $w),
            'belum' => $this->barisStatus($r['rincian']['u'] ?? [], $hira, $w, 'T'),
            'gap' => $this->barisStatus($r['rincian']['u'] ?? [], $hira, $w, 'G'),
            'temuan' => $this->temuan($berkas, $sid),
            'coverage' => $this->coverage($berkas, (string) $minggu[$w], (int) ($cfg['jendela_hari'] ?? 28)),
            'personilPja' => $this->personilPja($berkas, $sid),
            'insiden' => count(array_filter($berkas['insiden'] ?? [], fn ($i) => (string) $i['tanggal'] >= $tgl->subYear()->toDateString())),
        ];
    }

    /** Minggu bawaan = minggu penuh terakhir (sama dengan DR.wDefault di Beranda). */
    public static function mingguDefault(array $minggu, string $sampai): int
    {
        if (count($minggu) < 2) {
            return 0;
        }
        $penuh = CarbonImmutable::parse($minggu[0])->addDays(6)->toDateString() <= $sampai;

        return $penuh ? 0 : 1;
    }

    // ------------------------------------------------------------ bagian rapor

    /** @return array<string, array<string, mixed>> hid => atribut yang dipakai DrmBeranda */
    private function hira(array $bahaya): array
    {
        $hasil = [];
        foreach ($bahaya as $x) {
            $hasil[(string) $x[self::B_HID]] = [
                'a' => (string) ($x[1] ?? ''), 'an' => (string) ($x[self::B_AN] ?? ''), 'sn' => (string) ($x[self::B_SN] ?? ''),
                'bn' => (string) ($x[self::B_BN] ?? ''), 'n1' => (string) ($x[self::B_N1] ?? ''),
                'k1' => (string) ($x[self::B_K1] ?? ''), 'c1' => (string) ($x[self::B_C1] ?? ''),
                'n2' => (string) ($x[self::B_N2] ?? ''), 'k2' => (string) ($x[self::B_K2] ?? ''), 'c2' => (string) ($x[self::B_C2] ?? ''),
                'proses' => (string) ($x[self::B_PROSES] ?? 'X'),
            ];
        }

        return $hasil;
    }

    /**
     * Angka per level + gabungan Significant & High.
     *
     * @param  array<string, list<int>>  $perLevel  level => vektor DrmBeranda::KOLOM_MGMT
     * @return array<string, array<string, int>>
     */
    private function ringkasLevel(array $perLevel): array
    {
        $kolom = array_flip(DrmBeranda::KOLOM_MGMT);
        $ambil = fn (array $v) => [
            'tot' => (int) ($v[$kolom['tot']] ?? 0), 'full' => (int) ($v[$kolom['full']] ?? 0), 'part' => (int) ($v[$kolom['part']] ?? 0),
            'gaponly' => (int) ($v[$kolom['gaponly']] ?? 0), 'none' => (int) ($v[$kolom['none']] ?? 0),
            'closed' => (int) ($v[$kolom['closed']] ?? 0), 'ulang' => (int) ($v[$kolom['ulang']] ?? 0), 'stop' => (int) ($v[$kolom['stop']] ?? 0),
            // Status kartu KPI Beranda (gabungan antar detail lokasi, G > K > T).
            'idk' => (int) ($v[$kolom['idk']] ?? 0), 'idg' => (int) ($v[$kolom['idg']] ?? 0), 'tid' => (int) ($v[$kolom['tid']] ?? 0),
            'turun' => (int) ($v[$kolom['turun']] ?? 0), 'laten' => (int) ($v[$kolom['laten']] ?? 0),
        ];
        $hasil = ['utama' => array_fill_keys(['tot', 'full', 'part', 'gaponly', 'none', 'closed', 'ulang', 'stop', 'idk', 'idg', 'tid', 'turun', 'laten'], 0)];
        foreach (self::LEVEL as $lv) {
            $hasil[$lv] = $ambil($perLevel[$lv] ?? []);
            if (in_array($lv, self::LEVEL_UTAMA, true)) {
                foreach ($hasil[$lv] as $k => $n) {
                    $hasil['utama'][$k] += $n;
                }
            }
        }

        return $hasil;
    }

    /**
     * % terkontrol penuh (Significant + High) beberapa minggu terakhir s.d. minggu terpilih, lama → baru.
     *
     * @return list<array{minggu: string, tot: int, full: int}>
     */
    private function tren(array $w, array $minggu, int $mulai): array
    {
        $hasil = [];
        for ($i = min(count($minggu) - 1, $mulai + self::TREN_MINGGU - 1); $i >= $mulai; $i--) {
            $r = $this->ringkasLevel($w[$i] ?? [])['utama'];
            $hasil[] = ['minggu' => $minggu[$i], 'tot' => $r['tot'], 'full' => $r['full'], 'idk' => $r['idk']];
        }

        return $hasil;
    }

    /**
     * Baris baseline Significant/High dengan kode status tertentu pada minggu w
     * (T = tanpa laporan / belum ditemukan, G = ada laporan bermasalah), dikelompokkan per aktivitas.
     *
     * @param  list<list<int|string>>  $u  rincian u: [kel, hid, level, inc, tr, kode, gap, vol]
     * @return array{total: int, aktivitas: list<array{nama: string, baris: list<array<string, mixed>>}>}
     */
    private function barisStatus(array $u, array $hira, int $w, string $kode): array
    {
        $urut = array_flip(self::LEVEL);
        $pilih = [];
        foreach ($u as $x) {
            [$hid, $lv, $inc, $k, $gap] = [(string) $x[1], (string) $x[2], (int) $x[3], (string) $x[5], (string) $x[6]];
            if (! in_array($lv, self::LEVEL_UTAMA, true) || ($k[$w] ?? '') !== $kode) {
                continue;
            }
            $h = $hira[$hid] ?? [];
            $bit = hexdec($gap[$w] ?? '0');
            $pilih[] = [
                'aktivitas' => $h['an'] ?? '', 'sub' => $h['sn'] ?? '', 'bahaya' => $h['bn'] ?? '', 'level' => $lv, 'insiden' => $inc,
                'masalah' => array_values(array_filter([
                    $bit & 1 ? 'belum closed' : null, $bit & 2 ? 'berulang' : null, $bit & 4 ? 'OAK Stop' : null, $bit & 8 ? 'butir OAK tidak sesuai' : null,
                ])),
            ];
        }
        usort($pilih, fn ($a, $b) => ($urut[$a['level']] <=> $urut[$b['level']]) ?: ($b['insiden'] <=> $a['insiden'])
            ?: strcmp($a['aktivitas'], $b['aktivitas']) ?: strcmp($a['bahaya'], $b['bahaya']));

        $perAkt = [];
        foreach (array_slice($pilih, 0, self::BELUM_MAKS) as $p) {
            $perAkt[$p['aktivitas']][] = array_diff_key($p, ['aktivitas' => 1]);
        }

        return [
            'total' => count($pilih),
            'aktivitas' => array_map(fn ($nama, $baris) => ['nama' => (string) $nama, 'baris' => $baris], array_keys($perAkt), $perAkt),
        ];
    }

    /** @return array<string, mixed> data SID di PJA ini (nama, perusahaan, SAP 14 hari, BeARC terakhir) */
    private function orang(array $berkas, string $sid): array
    {
        $baris = array_values(array_filter($berkas['personil'] ?? [], fn ($p) => strtoupper((string) $p['sid']) === $sid));
        $p = $baris[0] ?? [];
        $harian = array_map('intval', (array) ($p['harian'] ?? []));
        $bearc = null;
        foreach ($berkas['bearc'] ?? [] as $x) {
            if (strtoupper((string) $x['sid']) === $sid && ($bearc === null || (string) $x['mulai'] > (string) $bearc['mulai'])) {
                $bearc = $x;
            }
        }

        return [
            'sid' => $sid,
            'nama' => (string) ($p['nama'] ?? ''),
            'perusahaan' => (string) ($p['perusahaan'] ?? ''),
            'pja' => (string) ($p['pja'] ?? ''),
            'kategori' => implode(', ', array_values(array_unique(array_filter(array_map(fn ($x) => trim((string) ($x['kategori'] ?? '')), $baris))))),
            'harian' => $harian,
            'totalSap' => array_sum($harian),
            'bearc' => $bearc === null ? null : [
                'persen' => $bearc['persen'] === null ? null : (float) $bearc['persen'],
                'banned' => (bool) (int) ($bearc['banned_sap'] ?? 0),
            ],
        ];
    }

    /** @return array{total: int, daftar: list<array<string, mixed>>} temuan Hazard/Inspeksi open di detail lokasi PJA */
    private function temuan(array $berkas, string $sid): array
    {
        $detlok = [];
        foreach ($berkas['lokasi'] ?? [] as $l) {
            $detlok[(string) $l['dl']] = trim((string) $l['lokasi'].' · '.(string) $l['detlok'], ' ·');
        }
        $nama = mb_strtolower(trim($this->orang($berkas, $sid)['nama']));
        $hariIni = now()->toDateString();

        return [
            'total' => (int) ($berkas['temuanTotal'] ?? 0),
            'daftar' => array_map(fn ($t) => [
                'tanggal' => (string) $t['tanggal'], 'jenis' => (string) $t['jenis'], 'lokasi' => $detlok[(string) $t['dl']] ?? '',
                'ket' => (string) $t['ket'], 'subket' => (string) $t['subket'], 'deskripsi' => (string) $t['deskripsi'],
                'status' => (string) $t['status'], 'janji' => (string) $t['janji'],
                // Nomor tasklist SAP (snapshot sebelum 20 Nov 2026 belum memuatnya → '').
                'task' => (string) ($t['task'] ?? ''),
                'lewat' => $t['janji'] !== '' && (string) $t['janji'] < $hariIni,
                'milikSaya' => $nama !== '' && mb_strtolower(trim((string) ($t['pelapor'] ?? ''))) === $nama,
            ], array_slice($berkas['temuan'] ?? [], 0, self::TEMUAN_MAKS)),
        ];
    }

    /**
     * Coverage area per minggu kalender Minggu–Sabtu (minggu yang sama dengan Performa PJA): laporan SAP per detail
     * lokasi PJA. semua = semua pelapor di detail lokasi itu; tim = personil PJA ini + PJA Mitra anaknya.
     * Sumber: kolom `minggu_cov` coverage snapshot ("awal minggu:semua:tim,…"). Snapshot lama tanpa `minggu_cov` → jendela hari
     * (semua / dedicated, sama dengan Beranda PJA poin c), ditandai 'perMinggu' => false.
     *
     * @return array<string, mixed>
     */
    private function coverage(array $berkas, string $awalMinggu, int $jendela): array
    {
        $baris = $berkas['coverage'] ?? [];
        $perMinggu = $baris !== [] && array_key_exists('minggu_cov', $baris[0]);
        $cov = [];
        foreach ($baris as $c) {
            if (! $perMinggu) {
                $cov[(string) $c['dl']] = ['semua' => (int) ($c['semua'] ?? 0), 'tim' => (int) ($c['dedicated'] ?? 0)];

                continue;
            }
            foreach (array_filter(explode(',', (string) $c['minggu_cov'])) as $m) {
                [$awal, $semua, $tim] = array_pad(explode(':', $m), 3, '0');
                if ($awal === $awalMinggu) {
                    $cov[(string) $c['dl']] = ['semua' => (int) $semua, 'tim' => (int) $tim];
                }
            }
        }
        $daftar = [];
        foreach ($berkas['lokasi'] ?? [] as $l) {
            $c = $cov[(string) $l['dl']] ?? ['semua' => 0, 'tim' => 0];
            $daftar[] = ['lokasi' => (string) $l['lokasi'], 'detlok' => (string) $l['detlok'], 'grup' => (string) ($l['grp'] ?? ''), ...$c];
        }
        // Yang paling perlu perhatian dulu: tanpa laporan, lalu tanpa laporan tim PJA, lalu laporan paling sedikit.
        usort($daftar, fn ($a, $b) => [$a['semua'] > 0, $a['tim'] > 0, $a['semua']] <=> [$b['semua'] > 0, $b['tim'] > 0, $b['semua']]
            ?: strcmp($a['lokasi'].$a['detlok'], $b['lokasi'].$b['detlok']));

        return [
            'perMinggu' => $perMinggu,
            'minggu' => $awalMinggu,
            'jendela' => $jendela,
            'dl' => count($daftar),
            'dlAda' => count(array_filter($daftar, fn ($d) => $d['semua'] > 0)),
            'dlTim' => count(array_filter($daftar, fn ($d) => $d['tim'] > 0)),
            'laporan' => array_sum(array_column($daftar, 'semua')),
            'laporanTim' => array_sum(array_column($daftar, 'tim')),
            'daftar' => $daftar,
        ];
    }

    /**
     * Pencapaian SAP personil PJA (BC + Mitra anak), sama dengan Beranda PJA poin a–b: laporan SAP 14 hari
     * (Hazard, Inspeksi, OBS, OAK, Coaching) dan BeARC minggu terakhir (%SAP, target vs realisasi, banned).
     * Halaman publik: SID orang lain tidak dikirim; hanya baris milik SID pencari yang ditandai.
     *
     * @return array<string, mixed>
     */
    private function personilPja(array $berkas, string $sid): array
    {
        $bearc = [];
        foreach ($berkas['bearc'] ?? [] as $x) {
            $k = strtoupper((string) $x['sid']);
            if (! isset($bearc[$k]) || (string) $x['mulai'] > (string) $bearc[$k]['mulai']) {
                $bearc[$k] = $x;
            }
        }
        $orang = [];
        foreach ($berkas['personil'] ?? [] as $p) {
            $k = strtoupper((string) ($p['sid'] ?? ''));
            if ($k === '') {
                continue;
            }
            $orang[$k] ??= ['nama' => (string) $p['nama'], 'perusahaan' => (string) $p['perusahaan'], 'bc' => false, 'kat' => [],
                'pja' => [], 'harian' => array_map('intval', (array) ($p['harian'] ?? []))];
            $orang[$k]['bc'] = $orang[$k]['bc'] || (bool) ($p['bc'] ?? false);
            if (trim((string) ($p['kategori'] ?? '')) !== '') {
                $orang[$k]['kat'][trim((string) $p['kategori'])] = true;
            }
            if (! ($p['bc'] ?? false) && (string) ($p['pja'] ?? '') !== '') {
                $orang[$k]['pja'][(string) $p['pja']] = true;
            }
        }
        $daftar = [];
        foreach ($orang as $k => $o) {
            $h = $o['harian'];
            $b = $bearc[$k] ?? null;
            $daftar[] = [
                'saya' => $k === $sid, 'nama' => $o['nama'], 'perusahaan' => $o['perusahaan'], 'bc' => $o['bc'],
                'peran' => implode(', ', array_keys($o['kat'])), 'pjaMitra' => implode(', ', array_keys($o['pja'])),
                'harian' => $h, 'tot' => array_sum($h), 'tot7' => array_sum(array_slice($h, -7)), 'aktif' => count(array_filter($h)),
                'bearc' => $b === null ? null : [
                    'mulai' => (string) $b['mulai'], 'persen' => $b['persen'] === null ? null : (float) $b['persen'],
                    'banned' => (bool) (int) ($b['banned_sap'] ?? 0),
                    'target' => array_map('intval', ['coach' => $b['t_coach'] ?? 0, 'obs' => $b['t_obs'] ?? 0, 'ins' => $b['t_ins'] ?? 0, 'hzr' => $b['t_hzr'] ?? 0]),
                    'real' => array_map('intval', ['coach' => $b['n_coach'] ?? 0, 'obs' => $b['n_obs'] ?? 0, 'ins' => $b['n_ins'] ?? 0, 'hzr' => $b['n_hzr'] ?? 0]),
                ],
            ];
        }
        // Urutan tampil: PJA utama dulu, lalu tiap PJA child (Mitra) per nama; di dalam kelompok "Anda" lalu laporan terbanyak.
        // Personil di beberapa PJA child masuk kelompok child pertama (nama terurut); anggota PJA utama selalu di utama.
        foreach ($daftar as &$d) {
            $mitra = array_filter(array_map('trim', explode(',', $d['pjaMitra'])));
            sort($mitra);
            $d['grup'] = $d['bc'] || $mitra === [] ? '' : (string) $mitra[0];
        }
        unset($d);
        usort($daftar, fn ($a, $b) => [$a['grup'] !== '', $a['grup'], ! $a['saya'], -$a['tot']] <=> [$b['grup'] !== '', $b['grup'], ! $b['saya'], -$b['tot']]
            ?: strcmp($a['nama'], $b['nama']));
        $grup = [];
        foreach ($daftar as $d) {
            $g = &$grup[$d['grup']];
            $g ??= ['nama' => $d['grup'] === '' ? (string) ($berkas['info']['nama'] ?? 'PJA utama') : $d['grup'], 'utama' => $d['grup'] === '',
                'n' => 0, 'laporan' => 0, 'aktif7' => 0];
            $g['n']++;
            $g['laporan'] += $d['tot'];
            $g['aktif7'] += (int) ($d['tot7'] > 0);
            unset($g);
        }
        $persen = array_values(array_filter(array_map(fn ($d) => $d['bearc']['persen'] ?? null, $daftar), fn ($x) => $x !== null));

        return [
            'total' => count($daftar),
            'bc' => count(array_filter($daftar, fn ($d) => $d['bc'])),
            'aktif7' => count(array_filter($daftar, fn ($d) => $d['tot7'] > 0)),
            'tanpaLaporan' => count(array_filter($daftar, fn ($d) => $d['tot'] === 0)),
            'laporan' => array_sum(array_column($daftar, 'tot')),
            'rataPersen' => $persen === [] ? null : round(array_sum($persen) / count($persen), 1),
            'capai' => count(array_filter($persen, fn ($x) => $x >= 100)),
            'adaBearc' => count($persen),
            'banned' => count(array_filter($daftar, fn ($d) => $d['bearc']['banned'] ?? false)),
            // Kelompok berurutan (utama → child); daftar sudah terurut mengikuti kelompok ini (kunci 'grup', '' = utama).
            'grup' => array_values($grup),
            'daftar' => $daftar,
        ];
    }

    // ------------------------------------------------------------ indeks SID → PJA

    /**
     * Indeks dibangun sekali per snapshot (kunci = waktu ubah meta.json) dari daftar personil tiap berkas PJA.
     *
     * @return array{sampai?: string, sid: array<string, array<string, mixed>>}
     */
    private function indeks(): array
    {
        $meta = $this->repo->pathMeta();
        if (! is_file($meta)) {
            return ['sid' => []];
        }
        $kunci = 'drm-rapor-pja:v2:'.filemtime($meta);
        try {
            return Cache::remember($kunci, now()->addDays(2), fn () => $this->bangunIndeks($meta));
        } catch (Throwable $e) {
            // Cache tidak bisa ditulis (izin folder): tetap layani dengan membangun langsung.
            Log::warning('Rapor PJA: cache indeks gagal', ['pesan' => $e->getMessage()]);

            return $this->bangunIndeks($meta);
        }
    }

    /** @return array{sampai: ?string, sid: array<string, array<string, mixed>>} */
    private function bangunIndeks(string $pathMeta): array
    {
        // meta.json ±11 MB: cukup baca awal file untuk tanggal akhir data ("sampai" ditulis di kunci kedua).
        $awal = (string) @file_get_contents($pathMeta, false, null, 0, 2048);
        $sampai = preg_match('/"sampai"\s*:\s*"(\d{4}-\d{2}-\d{2})"/', $awal, $m) ? $m[1] : null;

        $sid = [];
        $folder = Storage::disk('local')->path('peta-risiko-drm/pja');
        foreach (glob($folder.'/*.json') ?: [] as $file) {
            $berkas = json_decode((string) file_get_contents($file), true);
            if (! is_array($berkas)) {
                continue;
            }
            $id = basename($file, '.json');
            // Termasuk PJA yang dikecualikan dari dashboard (mis. Inspektor): di rapor publik tetap muncul.
            $pja = ['id' => $id, 'nama' => (string) ($berkas['info']['nama'] ?? ''), 'site' => (string) ($berkas['info']['site'] ?? '')];
            foreach ($berkas['personil'] ?? [] as $p) {
                $s = self::normSid((string) ($p['sid'] ?? ''));
                if ($s === null) {
                    continue;
                }
                $sid[$s]['orang'] ??= ['nama' => (string) $p['nama'], 'perusahaan' => (string) $p['perusahaan']];
                $sid[$s]['pja'][$id] = $pja;
            }
            unset($berkas);
        }
        foreach ($sid as $s => $x) {
            $sid[$s]['pja'] = array_values($x['pja']);
        }

        return ['sampai' => $sampai, 'sid' => $sid];
    }
}
