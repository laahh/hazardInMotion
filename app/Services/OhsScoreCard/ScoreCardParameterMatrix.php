<?php

declare(strict_types=1);

namespace App\Services\OhsScoreCard;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun matriks "Score Card Parameter": parameter (baris) x site/kontraktor
 * (kolom), dari tabel ringkasan asli tiap parameter.
 *
 * TIGA HAL YANG MEMBUAT INI TIDAK SESEDERHANA SATU QUERY:
 *
 * 1. NAMA BULAN BERBEDA-BEDA ANTAR TABEL. Ada yang menyimpan "M09", ada
 *    "September", ada "September 2026", ada "Sep 2026". Karena itu bulan tidak
 *    disaring di SQL melainkan dinormalkan di PHP setelah data ditarik; tabel
 *    ringkasannya kecil (puluhan sampai ratusan baris), jadi ini murah dan
 *    jauh lebih tahan banting daripada menebak format per tabel.
 *
 * 2. NAMA KONTRAKTOR JUGA BERBEDA-BEDA: "PT Bukit Makmur Mandiri Utama",
 *    "BUMA", dan "PT BUMA" adalah perusahaan yang sama. Semuanya dipetakan
 *    lewat ScoreCardParameterRegistry::ALIAS_KONTRAKTOR.
 *
 * 3. TABEL YANG TIDAK ADA TIDAK BOLEH MENJATUHKAN HALAMAN. Setiap sumber
 *    diperiksa dulu keberadaannya; yang hilang diperlakukan sama seperti
 *    parameter tanpa sumber, yaitu sel kosong bertanda.
 */
final class ScoreCardParameterMatrix
{
    private const NAMA_BULAN = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    private const LABEL_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Berapa lama hasil tarikan satu tabel disimpan. Yang di-cache adalah
     * agregat MENTAH PER BULAN, bukan matriks jadi, supaya satu pemanasan
     * melayani "semua bulan" sekaligus kedua belas bulannya.
     */
    private const TTL_DETIK = 600;

    /** Cache per-permintaan: satu tabel tidak ditarik dua kali. */
    private array $cache = [];

    /** Peta tabel => kolom, diambil sekali untuk seluruh skema. */
    private ?array $skema = null;

    /**
     * @param  int|null  $bulan  1-12, atau null untuk seluruh bulan.
     * @return array<string, mixed>
     */
    public function bangun(?int $bulan = null): array
    {
        $parameter = ScoreCardParameterRegistry::parameter();
        $kolom = ScoreCardParameterRegistry::KOLOM;

        $matriks = [];
        $bulanAda = [];
        $tanpaSumber = [];

        foreach ($parameter as $p) {
            $nama = $p['nama'];

            if ($p['sumber'] === null) {
                $matriks[$nama] = $this->selKosong($kolom);
                $tanpaSumber[] = $nama;
                continue;
            }

            $perBulan = $this->ambil($p);

            if ($perBulan === null) {
                $matriks[$nama] = $this->selKosong($kolom);
                $tanpaSumber[] = $nama;
                continue;
            }

            // Sebuah bulan hanya ditawarkan di penyaring kalau benar-benar
            // menghasilkan angka. Bulan yang barisnya ada tetapi nilainya
            // NULL semua -- misalnya bulan yang belum berjalan -- akan
            // menyajikan tabel kosong, dan itu membingungkan.
            foreach ($perBulan as $kunci => $agregat) {
                if ($p['ringkas'] === ScoreCardParameterRegistry::RINGKAS_RATA
                    && ($agregat['baris'] ?? 0) <= 0) {
                    continue;
                }

                [, , $b] = explode('|', (string) $kunci);
                $bulanAda[(int) $b] = true;
            }

            $matriks[$nama] = $this->selParameter($p, $perBulan, $kolom, $bulan);
        }

        ksort($bulanAda);

        return [
            'kolom' => $kolom,
            'jumlah_kolom' => array_sum(array_map('count', $kolom)),
            'parameter' => array_map(static fn (array $p): array => [
                'nama' => $p['nama'],
                'satuan' => $p['satuan'],
                'ada_sumber' => $p['sumber'] !== null,
                'ada_band' => $p['band'] !== null,
            ], $parameter),
            'matriks' => $matriks,
            'skor_kolom' => $this->skorKolom($matriks, $kolom),
            'bulan_tersedia' => array_map(
                static fn (int $n): array => ['nomor' => $n, 'label' => self::LABEL_BULAN[$n]],
                array_keys($bulanAda)
            ),
            'bulan_terpilih' => $bulan,
            'label_bulan' => $bulan === null ? 'Semua bulan' : self::LABEL_BULAN[$bulan],
            'tanpa_sumber' => $tanpaSumber,
        ];
    }

    /**
     * Menarik satu tabel sumber, dikelompokkan per site|kontraktor|bulan.
     *
     * @return array<string, array{jumlah: float, baris: int}>|null
     */
    private function ambil(array $p): ?array
    {
        // Kuncinya memuat SELURUH kolom, bukan hanya tabel dan kolom nilai:
        // dua parameter yang kebetulan berbagi tabel dengan kolom site atau
        // kontraktor berbeda tidak boleh saling memakai hasil cache.
        $kunciCache = implode('|', [
            $p['sumber'], $p['khusus'] ?? '', $p['site'] ?? '',
            $p['mitra'] ?? '', $p['bulan'] ?? '', $p['nilai'] ?? '',
        ]);

        if (array_key_exists($kunciCache, $this->cache)) {
            return $this->cache[$kunciCache];
        }

        // Database-nya jauh, dan tiap parameter berarti satu perjalanan
        // pulang-pergi. Tanpa cache, satu kali muat halaman menghabiskan
        // belasan detik hanya untuk menunggu jaringan.
        $hasil = Cache::remember(
            'osc-sc:sumber:' . md5($kunciCache),
            self::TTL_DETIK,
            fn (): ?array => ($p['khusus'] ?? null) === 'road_summary'
                ? $this->ambilRoadSummary()
                : $this->ambilRingkasan($p)
        );

        return $this->cache[$kunciCache] = $hasil;
    }

    /** @return array<string, array{jumlah: float, baris: int}>|null */
    private function ambilRingkasan(array $p): ?array
    {
        $kolomAda = $this->skema()[$p['sumber']] ?? null;

        if ($kolomAda === null) {
            return null;
        }

        foreach ([$p['site'], $p['mitra'], $p['bulan'], $p['nilai']] as $k) {
            if (!isset($kolomAda[$k])) {
                return null;
            }
        }

        $rows = DB::table($p['sumber'])
            ->selectRaw(
                $this->kutip($p['site']) . ' AS site, '
                . $this->kutip($p['mitra']) . ' AS mitra, '
                . $this->kutip($p['bulan']) . ' AS bulan, '
                . 'SUM(' . $this->kutip($p['nilai']) . ') AS jumlah, '
                . 'COUNT(' . $this->kutip($p['nilai']) . ') AS baris'
            )
            ->groupBy('site', 'mitra', 'bulan')
            ->get();

        return $this->kelompokkan($rows);
    }

    /**
     * road_summary berbasis MINGGU ISO dan tidak punya kolom persentase: yang
     * ada hanya hasil cek per segmen. Persentasenya dihitung di sini, dan
     * bulannya diambil dari hari Kamis minggu itu -- aturan yang sama dengan
     * halaman Jalan sesuai standar.
     *
     * @return array<string, array{jumlah: float, baris: int}>|null
     */
    private function ambilRoadSummary(): ?array
    {
        if (!isset($this->skema()['road_summary'])) {
            return null;
        }

        $standar = "(grade_stat = 'ACCEPT' AND road_width = 'ACCEPT' AND supereleva = 'ACCEPT'"
            . " AND (junction_1 IS NULL OR junction_1 IN ('-', '', 'ACCEPT'))"
            . " AND (junction_s IS NULL OR junction_s IN ('-', '', 'ACCEPT')))";

        $rows = DB::table('road_summary')
            ->selectRaw(
                'site, mitra, year, week, '
                . 'SUM(' . $standar . ') AS standar, COUNT(*) AS total'
            )
            ->groupBy('site', 'mitra', 'year', 'week')
            ->get();

        $out = [];

        foreach ($rows as $r) {
            $tahun = (int) $r->year;
            $minggu = (int) $r->week;

            if ($tahun <= 0 || $minggu < 1 || $minggu > 53) {
                continue;
            }

            // Bulan pemilik sebuah minggu ISO: bulan tempat hari Kamis-nya jatuh.
            $bulan = (int) (new \DateTimeImmutable())->setISODate($tahun, $minggu, 4)->format('n');
            $kunci = $this->kunciSel((string) $r->site, (string) $r->mitra, $bulan);

            if ($kunci === null) {
                continue;
            }

            $out[$kunci]['jumlah'] = ($out[$kunci]['jumlah'] ?? 0.0) + (float) $r->standar;
            $out[$kunci]['baris'] = ($out[$kunci]['baris'] ?? 0) + (int) $r->total;
        }

        // Disimpan sebagai persentase per bulan supaya bentuknya sama dengan
        // sumber lain: 'jumlah' menjadi total persen, 'baris' pembaginya.
        foreach ($out as $k => $v) {
            $out[$k] = [
                'jumlah' => $v['baris'] > 0 ? $v['jumlah'] / $v['baris'] * 100.0 : 0.0,
                'baris' => 1,
            ];
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<string, array{jumlah: float, baris: int}>
     */
    private function kelompokkan($rows): array
    {
        $out = [];

        foreach ($rows as $r) {
            $bulan = $this->nomorBulan((string) $r->bulan);

            if ($bulan === null) {
                continue; // format bulan tak dikenal: jangan diam-diam dianggap bulan lain
            }

            $kunci = $this->kunciSel((string) $r->site, (string) $r->mitra, $bulan);

            if ($kunci === null) {
                continue;
            }

            $out[$kunci]['jumlah'] = ($out[$kunci]['jumlah'] ?? 0.0) + (float) $r->jumlah;
            $out[$kunci]['baris'] = ($out[$kunci]['baris'] ?? 0) + (int) $r->baris;
        }

        return $out;
    }

    /** Kunci "site|kontraktor|bulan", atau null kalau kolomnya tidak ditampilkan. */
    private function kunciSel(string $site, string $mitra, int $bulan): ?string
    {
        $site = trim($site);
        $label = $this->labelKontraktor($mitra);

        if ($label === null || !isset(ScoreCardParameterRegistry::KOLOM[$site])) {
            return null;
        }

        if (!in_array($label, ScoreCardParameterRegistry::KOLOM[$site], true)) {
            return null;
        }

        return $site . '|' . $label . '|' . $bulan;
    }

    private function labelKontraktor(string $mentah): ?string
    {
        return ScoreCardParameterRegistry::ALIAS_KONTRAKTOR[$this->kunciKontraktor($mentah)] ?? null;
    }

    /** Membuang awalan "PT"/"CV", merapikan spasi, dan menyeragamkan huruf. */
    private function kunciKontraktor(string $mentah): string
    {
        $s = mb_strtolower(trim($mentah));
        $s = (string) preg_replace('/^(pt|cv)[\s.]+/u', '', $s);
        $s = (string) preg_replace('/\s+/u', ' ', $s);

        return trim($s);
    }

    /** @return int|null 1-12 */
    private function nomorBulan(string $mentah): ?int
    {
        $s = trim($mentah);

        if ($s === '') {
            return null;
        }

        if (preg_match('/^M(\d{1,2})$/i', $s, $m) === 1) {
            $n = (int) $m[1];

            return $n >= 1 && $n <= 12 ? $n : null;
        }

        if (preg_match('/^\d{1,2}$/', $s) === 1) {
            $n = (int) $s;

            return $n >= 1 && $n <= 12 ? $n : null;
        }

        // Nama lengkap diperiksa lebih dulu: "June" dan "July" sama-sama
        // berawalan "Ju", jadi mencocokkan tiga huruf duluan bisa salah.
        foreach (self::NAMA_BULAN as $n => $nama) {
            if (stripos($s, $nama) === 0) {
                return $n;
            }
        }

        foreach (self::NAMA_BULAN as $n => $nama) {
            if (stripos($s, substr($nama, 0, 3)) === 0) {
                return $n;
            }
        }

        foreach (self::LABEL_BULAN as $n => $nama) {
            if (stripos($s, $nama) === 0) {
                return $n;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{jumlah: float, baris: int}>  $perBulan
     * @param  array<string, array<int, string>>  $kolom
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function selParameter(array $p, array $perBulan, array $kolom, ?int $bulan): array
    {
        $out = [];

        foreach ($kolom as $site => $kontraktor) {
            foreach ($kontraktor as $k) {
                $nilai = $this->ringkas($p, $perBulan, $site, $k, $bulan);

                $out[$site][$k] = $nilai === null
                    ? ['ada' => false, 'capaian' => null, 'nilai' => null, 'band' => null, 'teks' => '–']
                    : $this->beriNilai($p, $nilai);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, array{jumlah: float, baris: int}>  $perBulan
     */
    private function ringkas(array $p, array $perBulan, string $site, string $kontraktor, ?int $bulan): ?float
    {
        $bulanDipakai = $bulan === null ? range(1, 12) : [$bulan];

        $jumlah = 0.0;
        $baris = 0;
        $ada = false;

        foreach ($bulanDipakai as $b) {
            $kunci = $site . '|' . $kontraktor . '|' . $b;

            if (!isset($perBulan[$kunci])) {
                continue;
            }

            $ada = true;
            $jumlah += $perBulan[$kunci]['jumlah'];
            $baris += $perBulan[$kunci]['baris'];
        }

        if (!$ada) {
            return null;
        }

        if ($p['ringkas'] === ScoreCardParameterRegistry::RINGKAS_JUMLAH) {
            return $jumlah;
        }

        return $baris > 0 ? $jumlah / $baris : null;
    }

    /** @return array<string, mixed> */
    private function beriNilai(array $p, float $capaian): array
    {
        [$nilai, $label] = $this->nilaiBand($p, $capaian);

        return [
            'ada' => true,
            'capaian' => round($capaian, 2),
            'nilai' => $nilai,
            'band' => $nilai === null ? null : (int) floor($nilai),
            'band_label' => $label,
            'teks' => $p['satuan'] === '%'
                ? number_format($capaian, 2, ',', '.') . '%'
                : number_format($capaian, 0, ',', '.'),
        ];
    }

    /**
     * Band dan nilainya. Ambangnya sama persis dengan SCORE_BANDS di controller
     * masing-masing halaman.
     *
     * @return array{0: float|null, 1: string|null}
     */
    private function nilaiBand(array $p, float $x): array
    {
        return match ($p['band']) {
            ScoreCardParameterRegistry::BAND_NAIK => $this->bandNaik($p['ambang'], $x),
            ScoreCardParameterRegistry::BAND_TURUN => $this->bandTurun($p['ambang'], $x),
            ScoreCardParameterRegistry::BAND_CACAH => $this->bandCacah($x),
            ScoreCardParameterRegistry::BAND_BINER => $x >= 100.0
                ? [4.0, '100% - tidak ada yang lewat']
                : [1.0, '<100% - ada yang lewat'],
            default => [null, null],
        };
    }

    /**
     * Makin besar makin baik. Nilainya melandai di dalam band, dan band
     * teratas datar di 4,00.
     *
     * @param  array<int, int|float>  $ambang  [band2, band3, band4, batas atas]
     * @return array{0: float, 1: string}
     */
    private function bandNaik(array $ambang, float $x): array
    {
        [$a1, $a2, $a3, $atas] = array_map('floatval', $ambang);

        $band = [
            [$a3, $atas, 4, $this->label($a3, $atas, true)],
            [$a2, $a3, 3, $this->label($a2, $a3, false)],
            [$a1, $a2, 2, $this->label($a1, $a2, false)],
            [0.0, $a1, 1, '<' . $this->angka($a1) . '%'],
        ];

        foreach ($band as [$bawah, $atasBand, $dasar, $label]) {
            if ($x < $bawah) {
                continue;
            }

            if ($dasar >= 4) {
                return [4.0, $label];
            }

            $rentang = $atasBand - $bawah;
            $nilai = $rentang > 0 ? $dasar + ($x - $bawah) / $rentang : (float) $dasar;

            // Tidak boleh menyentuh angka band berikutnya, supaya angka dan
            // label band di layar tidak pernah bertentangan.
            return [round(max(1.0, min(4.0, min($nilai, $dasar + 0.99))), 2), $label];
        }

        return [1.0, '<' . $this->angka($a1) . '%'];
    }

    /**
     * Makin kecil makin baik (blindspot). Jaraknya dihitung dari batas ATAS
     * band, sisi yang lebih buruk.
     *
     * @param  array<int, int|float>  $ambang  [band4, band3, band2]
     * @return array{0: float, 1: string}
     */
    private function bandTurun(array $ambang, float $x): array
    {
        [$b4, $b3, $b2] = array_map('floatval', $ambang);

        $band = [
            [0.0, $b4, 4, '0% - ' . $this->angka($b4) . '%'],
            [$b4, $b3, 3, '>' . $this->angka($b4) . '% - ' . $this->angka($b3) . '%'],
            [$b3, $b2, 2, '>' . $this->angka($b3) . '% - ' . $this->angka($b2) . '%'],
        ];

        foreach ($band as [$bawah, $atas, $dasar, $label]) {
            if ($x > $atas) {
                continue;
            }

            $rentang = $atas - $bawah;
            $nilai = $rentang > 0 ? $dasar + ($atas - $x) / $rentang : (float) $dasar;

            return [round(max(1.0, min(4.0, min($nilai, $dasar + 0.99))), 2), $label];
        }

        return [1.0, '>' . $this->angka($b2) . '%'];
    }

    /** @return array{0: float, 1: string} */
    private function bandCacah(float $x): array
    {
        if ($x <= 0) {
            return [4.0, 'tidak ada temuan'];
        }
        if ($x <= 3) {
            return [3.0, '1 - 3 temuan'];
        }
        if ($x <= 5) {
            return [2.0, '4 - 5 temuan'];
        }

        return [1.0, 'lebih dari 5 temuan'];
    }

    private function label(float $bawah, float $atas, bool $teratas): string
    {
        if ($teratas) {
            return $bawah >= $atas
                ? $this->angka($bawah) . '%'
                : $this->angka($bawah) . '% - ' . $this->angka($atas) . '%';
        }

        return $this->angka($bawah) . '% - <' . $this->angka($atas) . '%';
    }

    private function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    private function selKosong(array $kolom): array
    {
        $out = [];

        foreach ($kolom as $site => $kontraktor) {
            foreach ($kontraktor as $k) {
                $out[$site][$k] = [
                    'ada' => false, 'capaian' => null, 'nilai' => null,
                    'band' => null, 'teks' => '–',
                ];
            }
        }

        return $out;
    }

    /**
     * Baris Score: RATA-RATA NILAI, bukan rata-rata persen. Persen antar
     * parameter tidak sebanding -- 0% pada Blindspot adalah hasil terbaik
     * sedangkan 0% pada Ratio adalah terburuk -- jadi merata-ratakannya akan
     * menyesatkan. Parameter tanpa nilai tidak ikut membagi.
     *
     * @return array<string, array<string, mixed>>
     */
    private function skorKolom(array $matriks, array $kolom): array
    {
        $out = [];

        foreach ($kolom as $site => $kontraktor) {
            foreach ($kontraktor as $k) {
                $total = 0.0;
                $n = 0;

                foreach ($matriks as $sel) {
                    $nilai = $sel[$site][$k]['nilai'] ?? null;

                    if ($nilai !== null) {
                        $total += (float) $nilai;
                        $n++;
                    }
                }

                $out[$site . '|' . $k] = [
                    'nilai' => $n > 0 ? round($total / $n, 2) : null,
                    'terisi' => $n,
                ];
            }
        }

        return $out;
    }

    /**
     * Seluruh tabel beserta kolomnya dalam SATU query.
     *
     * Schema::hasTable() dan getColumnListing() masing-masing satu perjalanan
     * ke database; dipanggil per parameter, itu saja sudah lebih dari empat
     * puluh perjalanan sebelum satu baris data pun terbaca.
     *
     * @return array<string, array<string, true>>
     */
    private function skema(): array
    {
        if ($this->skema !== null) {
            return $this->skema;
        }

        return $this->skema = Cache::remember('osc-sc:skema', self::TTL_DETIK, static function (): array {
            $rows = DB::select(
                'SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.columns'
                . ' WHERE TABLE_SCHEMA = DATABASE()'
            );

            $peta = [];

            foreach ($rows as $r) {
                $peta[$r->t][$r->c] = true;
            }

            return $peta;
        });
    }

    private function kutip(string $kolom): string
    {
        return '`' . str_replace('`', '', $kolom) . '`';
    }
}
