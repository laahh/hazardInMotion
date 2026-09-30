<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

/**
 * Mesin aturan kepatuhan roster — port PHP dari fungsi compute() referensi
 * safety-roster. Murni: tidak menyentuh database, cache, atau request.
 * Metodologi lengkap ada di docs/safety-roster-perhitungan-rumus.md.
 *
 * Urutan penulisan flag DIPERTAHANKAN sama seperti referensi karena
 * berpengaruh ke pesan yang tampil: REG-2 ditulis lebih dulu, lalu REG-1
 * menimpanya pada hari yang beririsan, baru REG-3 (hari cuti, tidak mungkin
 * beririsan dengan keduanya).
 *
 * Satu penyimpangan yang disengaja dari referensi: di sana ada `yel.fill('')`
 * sebelum array kuning dibaca, sehingga seluruh flag MAP-1/2/3 mati padahal
 * didokumentasikan (temuan T-3). Di sini flag kuning berfungsi.
 */
final class DmsRosterRuleEngine
{
    public const STATUS_PAGI = 'Shift Pagi';

    public const STATUS_MALAM = 'Shift Malam';

    public const STATUS_OVERSHIFT = 'Overshift';

    public const STATUS_OFF = 'Off';

    public const STATUS_CUTI = 'Cuti';

    /** @var array<string, int> */
    private array $ambang;

    /** @var list<string> */
    private array $kategoriLonggar;

    public function __construct()
    {
        /** @var array<string, int> $ambang */
        $ambang = config('dms_roster.ambang', []);
        $this->ambang = $ambang;

        /** @var list<string> $longgar */
        $longgar = config('dms_roster.kategori_longgar', []);
        $this->kategoriLonggar = $longgar;
    }

    /**
     * @return list<string>
     */
    public static function statusList(): array
    {
        return [
            self::STATUS_PAGI,
            self::STATUS_MALAM,
            self::STATUS_OVERSHIFT,
            self::STATUS_OFF,
            self::STATUS_CUTI,
        ];
    }

    /**
     * Normalisasi run off panjang menjadi fase Cuti — padanan toC() referensi.
     * Run 'o' LEBIH dari ambang off_ke_cuti (default 5) seluruhnya jadi 'c'.
     */
    public function normalisasiPola(string $polaMentah): string
    {
        $batas = $this->ambang['off_ke_cuti'] ?? 5;
        $p = $polaMentah;
        $n = strlen($p);
        $i = 0;

        while ($i < $n) {
            if ($p[$i] !== 'o') {
                $i++;

                continue;
            }

            $j = $i;
            while ($j < $n && $p[$j] === 'o') {
                $j++;
            }

            if ($j - $i > $batas) {
                $p = substr_replace($p, str_repeat('c', $j - $i), $i, $j - $i);
            }

            $i = $j;
        }

        return $p;
    }

    public function isLonggar(string $kategori): bool
    {
        return in_array($kategori, $this->kategoriLonggar, true);
    }

    /**
     * Evaluasi satu karyawan.
     *
     * @param  string  $polaMentah  Pola P/M/o hasil sinkronisasi RFID.
     * @param  int  $thr  Blok roster perusahaan; MAP-1 memakai thr - 1.
     * @param  string  $map  'pama' (wajib off saat ganti shift) atau 'block'.
     * @param  int|null  $r0  Indeks awal periode terpilih (null = 0).
     * @param  int|null  $r1  Indeks akhir periode terpilih (null = hari terakhir).
     */
    public function evaluasi(
        string $polaMentah,
        string $kategori,
        int $thr,
        string $map,
        ?int $r0 = null,
        ?int $r1 = null,
    ): DmsRosterEvaluation {
        $p = $this->normalisasiPola($polaMentah);
        $n = strlen($p);

        if ($n === 0) {
            return $this->evaluasiKosong($kategori);
        }

        $r0 = $r0 === null ? 0 : max(0, min($r0, $n - 1));
        $r1 = $r1 === null ? $n - 1 : max(0, min($r1, $n - 1));
        if ($r1 < $r0) {
            $r1 = $r0;
        }

        $longgar = $this->isLonggar($kategori);
        $maxblock = $thr - 1;

        $red = array_fill(0, $n, '');
        $yel = array_fill(0, $n, '');
        $dayno = $this->hitungDayno($p, $n);
        $rosterAt = $this->hitungRosterAt($p, $n);

        $f2 = $this->terapkanReg2($p, $n, $longgar, $red);
        $f1 = $this->terapkanReg1DanMapping($p, $n, $longgar, $maxblock, $map, $red, $yel, $dayno);
        $f3 = $this->terapkanReg3($p, $n, $longgar, $red);

        $adaMerah = false;
        $adaKuning = false;
        for ($i = $r0; $i <= $r1; $i++) {
            if ($red[$i] !== '') {
                $adaMerah = true;
            }
            if ($yel[$i] !== '') {
                $adaKuning = true;
            }
        }

        $terkini = $this->hitungRosterTerkini($p, $rosterAt, $r1);
        $ytd = $this->hitungYtd($p, $n, $rosterAt);
        $onCur = $this->hitungOnsiteBerjalan($p, $r1);

        return new DmsRosterEvaluation(
            pola: $p,
            dayno: $dayno,
            red: $red,
            yel: $yel,
            kategori: $kategori,
            longgar: $longgar,
            roster: $rosterAt[$r1],
            pagi: $terkini['pagi'],
            malam: $terkini['malam'],
            off: $terkini['off'],
            cutiCur: $terkini['cutiCur'],
            hadir: $terkini['pagi'] + $terkini['malam'] + $terkini['off'],
            onNoOff: $terkini['onNoOff'],
            onS: $terkini['onS'],
            onE: $terkini['onE'],
            onAll: $ytd['onAll'],
            onAllR: $ytd['onAllR'],
            onAllS: $ytd['onAllS'],
            onAllE: $ytd['onAllE'],
            cutiMin: $ytd['cutiMin'],
            cutiMinR: $ytd['cutiMinR'],
            cutiMinS: $ytd['cutiMinS'],
            cutiMinE: $ytd['cutiMinE'],
            onCur: $onCur['onCur'],
            onCurS: $onCur['onCurS'],
            status: $this->hitungStatus($p, $r1),
            adaMerahDiPeriode: $adaMerah,
            adaKuningDiPeriode: $adaKuning,
            everRed: $this->adaIsi($red),
            everYel: $this->adaIsi($yel),
            redK1: $f1,
            redK2: $f2,
            redK3: $f3,
        );
    }

    /**
     * Apakah karyawan ini wajib segera dicutikan per hari terakhir periode.
     */
    public function wajibCuti(DmsRosterEvaluation $e): bool
    {
        return ! $e->longgar && $e->onCur > ($this->ambang['wajib_cuti'] ?? 71);
    }

    /**
     * @return array<string, int>
     */
    public function ambang(): array
    {
        return $this->ambang;
    }

    /**
     * Counter "hari ke-N" untuk run karakter yang sama.
     *
     * @return list<int>
     */
    private function hitungDayno(string $p, int $n): array
    {
        $dayno = array_fill(0, $n, 1);
        for ($i = 1; $i < $n; $i++) {
            $dayno[$i] = $p[$i] === $p[$i - 1] ? $dayno[$i - 1] + 1 : 1;
        }

        return $dayno;
    }

    /**
     * Nomor roster per hari — naik setiap satu blok cuti selesai.
     *
     * @return list<int>
     */
    private function hitungRosterAt(string $p, int $n): array
    {
        $rosterAt = array_fill(0, $n, 1);
        $cyc = 1;
        for ($i = 0; $i < $n; $i++) {
            $rosterAt[$i] = $cyc;
            if ($p[$i] === 'c' && ($i + 1 >= $n || $p[$i + 1] !== 'c')) {
                $cyc++;
            }
        }

        return $rosterAt;
    }

    /**
     * REG-2 — run on-site (P/M/o) melebihi ambang tanpa blok cuti.
     *
     * @param  list<string>  $red
     */
    private function terapkanReg2(string $p, int $n, bool $longgar, array &$red): bool
    {
        $batas = $this->ambang['onsite'] ?? 71;
        $kena = false;
        $i = 0;

        while ($i < $n) {
            if ($p[$i] === 'c') {
                $i++;

                continue;
            }

            $j = $i;
            while ($j < $n && $p[$j] !== 'c') {
                $j++;
            }
            $len = $j - $i;

            if ($len > $batas && ! $longgar) {
                $kena = true;
                $pesan = "On-site {$len} hr belum cuti (>70) — PELANGGARAN";
                for ($k = $i; $k < $j; $k++) {
                    $red[$k] = $pesan;
                }
            }

            $i = $j;
        }

        return $kena;
    }

    /**
     * REG-1 (merah) + MAP-1/2/3 (kuning). MAP hanya diuji bila run kerja
     * BELUM kena REG-1, sesuai desain referensi ("melebihi blok roster tetapi
     * belum ≥14 hari").
     *
     * @param  list<string>  $red
     * @param  list<string>  $yel
     * @param  list<int>  $dayno
     */
    private function terapkanReg1DanMapping(
        string $p,
        int $n,
        bool $longgar,
        int $maxblock,
        string $map,
        array &$red,
        array &$yel,
        array &$dayno,
    ): bool {
        $batas = $this->ambang['kerja_beruntun'] ?? 13;
        $kena = false;
        $i = 0;

        while ($i < $n) {
            if ($p[$i] !== 'P' && $p[$i] !== 'M') {
                $i++;

                continue;
            }

            $j = $i;
            while ($j < $n && ($p[$j] === 'P' || $p[$j] === 'M')) {
                $j++;
            }
            $len = $j - $i;

            if ($len > $batas && ! $longgar) {
                $kena = true;
                $pesan = "Kerja beruntun {$len} hr (≥14) — PELANGGARAN";
                for ($k = $i; $k < $j; $k++) {
                    $red[$k] = $pesan;
                    $dayno[$k] = $k - $i + 1;
                }
            } else {
                if ($len > $maxblock && ! $longgar) {
                    $pesan = "Kerja beruntun {$len} hr — melebihi blok roster ({$maxblock})";
                    for ($k = $i; $k < $j; $k++) {
                        if ($red[$k] === '' && $yel[$k] === '') {
                            $yel[$k] = $pesan;
                        }
                    }
                }

                if ($map === 'pama') {
                    for ($k = $i + 1; $k < $j; $k++) {
                        if ($p[$k] !== $p[$k - 1] && $red[$k] === '' && $yel[$k] === '') {
                            $yel[$k] = 'Wajib OFF saat ganti shift (Pagi↔Malam)';
                        }
                    }
                } else {
                    $sudahMalam = false;
                    for ($k = $i; $k < $j; $k++) {
                        if ($p[$k] === 'M') {
                            $sudahMalam = true;
                        } elseif ($p[$k] === 'P' && $sudahMalam && $red[$k] === '' && $yel[$k] === '') {
                            $yel[$k] = 'Urutan shift tak sesuai (Pagi setelah Malam)';
                        }
                    }
                }
            }

            $i = $j;
        }

        return $kena;
    }

    /**
     * REG-3 — blok cuti lebih pendek dari ambang.
     *
     * @param  list<string>  $red
     */
    private function terapkanReg3(string $p, int $n, bool $longgar, array &$red): bool
    {
        $batas = $this->ambang['cuti_min'] ?? 12;
        $kena = false;
        $i = 0;

        while ($i < $n) {
            if ($p[$i] !== 'c') {
                $i++;

                continue;
            }

            $j = $i;
            while ($j < $n && $p[$j] === 'c') {
                $j++;
            }
            $len = $j - $i;

            if ($len < $batas && ! $longgar) {
                $kena = true;
                $pesan = "Cuti {$len} hr (<14) — PELANGGARAN";
                for ($k = $i; $k < $j; $k++) {
                    $red[$k] = $pesan;
                }
            }

            $i = $j;
        }

        return $kena;
    }

    /**
     * Hitungan yang dibatasi roster berjalan (cycle pada hari $r1).
     *
     * @param  list<int>  $rosterAt
     * @return array{pagi:int,malam:int,off:int,cutiCur:int,onNoOff:int,onS:int,onE:int}
     */
    private function hitungRosterTerkini(string $p, array $rosterAt, int $r1): array
    {
        $curCyc = $rosterAt[$r1];
        $pagi = 0;
        $malam = 0;
        $off = 0;
        $cutiCur = 0;
        $onNoOff = 0;
        $onS = -1;
        $onE = -1;
        $run = 0;
        $runStart = -1;

        for ($i = 0; $i <= $r1; $i++) {
            if ($rosterAt[$i] !== $curCyc) {
                continue;
            }

            $ch = $p[$i];
            if ($ch === 'P' || $ch === 'M') {
                if ($ch === 'P') {
                    $pagi++;
                } else {
                    $malam++;
                }
                if ($run === 0) {
                    $runStart = $i;
                }
                $run++;
                if ($run > $onNoOff) {
                    $onNoOff = $run;
                    $onS = $runStart;
                    $onE = $i;
                }
            } elseif ($ch === 'o') {
                $off++;
                $run = 0;
            } else {
                $cutiCur++;
                $run = 0;
            }
        }

        return compact('pagi', 'malam', 'off', 'cutiCur', 'onNoOff', 'onS', 'onE');
    }

    /**
     * On-site maks & cuti min lintas SEMUA roster (YTD) — tidak terpengaruh
     * periode terpilih, persis seperti referensi.
     *
     * @param  list<int>  $rosterAt
     * @return array{onAll:int,onAllR:int,onAllS:int,onAllE:int,cutiMin:int,cutiMinR:int,cutiMinS:int,cutiMinE:int}
     */
    private function hitungYtd(string $p, int $n, array $rosterAt): array
    {
        $onAll = 0;
        $onAllR = 0;
        $onAllS = -1;
        $onAllE = -1;
        $i = 0;
        while ($i < $n) {
            if ($p[$i] !== 'c') {
                $j = $i;
                while ($j < $n && $p[$j] !== 'c') {
                    $j++;
                }
                $len = $j - $i;
                if ($len > $onAll) {
                    $onAll = $len;
                    $onAllR = $rosterAt[$i];
                    $onAllS = $i;
                    $onAllE = $j - 1;
                }
                $i = $j;
            } else {
                $i++;
            }
        }

        $cutiMin = 0;
        $cutiMinR = 0;
        $cutiMinS = -1;
        $cutiMinE = -1;
        $i = 0;
        while ($i < $n) {
            if ($p[$i] === 'c') {
                $j = $i;
                while ($j < $n && $p[$j] === 'c') {
                    $j++;
                }
                $len = $j - $i;
                if ($cutiMin === 0 || $len < $cutiMin) {
                    $cutiMin = $len;
                    $cutiMinR = $rosterAt[$i];
                    $cutiMinS = $i;
                    $cutiMinE = $j - 1;
                }
                $i = $j;
            } else {
                $i++;
            }
        }

        return compact(
            'onAll', 'onAllR', 'onAllS', 'onAllE',
            'cutiMin', 'cutiMinR', 'cutiMinS', 'cutiMinE',
        );
    }

    /**
     * @return array{onCur:int,onCurS:int}
     */
    private function hitungOnsiteBerjalan(string $p, int $r1): array
    {
        $onCur = 0;
        $k = $r1;
        while ($k >= 0 && $p[$k] !== 'c') {
            $onCur++;
            $k--;
        }

        return ['onCur' => $onCur, 'onCurS' => $k + 1];
    }

    private function hitungStatus(string $p, int $r1): string
    {
        $last = $p[$r1];

        if ($last === 'c') {
            return self::STATUS_CUTI;
        }

        if ($last === 'o') {
            return self::STATUS_OFF;
        }

        $run = 0;
        $k = $r1;
        while ($k >= 0 && ($p[$k] === 'P' || $p[$k] === 'M')) {
            $run++;
            $k--;
        }

        if ($run >= ($this->ambang['overshift'] ?? 8)) {
            return self::STATUS_OVERSHIFT;
        }

        return $last === 'P' ? self::STATUS_PAGI : self::STATUS_MALAM;
    }

    /**
     * @param  list<string>  $flags
     */
    private function adaIsi(array $flags): bool
    {
        foreach ($flags as $flag) {
            if ($flag !== '') {
                return true;
            }
        }

        return false;
    }

    private function evaluasiKosong(string $kategori): DmsRosterEvaluation
    {
        return new DmsRosterEvaluation(
            pola: '', dayno: [], red: [], yel: [],
            kategori: $kategori, longgar: $this->isLonggar($kategori),
            roster: 1, pagi: 0, malam: 0, off: 0, cutiCur: 0, hadir: 0,
            onNoOff: 0, onS: -1, onE: -1,
            onAll: 0, onAllR: 0, onAllS: -1, onAllE: -1,
            cutiMin: 0, cutiMinR: 0, cutiMinS: -1, cutiMinE: -1,
            onCur: 0, onCurS: -1, status: self::STATUS_OFF,
            adaMerahDiPeriode: false, adaKuningDiPeriode: false,
            everRed: false, everYel: false,
            redK1: false, redK2: false, redK3: false,
        );
    }
}
