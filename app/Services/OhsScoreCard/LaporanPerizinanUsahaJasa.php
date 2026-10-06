<?php

declare(strict_types=1);

namespace App\Services\OhsScoreCard;

/**
 * Definisi bersama parameter "Laporan Perizinan Usaha Jasa".
 *
 * PERSENTASENYA DIBACA DARI KOLOM performance_<bulan>_26_pct, yang menyimpan
 * RASIO 0-1 (0,92857143 berarti 92,86%) sehingga dikalikan 100 -- bukan dibaca
 * sebagai persen mentah.
 *
 * KOLOMNYA TINGKAT PEMENUHAN, BUKAN PROPORSI DEVIASI. Seratus persen berarti
 * tidak ada subkontraktor yang menyimpang dan itu hasil TERBAIK; arahnya NAIK,
 * sama seperti Pemenuhan Regulasi.
 *
 * ISI KOLOM INI PERNAH BERUBAH ARTI, dan itu sebabnya pemeriksaan silang di
 * bawah ada. Pada 6 Oktober 2026 kolomnya masih memuat proporsi deviasi
 * (deviasi/total, 0 berarti terbaik); hari yang sama isinya diganti menjadi
 * komplemennya, 1 - deviasi/total. Pembalikan seperti itu tidak mengubah
 * bentuk datanya sama sekali -- tetap rasio 0-1 yang terlihat wajar -- jadi
 * satu-satunya cara menangkapnya adalah membandingkannya dengan kolom cacah.
 *
 * KOLOM deviasi TETAP DIBACA sebagai pendamping: cacahnya dipakai di tooltip
 * dan modal, dan dipakai MEMERIKSA kolom pct lewat penanda 'sepakat'. Kalau
 * suatu saat keduanya tidak lagi sejalan, selisihnya dilaporkan di catatan
 * halaman alih-alih diam-diam dipilih salah satu.
 *
 * TABELNYA BERFORMAT LEBAR: satu baris per main_cont x site_dedicated, dengan
 * bulan sebagai KOLOM, bukan baris. Akhiran bulannya singkatan Indonesia dan
 * tahunnya menempel di nama kolom ("_26"), jadi kalau data 2027 masuk nanti
 * nama kolomnya akan berubah dan BULAN di bawah perlu ditambah.
 */
final class LaporanPerizinanUsahaJasa
{
    public const TABEL = 'scr_business_license_performance';

    /** Tahun yang menempel di nama kolom. */
    public const TAHUN = '26';

    /**
     * Akhiran kolom bulan => nomor bulan.
     *
     * Hanya Januari sampai September yang ada kolomnya di tabel; Oktober ke
     * atas belum dibuat.
     */
    public const BULAN = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mei' => 5,
        'jun' => 6, 'jul' => 7, 'agu' => 8, 'sep' => 9,
    ];

    public const LABEL_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September',
    ];

    public static function kolomDeviasi(string $akhiran): string
    {
        return 'deviasi_' . $akhiran . '_' . self::TAHUN;
    }

    public static function kolomPersen(string $akhiran): string
    {
        return 'performance_' . $akhiran . '_' . self::TAHUN . '_pct';
    }

    /**
     * Angka satu baris pada satu bulan.
     *
     * PERSENNYA DARI KOLOM performance_<bulan>_26_pct (rasio 0-1, dikali 100),
     * dan merupakan TINGKAT PEMENUHAN. Cacah deviasi ikut dikembalikan sebagai
     * pendamping untuk tooltip dan modal, beserta penanda 'sepakat' yang
     * mengatakan apakah kolom pct masih sejalan dengan 1 - deviasi/total.
     *
     * Mengembalikan null kalau bulan itu memang tidak terdata, supaya
     * pemanggilnya bisa membedakannya dari pemenuhan nol yang sesungguhnya.
     *
     * @return array{persen: float, deviasi: int|null, total: int, sepakat: bool}|null
     */
    public static function selBulan(object $row, string $akhiran): ?array
    {
        $total = (int) ($row->total_perusahaan_subcontractor ?? 0);
        $pct = $row->{self::kolomPersen($akhiran)} ?? null;
        $deviasi = $row->{self::kolomDeviasi($akhiran)} ?? null;

        // Tanpa subkontraktor tidak ada penyebut, dan bulan yang kolom
        // persennya belum terisi bukan berarti nol pemenuhan.
        if ($total <= 0 || $pct === null) {
            return null;
        }

        // Rasio 0-1, bukan persen mentah.
        $persen = round((float) $pct * 100, 2);

        return [
            'persen' => $persen,
            'deviasi' => $deviasi === null ? null : (int) $deviasi,
            'total' => $total,
            // Pemenuhan semestinya komplemen dari proporsi deviasi.
            'sepakat' => $deviasi === null
                || abs(round((1 - (int) $deviasi / $total) * 100, 2) - $persen) <= 0.02,
        ];
    }
}
