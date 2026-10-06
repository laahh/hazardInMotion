<?php

declare(strict_types=1);

namespace App\Services\OhsScoreCard;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Definisi bersama parameter "Kesiapan alat Emergency".
 *
 * ADA DUA PEMAKAI YANG HARUS SEPAKAT: halaman
 * /ohs-score-card/kesiapan-alat-emergency dan sel "Kesiapan alat Emergency"
 * di matriks Score Card. Kalau masing-masing menulis sendiri aturan "siap",
 * ekspresi 31 kolom harian, dan cara membaca perusahaan pemilik, keduanya
 * akan berangsur berbeda tanpa ada yang menyadarinya. Jadi semuanya tinggal
 * di sini, dan kedua pemakai memanggil yang sama.
 *
 * ATURAN "SIAP": bulan itu ada hari yang terisi DAN tidak satu pun harinya
 * berstatus Not Good, Breakdown, atau Kembali ke CCR. Dari 22.170 baris hanya
 * 262 (1,2%) yang kondisinya campur dalam satu bulan, jadi aturan ini dan
 * "ada Good-nya" hampir sama hasilnya -- tetapi yang ini tidak pernah
 * menyebut alat rusak sebagai siap.
 *
 * PENYEBUTNYA SELURUH INVENTARIS, bukan yang diperiksa saja: yang ditanyakan
 * memang "di setiap bulannya diperiksa atau engga".
 */
final class KesiapanAlatEmergency
{
    public const TABEL_INVENTARIS = 'emergency_equipment_inventory';

    public const TABEL_INSPEKSI = 'emergency_equipment_daily_inspection';

    /** Status harian yang membatalkan kesiapan sebuah alat. */
    public const STATUS_TIDAK_SIAP = ['Not Good', 'Breakdown', 'Kembali ke CCR'];

    /** Label untuk alat yang pemiliknya memang tidak bisa ditentukan. */
    public const PEMILIK_TAK_DIKENAL = '(pemilik belum dicatat)';

    /**
     * Ekspresi penjumlahan untuk 31 kolom day_NN_condition.
     *
     * WAJIB `<=>`, BUKAN `=`. Dengan `=`, kolom hari yang kosong menghasilkan
     * NULL dan satu NULL membuat seluruh penjumlahan ikut NULL -- setiap bulan
     * 30 hari dan bulan berjalan langsung terbuang dari hasil, sehingga Juni,
     * September, dan Oktober terukur 0% padahal sebenarnya 95-96%.
     *
     * @return array{isi: string, good: string, ng: string, bd: string, ccr: string}
     */
    public static function ekspresiHari(): array
    {
        $kolom = [];

        for ($i = 1; $i <= 31; $i++) {
            $kolom[] = sprintf('`day_%02d_condition`', $i);
        }

        $cacah = static function (string $status) use ($kolom): string {
            $bagian = array_map(
                static fn (string $c): string => '(' . $c . " <=> '" . $status . "')",
                $kolom
            );

            return '(' . implode(' + ', $bagian) . ')';
        };

        $isi = array_map(
            static fn (string $c): string => '(' . $c . ' IS NOT NULL AND TRIM(' . $c . ") <> '')",
            $kolom
        );

        return [
            'isi' => '(' . implode(' + ', $isi) . ')',
            'good' => $cacah('Good'),
            'ng' => $cacah(self::STATUS_TIDAK_SIAP[0]),
            'bd' => $cacah(self::STATUS_TIDAK_SIAP[1]),
            'ccr' => $cacah(self::STATUS_TIDAK_SIAP[2]),
        ];
    }

    /**
     * Perusahaan pemilik sebuah alat, dari tabel inventaris.
     *
     * KOLOM perusahaan_pemilik KOSONG DI 3.485 BARIS, DAN ITU BUKAN DATA
     * HILANG. Seluruhnya berkepemilikan "BC", dan 3.485 ditambah 557 baris
     * yang menulis "PT BC" secara eksplisit berjumlah tepat 4.042 -- sama
     * persis dengan cacah alat berkepemilikan BC. Jadi yang kosong memang
     * milik Berau Coal sendiri dan boleh dibaca sebagai PT BC.
     *
     * Yang tidak bisa dipetakan hanya 3 baris dari 4.373. Baris itu TIDAK
     * dibuang, melainkan dikumpulkan di satu label sendiri supaya penyebutnya
     * tetap utuh dan kekurangannya kelihatan di layar.
     */
    public static function ekspresiPemilik(string $awalan = ''): string
    {
        $p = $awalan === '' ? '' : $awalan . '.';

        return 'COALESCE('
            . "NULLIF(TRIM({$p}perusahaan_pemilik), ''), "
            . "CASE WHEN TRIM({$p}kepemilikan_peralatan) = 'BC' THEN 'PT BC' END, "
            . "'" . self::PEMILIK_TAK_DIKENAL . "')";
    }

    /** Penanda 1/0 apakah satu alat siap pada bulan itu. */
    public static function ekspresiSiap(string $awalan = 'p'): string
    {
        $p = $awalan === '' ? '' : $awalan . '.';

        return "CASE WHEN {$p}hari_isi > 0 AND {$p}hari_ng = 0 "
            . "AND {$p}hari_bd = 0 AND {$p}hari_ccr = 0 THEN 1 ELSE 0 END";
    }

    /**
     * Satu baris per alat per bulan, dengan cacah hari per status.
     *
     * SEBUAH ALAT BISA PUNYA LEBIH DARI SATU LEMBAR dalam sebulan (9 kejadian
     * di Juni). Dijumlahkan dulu supaya temuan di lembar kedua tidak hilang
     * karena lembar pertama kebetulan bersih.
     */
    public static function perAlat(): Builder
    {
        $hari = self::ekspresiHari();

        return DB::table(self::TABEL_INSPEKSI)
            ->selectRaw(
                'no_registrasi, bulan, '
                . 'SUM(' . $hari['isi'] . ') AS hari_isi, '
                . 'SUM(' . $hari['good'] . ') AS hari_good, '
                . 'SUM(' . $hari['ng'] . ') AS hari_ng, '
                . 'SUM(' . $hari['bd'] . ') AS hari_bd, '
                . 'SUM(' . $hari['ccr'] . ') AS hari_ccr'
            )
            ->groupBy('no_registrasi', 'bulan');
    }

    /**
     * Penyebut: cacah alat di inventaris per site dan perusahaan pemilik.
     *
     * @return array<string, int>  "site|pemilik" => cacah alat
     */
    public static function baseline(): array
    {
        $rows = DB::table(self::TABEL_INVENTARIS)
            ->selectRaw(
                'TRIM(site) AS site, '
                . self::ekspresiPemilik() . ' AS pemilik, '
                . 'COUNT(*) AS total'
            )
            ->groupBy('site', 'pemilik')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $site = trim((string) $row->site);
            $pemilik = trim((string) $row->pemilik);

            if ($site === '' || $pemilik === '') {
                continue;
            }

            $out[$site . '|' . $pemilik] = (int) $row->total;
        }

        return $out;
    }

    /**
     * Pembilang: cacah alat siap per site, pemilik, dan bulan.
     *
     * JOIN-nya INNER dan itu disengaja: alat yang diperiksa tetapi tidak ada
     * di inventaris tidak punya penyebut, jadi tidak bisa dijadikan persentase.
     * Site-nya pun diambil dari INVENTARIS, bukan dari lembar periksa -- ada
     * 137 pasangan yang site-nya berbeda di antara kedua tabel, dan memakai
     * site lembar periksa membuat alat berpindah site dari bulan ke bulan
     * sehingga penyebutnya tidak pernah cocok.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function siapPerBulan()
    {
        return DB::query()
            ->fromSub(self::perAlat(), 'p')
            ->join(self::TABEL_INVENTARIS . ' as i', 'i.no_registrasi', '=', 'p.no_registrasi')
            ->selectRaw(
                'TRIM(i.site) AS site, '
                . self::ekspresiPemilik('i') . ' AS pemilik, '
                . 'p.bulan AS bulan, '
                . 'SUM(' . self::ekspresiSiap('p') . ') AS siap, '
                . 'COUNT(*) AS diperiksa'
            )
            ->groupBy('site', 'pemilik', 'bulan')
            ->get();
    }
}
