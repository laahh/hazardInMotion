<?php

declare(strict_types=1);

namespace App\Services\OhsScoreCard;

/**
 * Definisi bersama parameter "Pemenuhan Regulasi".
 *
 * ADA DUA PEMAKAI YANG HARUS SEPAKAT: halaman /ohs-score-card/pemenuhan-regulasi
 * dan baris "Pemenuhan Regulasi" di matriks Score Card. Yang ditaruh di sini
 * hanya hal yang kalau berbeda akan membuat kedua tempat itu menampilkan angka
 * berlainan: nama tabel, daftar sektor, dan cara membaca angkanya.
 *
 * ANGKANYA DISIMPAN SEBAGAI VARCHAR, BUKAN ANGKA, dan sel kosong ditulis "-"
 * (tanda hubung), bukan NULL. Membacanya dengan (float) langsung membuat "-"
 * menjadi 0 dan menyeret rata-rata turun tanpa dasar; 8 dari 57 sel memakai
 * "-". Itulah sebabnya angka() ada dan dipakai di mana-mana.
 *
 * RUMUS KEPATUHAN SUDAH DIBUKTIKAN dari datanya sendiri:
 * complied / (complied + in_progress), cocok di 49 dari 49 sel yang berangka,
 * tanpa satu pun selisih.
 */
final class PemenuhanRegulasi
{
    public const TABEL_RINGKASAN = 'regulatory_compliance_summary';

    public const TABEL_DETAIL = 'regulatory_compliance_detail';

    /**
     * Tiga sektor di tabel ringkasan, beserta akhiran kolomnya.
     *
     * Kolomnya berpola compliance_rate_<kunci>, in_progress_<kunci>, dan
     * complied_<kunci>.
     */
    public const SEKTOR = [
        'health_safety' => 'Health & Safety',
        'additional_compliance' => 'Additional Compliance',
        'environment' => 'Environment',
    ];

    /**
     * Membaca angka yang tersimpan sebagai teks.
     *
     * "-" DAN TEKS KOSONG BUKAN NOL, melainkan "sektor itu tidak ditugaskan".
     * Mengembalikan null untuk keduanya supaya pemanggilnya bisa membedakan
     * sel yang memang nol dari sel yang tidak ada isinya.
     */
    public static function angka(mixed $mentah): ?float
    {
        if ($mentah === null) {
            return null;
        }

        $teks = trim((string) $mentah);

        if ($teks === '' || $teks === '-') {
            return null;
        }

        // Sebagian angka ditulis dengan koma desimal.
        $teks = str_replace(',', '.', $teks);

        return is_numeric($teks) ? (float) $teks : null;
    }

    /**
     * Kewajiban yang dipenuhi dan seluruh kewajiban satu baris ringkasan,
     * dijumlahkan dari seluruh sektor yang ditugaskan.
     *
     * Dikembalikan sebagai CACAH, bukan persentase, supaya pemanggilnya bisa
     * merata-ratakan secara TERTIMBANG. Merata-ratakan persentase antar sektor
     * memberi bobot sama kepada sektor berisi 3 kewajiban dan sektor berisi 300.
     *
     * @return array{patuh: float, total: float}
     */
    public static function cacahBaris(object $row): array
    {
        $patuh = 0.0;
        $total = 0.0;

        foreach (array_keys(self::SEKTOR) as $kunci) {
            $c = self::angka($row->{'complied_' . $kunci} ?? null);
            $p = self::angka($row->{'in_progress_' . $kunci} ?? null);

            // Keduanya "-" berarti sektor itu tidak ditugaskan ke kombinasi
            // ini, bukan nol persen -- jadi tidak ikut menjadi penyebut.
            if ($c === null && $p === null) {
                continue;
            }

            $patuh += $c ?? 0.0;
            $total += ($c ?? 0.0) + ($p ?? 0.0);
        }

        return ['patuh' => $patuh, 'total' => $total];
    }
}
