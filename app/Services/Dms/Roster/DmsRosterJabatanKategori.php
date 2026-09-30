<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use Illuminate\Support\Facades\Cache;

/**
 * Pemetaan jabatan struktural → kategori roster (Operator A2B, Operator
 * Hauler, Operator Transportasi Massal, Mekanik, Lainnya).
 *
 * Dua lapis, karena populasi live punya 483 jabatan berbeda sementara tabel
 * jabcat.json hasil ekstraksi referensi hanya memuat ±150:
 *
 *   1. Pencocokan persis ke jabcat.json (case-insensitive — referensi
 *      memakai lookup case-sensitive sehingga varian penulisan seperti
 *      "Operator Tp" vs "OPERATOR TP" jatuh ke Lainnya di sana).
 *   2. Fallback kata kunci untuk jabatan yang tidak ada di tabel.
 *
 * Jabatan yang tetap tidak terklasifikasi menjadi 'Lainnya', dan 'Lainnya'
 * TIDAK termasuk kategori longgar — artinya rule regulasi tetap berlaku
 * penuh. Ini disengaja: untuk dashboard keselamatan, salah tebak sebaiknya
 * membuat orang tetap terpantau, bukan malah dikecualikan.
 */
final class DmsRosterJabatanKategori
{
    public const A2B = 'Operator A2B';

    public const HAULER = 'Operator Hauler';

    public const TRANSPORTASI = 'Operator Transportasi Massal';

    public const MEKANIK = 'Mekanik';

    public const LAINNYA = 'Lainnya';

    private const CACHE_KEY = 'dms_roster:jabcat:v1';

    private const CACHE_TTL = 3600;

    /**
     * Tabel referensi yang dipakai bersama halaman snapshot statis
     * (/dms/roster-compliance-static) supaya kedua halaman memakai pemetaan
     * yang sama dan tidak pernah berbeda hasil.
     */
    private const JABCAT_PATH = 'dms-assets/roster-compliance/jabcat.json';

    /**
     * Fallback kata kunci, diuji BERURUTAN — yang lebih spesifik lebih dulu.
     * Kategori longgar (Mekanik, Transportasi Massal) hanya diberikan bila
     * kata kuncinya tidak ambigu.
     *
     * @var list<array{0: list<string>, 1: string}>
     */
    private const KEYWORD_RULES = [
        [['MECHANIC', 'MEKANIK', 'WELDER', 'TYREMAN', 'TYRE MAN', 'FITTER', 'TECHNICIAN', 'TEKNISI'], self::MEKANIK],
        [['LIGHT VEHICLE', 'DRIVER LV', 'OPERATOR LV', ' LV', 'MANHAUL', 'MAN HAUL', 'BUS', 'ELF', 'MINIBUS'], self::TRANSPORTASI],
        [[
            'EXCAVATOR', 'EXCA', 'DOZER', 'GRADER', 'LOADER', 'COMPACTOR', 'BOMAG',
            'CRANE', 'DRILL', 'A2B', 'LIFTING', 'CHIPSEAL', 'CHIP SEAL', 'CHIPSELER',
            'BACK HOE', 'BACKHOE', 'PLANT SERVICES', 'SLURRY',
        ], self::A2B],
        [[
            'HAULER', 'DUMP TRUCK', 'DUMPTRUCK', 'OHT', 'RDT', 'ADT', 'HD ', 'HD4', 'HD7', 'HD8',
            'WATER TRUCK', 'FUEL TRUCK', 'LUBE', 'TRAILER', 'LOWBOY', 'ANFO', 'TOWER LAMP',
            ' DT', 'DT ', 'WT', 'LT/WT', 'SERVICE TRUCK', 'WASHING TRUCK', 'PRODUCTION', ' TP',
        ], self::HAULER],
        [['OPERATOR', 'OPT.', 'OP.', 'OP '], self::HAULER],
        [['DRIVER'], self::HAULER],
    ];

    /**
     * @var array<string, string>|null
     */
    private ?array $tabel = null;

    public function kategoriDari(?string $jabatan): string
    {
        $bersih = strtoupper(trim((string) $jabatan));
        if ($bersih === '') {
            return self::LAINNYA;
        }

        $tabel = $this->tabel();
        if (isset($tabel[$bersih])) {
            return $tabel[$bersih];
        }

        foreach (self::KEYWORD_RULES as [$kataKunci, $kategori]) {
            foreach ($kataKunci as $kata) {
                if (str_contains($bersih, $kata)) {
                    return $kategori;
                }
            }
        }

        return self::LAINNYA;
    }

    /**
     * @return list<string>
     */
    public static function semua(): array
    {
        return [self::A2B, self::HAULER, self::TRANSPORTASI, self::MEKANIK, self::LAINNYA];
    }

    /**
     * @return array<string, string>
     */
    private function tabel(): array
    {
        if ($this->tabel !== null) {
            return $this->tabel;
        }

        /** @var array<string, string> $tabel */
        $tabel = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $path = public_path(self::JABCAT_PATH);
            if (! is_file($path)) {
                return [];
            }

            $isi = json_decode((string) file_get_contents($path), true);
            if (! is_array($isi)) {
                return [];
            }

            $out = [];
            foreach ($isi as $jabatan => $kategori) {
                if (is_string($jabatan) && is_string($kategori)) {
                    $out[strtoupper(trim($jabatan))] = $kategori;
                }
            }

            return $out;
        });

        $this->tabel = $tabel;

        return $tabel;
    }
}
