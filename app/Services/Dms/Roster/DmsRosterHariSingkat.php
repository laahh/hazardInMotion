<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

/**
 * Pengecualian aturan > 13 hari berturut-turut: bila hari yang membuat run
 * kerja melewati batas (hari ke-14) total check-in → check-out-nya hanya
 * <= N menit (bawaan 2 jam), orang itu dianggap hanya datang sebentar dan
 * hari itu DILEWATI — tidak dihitung kerja, tapi juga tidak memutus run.
 * Hari kerja berikutnya menjadi hari ke-14 dan dicek lagi dengan cara sama.
 *
 * Aturan turunan (keputusan diskusi 28 Okt 2026):
 *   - hanya hari di titik batas yang dicek; hari singkat di tengah run tetap
 *     dihitung kerja;
 *   - durasi tidak diketahui (tidak ada check-out) = tetap potensial banned;
 *   - run yang menyentuh awal jendela (terpotong) tidak dicek — hari ke-14
 *     aslinya berada di luar data.
 *
 * Murni, tanpa DB.
 */
final class DmsRosterHariSingkat
{
    /** Batas menit "datang sebentar" (<=), dari config. */
    public static function maksMenit(): int
    {
        return max(0, (int) config('dms_roster.reg_live.aturan.beruntun_hari_singkat_menit', 120));
    }

    /**
     * @param  array{mulai:int,selesai:int,hari:int}  $run  Run kerja beruntun (offset inklusif).
     * @param  int  $batas  beruntun_maks (13) — hari ke-(batas+1) yang dicek.
     * @param  array<int, int|null>  $menit  offset hari => total menit check-in → check-out; tidak ada = tidak diketahui.
     * @return array{melanggar:bool,dilewati:list<int>,hari_langgar:?int,hari_efektif:int}
     *         hari_langgar = offset hari ke-14 efektif (null bila tidak melanggar);
     *         hari_efektif = panjang run tanpa hari yang dilewati.
     */
    public static function sesuaikan(array $run, int $batas, array $menit, ?int $maksMenit = null): array
    {
        $maksMenit ??= self::maksMenit();
        $dilewati = [];
        $i = $run['mulai'] + $batas;

        while ($i <= $run['selesai'] && isset($menit[$i]) && $menit[$i] <= $maksMenit) {
            $dilewati[] = $i;
            $i++;
        }

        $melanggar = $i <= $run['selesai'];

        return [
            'melanggar' => $melanggar,
            'dilewati' => $dilewati,
            'hari_langgar' => $melanggar ? $i : null,
            'hari_efektif' => $run['hari'] - count($dilewati),
        ];
    }

    public static function teksDurasi(int $menit): string
    {
        return intdiv($menit, 60).'j '.($menit % 60).'m';
    }
}
