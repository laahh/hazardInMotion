<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use Carbon\CarbonImmutable;

/**
 * Menyusun seri pelanggaran harian menjadi grid kalender (baris Senin–Minggu x
 * kolom minggu) untuk kartu "Pola Kepatuhan Roster Harian".
 *
 * Kelas ini murni: masukannya seri `harian` dari
 * DmsRosterComplianceService::dashboard() — jumlah karyawan yang kena flag
 * merah pada tiap hari — dan tidak menyentuh database sama sekali.
 *
 * Skala warnanya KUINTIL, bukan ambang persen tetap. Kepatuhan harian pada
 * data nyata hanya bergerak di rentang sempit (±71–85%), sehingga ambang tetap
 * seperti <50% / 50–74% / 75–89% membuat hampir seluruh sel jatuh ke satu-dua
 * warna dan petanya tidak terbaca. Batas kuintil dihitung dari data lalu
 * dicetak apa adanya di legenda, jadi warnanya relatif tapi angkanya jujur.
 */
final class DmsRosterHeatmapBuilder
{
    /** Jumlah tingkat warna selain "tidak ada data" (lvl-1 s/d lvl-5). */
    private const JUMLAH_LEVEL = 5;

    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    /**
     * @param  list<int>  $harian    jumlah karyawan kena pelanggaran per hari
     * @param  int  $total           populasi yang dievaluasi (penyebut kepatuhan)
     * @param  string  $tanggalMulai tanggal untuk $harian[0]
     * @return array<string, mixed>|null null bila seri kosong / tak bisa dipakai
     */
    public function bangun(array $harian, int $total, string $tanggalMulai): ?array
    {
        $harian = array_values(array_map(static fn (mixed $v): int => (int) $v, $harian));
        if ($harian === [] || $total <= 0) {
            return null;
        }

        $awal = CarbonImmutable::parse($tanggalMulai)->startOfDay();
        $persen = array_map(
            static fn (int $v): float => round(($total - $v) / $total * 100, 2),
            $harian,
        );

        $batas = $this->batasKuintil($persen);
        $level = array_map(fn (float $v): int => $this->level($v, $batas), $persen);

        return [
            'kolom' => $this->jumlahKolom($awal, count($harian)),
            'grid' => $this->grid($awal, $harian, $persen, $level, $total),
            'labelKolom' => $this->labelKolom($awal, count($harian)),
            'legenda' => $this->legenda($persen, $level),
            'puncak' => $this->puncak($awal, $harian),
            'perHari' => $perHari = $this->perHari($awal, $harian),
            // Seberapa jauh hari terpadat dari hari terlonggar, relatif. Flag
            // pelanggaran bersifat blok (sekali kena REG-2, karyawan ter-flag
            // berhari-hari berturut-turut), jadi efek hari-dalam-seminggu
            // biasanya nol. Angka ini dipakai view untuk memutuskan apakah
            // boleh menyimpulkan ada pola harian atau justru menyatakan tidak.
            'sebaranHari' => $this->sebaranHari($perHari),
            'rata' => (int) round(array_sum($harian) / count($harian)),
            'periode' => $awal->translatedFormat('j M Y')
                .' – '.$awal->addDays(count($harian) - 1)->translatedFormat('j M Y'),
        ];
    }

    /**
     * Batas antar tingkat: nilai kepatuhan pada posisi 1/5, 2/5, 3/5, 4/5 dari
     * seri yang sudah diurutkan. Nilai kembar dibuang supaya dua tingkat tidak
     * pernah punya batas yang sama (bisa terjadi bila variasi datanya kecil).
     *
     * @param  list<float>  $persen
     * @return list<float>
     */
    private function batasKuintil(array $persen): array
    {
        sort($persen);
        $n = count($persen);
        $batas = [];

        for ($i = 1; $i < self::JUMLAH_LEVEL; $i++) {
            $batas[] = $persen[(int) floor($i * $n / self::JUMLAH_LEVEL)] ?? $persen[$n - 1];
        }

        return array_values(array_unique($batas));
    }

    /**
     * @param  list<float>  $batas
     */
    private function level(float $persen, array $batas): int
    {
        $level = 1;
        foreach ($batas as $b) {
            if ($persen >= $b) {
                $level++;
            }
        }

        return $level;
    }

    /**
     * Kolom bertambah setiap hari Senin — sama dengan grid timeline per
     * karyawan, supaya kedua heatmap bisa dibandingkan kolom per kolom.
     */
    private function jumlahKolom(CarbonImmutable $awal, int $jumlahHari): int
    {
        $kolom = 0;
        for ($i = 1; $i < $jumlahHari; $i++) {
            if ($awal->addDays($i)->dayOfWeek === CarbonImmutable::MONDAY) {
                $kolom++;
            }
        }

        return $kolom + 1;
    }

    /**
     * @param  list<int>  $harian
     * @param  list<float>  $persen
     * @param  list<int>  $level
     * @return array<int, array<int, array{level:int,judul:string}>>
     */
    private function grid(CarbonImmutable $awal, array $harian, array $persen, array $level, int $total): array
    {
        $grid = array_fill(0, 7, []);
        $kolom = 0;

        foreach ($harian as $i => $jumlah) {
            $t = $awal->addDays($i);
            if ($i > 0 && $t->dayOfWeek === CarbonImmutable::MONDAY) {
                $kolom++;
            }

            $grid[($t->dayOfWeek + 6) % 7][$kolom] = [
                'level' => $level[$i],
                'judul' => $t->translatedFormat('D, j M Y')
                    .' — kepatuhan '.number_format($persen[$i], 1, ',', '.').'%'
                    .' · '.number_format($jumlah, 0, ',', '.').' dari '
                    .number_format($total, 0, ',', '.').' karyawan kena pelanggaran',
            ];
        }

        return $grid;
    }

    /**
     * Label bulan dicetak sekali di kolom pertama tiap bulan; kolom lainnya
     * dibiarkan kosong agar sumbu-x tidak berdesakan.
     *
     * @return list<string>
     */
    private function labelKolom(CarbonImmutable $awal, int $jumlahHari): array
    {
        $label = [];
        $kolom = 0;
        $bulanTerakhir = '';

        for ($i = 0; $i < $jumlahHari; $i++) {
            $t = $awal->addDays($i);
            if ($i > 0 && $t->dayOfWeek === CarbonImmutable::MONDAY) {
                $kolom++;
            }
            if (isset($label[$kolom])) {
                continue;
            }

            $bulan = $t->translatedFormat('M');
            $label[$kolom] = $bulan === $bulanTerakhir ? '' : $bulan;
            $bulanTerakhir = $bulan;
        }

        ksort($label);

        return array_values($label);
    }

    /**
     * Legenda dibangun dari rentang kepatuhan yang BENAR-BENAR muncul di tiap
     * tingkat, bukan dari rumus batasnya. Dengan begitu tidak pernah ada baris
     * legenda untuk tingkat yang tak terpakai (misalnya saat variasi datanya
     * kecil sehingga beberapa kuintil runtuh jadi satu), dan angka yang
     * tercetak selalu bisa ditemukan di sel heatmap.
     *
     * @param  list<float>  $persen
     * @param  list<int>  $level
     * @return list<array{level:int,teks:string}>
     */
    private function legenda(array $persen, array $level): array
    {
        $rentang = [];
        foreach ($level as $i => $lvl) {
            $rentang[$lvl]['min'] = min($rentang[$lvl]['min'] ?? $persen[$i], $persen[$i]);
            $rentang[$lvl]['max'] = max($rentang[$lvl]['max'] ?? $persen[$i], $persen[$i]);
        }
        ksort($rentang);

        $fmt = static fn (float $v): string => number_format($v, 1, ',', '.').'%';
        $out = [['level' => 0, 'teks' => 'Tidak ada data']];

        foreach ($rentang as $lvl => $r) {
            $out[] = [
                'level' => $lvl,
                'teks' => $fmt($r['min']) === $fmt($r['max'])
                    ? $fmt($r['min'])
                    : $fmt($r['min']).'–'.$fmt($r['max']),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $harian
     * @return array{jumlah:int,tanggal:string}
     */
    private function puncak(CarbonImmutable $awal, array $harian): array
    {
        $maks = max($harian);
        $idx = (int) array_search($maks, $harian, true);

        return [
            'jumlah' => $maks,
            'tanggal' => $awal->addDays($idx)->translatedFormat('j M Y'),
        ];
    }

    /**
     * Rata-rata pelanggaran tiap hari dalam seminggu, terurut dari yang
     * terpadat — dipakai untuk kartu metrik dan kalimat kesimpulan.
     *
     * @param  list<int>  $harian
     * @return list<array{nama:string,rata:int}>
     */
    private function perHari(CarbonImmutable $awal, array $harian): array
    {
        $jumlah = array_fill(0, 7, 0);
        $cacah = array_fill(0, 7, 0);

        foreach ($harian as $i => $v) {
            $r = ($awal->addDays($i)->dayOfWeek + 6) % 7;
            $jumlah[$r] += $v;
            $cacah[$r]++;
        }

        $out = [];
        foreach (self::HARI as $r => $nama) {
            if ($cacah[$r] > 0) {
                $out[] = ['nama' => $nama, 'rata' => (int) round($jumlah[$r] / $cacah[$r])];
            }
        }

        usort($out, static fn (array $a, array $b): int => $b['rata'] <=> $a['rata']);

        return $out;
    }

    /**
     * Selisih relatif hari terpadat vs terlonggar, 0..1.
     *
     * @param  list<array{nama:string,rata:int}>  $perHari
     */
    private function sebaranHari(array $perHari): float
    {
        if (count($perHari) < 2) {
            return 0.0;
        }

        $maks = $perHari[0]['rata'];
        $min = $perHari[count($perHari) - 1]['rata'];

        return $maks > 0 ? round(($maks - $min) / $maks, 4) : 0.0;
    }
}
