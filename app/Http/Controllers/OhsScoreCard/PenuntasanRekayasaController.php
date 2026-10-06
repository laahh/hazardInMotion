<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

/**
 * Parameter "Penuntasan pengendalian rekayasa".
 *
 * Sumbernya lead_replikasi_rekayasa_engineering. Kolom target_komitmen berupa
 * RASIO pencapaian terhadap komitmen (0 sampai 2,909), jadi dikalikan 100 dan
 * NILAINYA BISA MELEBIHI 100% -- memang begitu maksudnya.
 *
 * BAND-NYA BERBEDA SENDIRI dari parameter lain, dan justru memberi nilai
 * tertinggi pada capaian DI ATAS 100%:
 *
 *     X < 80%          -> 1        X = 100%  -> 3
 *     80% <= X < 100%  -> 2        X > 100%  -> 4
 *
 * Karena band 3 hanya tepat di 100% dan band 4 tak berbatas atas, keduanya
 * datar; yang melandai hanya band 1 dan 2.
 *
 * 71 DARI 130 BARIS KOSONG (target_komitmen NULL). Kombinasi seperti itu tidak
 * dinilai dan tampil sebagai sel kosong, bukan 0%.
 */
final class PenuntasanRekayasaController extends AbstractLeadBulananController
{
    protected function tabel(): string
    {
        return 'lead_replikasi_rekayasa_engineering';
    }

    protected function judul(): string
    {
        return 'Penuntasan Pengendalian Rekayasa';
    }

    protected function slug(): string
    {
        return 'penuntasan-pengendalian-rekayasa';
    }

    protected function penjelasan(): string
    {
        return 'Pencapaian terhadap komitmen replikasi rekayasa engineering, tiap perusahaan di tiap site';
    }

    protected function kolom(): array
    {
        return [
            'site' => 'site',
            'mitra' => 'perusahaan',
            'bulan' => 'month_name',
            'nilai' => 'target_komitmen',
        ];
    }

    protected function ekspresiPersen(): string
    {
        return 'ROUND(AVG(`target_komitmen`) * 100, 2) AS persen, '
            . 'ROUND(SUM(`target_komitmen`), 4) AS pembilang, '
            . 'COUNT(`target_komitmen`) AS penyebut';
    }

    protected function labelPecahan(): array
    {
        return ['Jumlah rasio', 'Baris berisi'];
    }

    protected function nilaiUntuk(float $persen): array
    {
        // Melebihi komitmen adalah hasil terbaik, jadi band teratas ada di atas
        // 100% dan band 3 hanya tepat di 100%.
        if ($persen > 100.0) {
            return [4.0, '>100%'];
        }

        if ($persen >= 100.0) {
            return [3.0, '100%'];
        }

        if ($persen >= 80.0) {
            return [round(min(2.0 + ($persen - 80.0) / 20.0, 2.99), 2), '80% - <100%'];
        }

        return [round(min(1.0 + $persen / 80.0, 1.99), 2), '<80%'];
    }

    protected function legendaBand(): array
    {
        return [
            ['nilai' => 1, 'label' => '<80%'],
            ['nilai' => 2, 'label' => '80-<100%'],
            ['nilai' => 3, 'label' => 'tepat 100%'],
            ['nilai' => 4, 'label' => '>100%'],
        ];
    }

    protected function target(): float
    {
        return 100.0;
    }
}
