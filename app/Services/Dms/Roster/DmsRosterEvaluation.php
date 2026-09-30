<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

/**
 * Hasil evaluasi satu karyawan oleh DmsRosterRuleEngine. Immutable — engine
 * selalu mengembalikan objek baru, tidak pernah mengubah input.
 *
 * Indeks hari (onS, onAllS, cutiMinS, dst.) adalah posisi karakter di dalam
 * $pola, di mana karakter ke-0 = 1 Januari tahun berjalan. Nilai -1 berarti
 * "tidak ada".
 */
final readonly class DmsRosterEvaluation
{
    /**
     * @param  string  $pola  Pola setelah normalisasi o → c.
     * @param  list<int>  $dayno  Hari ke-N dalam run yang sedang berjalan.
     * @param  list<string>  $red  Pesan pelanggaran regulasi per hari ('' = bersih).
     * @param  list<string>  $yel  Pesan mapping per hari ('' = bersih).
     */
    public function __construct(
        public string $pola,
        public array $dayno,
        public array $red,
        public array $yel,
        public string $kategori,
        public bool $longgar,
        // Roster berjalan (dibatasi cycle pada hari terakhir periode)
        public int $roster,
        public int $pagi,
        public int $malam,
        public int $off,
        public int $cutiCur,
        public int $hadir,
        public int $onNoOff,
        public int $onS,
        public int $onE,
        // YTD — lintas semua roster, lepas dari periode terpilih
        public int $onAll,
        public int $onAllR,
        public int $onAllS,
        public int $onAllE,
        public int $cutiMin,
        public int $cutiMinR,
        public int $cutiMinS,
        public int $cutiMinE,
        // On-site berjalan per hari terakhir periode
        public int $onCur,
        public int $onCurS,
        public string $status,
        // Flag di dalam periode terpilih
        public bool $adaMerahDiPeriode,
        public bool $adaKuningDiPeriode,
        // Flag YTD per jenis rule
        public bool $everRed,
        public bool $everYel,
        public bool $redK1,
        public bool $redK2,
        public bool $redK3,
    ) {}
}
