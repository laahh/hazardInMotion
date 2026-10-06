<?php

declare(strict_types=1);

namespace App\Services\OhsScoreCard;

/**
 * Definisi bersama parameter "Laporan Perizinan Usaha Jasa".
 *
 * ARAHNYA TERBALIK DARI PARAMETER KEPATUHAN LAIN, dan ini hal pertama yang
 * harus disadari: kolom performance_<bulan>_26_pct BUKAN tingkat kepatuhan,
 * melainkan PROPORSI DEVIASI. Nol berarti tidak ada subkontraktor yang
 * menyimpang -- itu hasil TERBAIK, bukan terburuk. Memperlakukannya seperti
 * Pemenuhan Regulasi akan membalik seluruh pemeringkatan dan pewarnaannya.
 *
 * RUMUSNYA SUDAH DIBUKTIKAN dari datanya sendiri:
 * deviasi_<bulan>_26 / total_perusahaan_subcontractor, cocok di 144 dari 144
 * sel, tanpa satu pun selisih.
 *
 * ANGKANYA DIHITUNG ULANG DARI CACAH, BUKAN DIBACA DARI KOLOM pct. Dua alasan:
 * (1) supaya rata-rata antar bulan bisa TERTIMBANG, dan (2) karena
 * performance_sep_26_pct bertipe int sedangkan delapan bulan lainnya
 * decimal(18,8) -- rasio seperti 0,07 akan terpotong menjadi 0 kalau ditulis
 * ke kolom itu. Kolom deviasi semuanya int dan utuh, jadi itu yang dipercaya.
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
     * Cacah deviasi dan penyebutnya untuk satu baris pada satu bulan.
     *
     * Mengembalikan null kalau bulan itu memang tidak terdata, supaya
     * pemanggilnya bisa membedakannya dari nol deviasi yang sesungguhnya.
     *
     * @return array{deviasi: int, total: int}|null
     */
    public static function selBulan(object $row, string $akhiran): ?array
    {
        $total = (int) ($row->total_perusahaan_subcontractor ?? 0);
        $deviasi = $row->{self::kolomDeviasi($akhiran)} ?? null;

        // Tanpa subkontraktor tidak ada yang bisa dibagi, dan deviasi yang
        // belum terisi bukan berarti nol deviasi.
        if ($total <= 0 || $deviasi === null) {
            return null;
        }

        return ['deviasi' => (int) $deviasi, 'total' => $total];
    }
}
