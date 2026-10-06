<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

/**
 * Parameter "Pelaksanaan Sobriety Test Jam Kritis dan Pengecekan Sobriety Test".
 *
 * Sumbernya lead_sobriety_test: 117 baris, satu per site, perusahaan, dan
 * bulan. Kolom pengisian_aggregator SUDAH BERUPA RASIO 0-1 (0,966244726
 * berarti 96,62%), jadi dikalikan 100 -- bukan dibaca sebagai persen mentah.
 *
 * Band resmi: <96% -> 1, 96-<98% -> 2, 98-<100% -> 3, tepat 100% -> 4.
 */
final class SobrietyTestController extends AbstractLeadBulananController
{
    protected function tabel(): string
    {
        return 'lead_sobriety_test';
    }

    protected function judul(): string
    {
        return 'Pelaksanaan Sobriety Test';
    }

    protected function slug(): string
    {
        return 'sobriety-test';
    }

    protected function penjelasan(): string
    {
        return 'Persentase pengisian sobriety test jam kritis, tiap perusahaan di tiap site';
    }

    protected function kolom(): array
    {
        return [
            'site' => 'site_dedicated',
            'mitra' => 'nama_perusahaan',
            'bulan' => 'month_name',
            'nilai' => 'pengisian_aggregator',
        ];
    }

    protected function ekspresiPersen(): string
    {
        return 'ROUND(AVG(`pengisian_aggregator`) * 100, 2) AS persen, '
            . 'ROUND(SUM(`pengisian_aggregator`), 4) AS pembilang, '
            . 'COUNT(`pengisian_aggregator`) AS penyebut';
    }

    protected function labelPecahan(): array
    {
        return ['Jumlah rasio', 'Baris'];
    }

    protected function nilaiUntuk(float $persen): array
    {
        // Band teratas hanya tercapai tepat di 100%, jadi band 98-100 melandai
        // sampai 3,99 dan tidak pernah menyentuh 4.
        if ($persen >= 100.0) {
            return [4.0, '100%'];
        }

        if ($persen >= 98.0) {
            return [round(min(3.0 + ($persen - 98.0) / 2.0, 3.99), 2), '98% - <100%'];
        }

        if ($persen >= 96.0) {
            return [round(min(2.0 + ($persen - 96.0) / 2.0, 2.99), 2), '96% - <98%'];
        }

        return [round(min(1.0 + $persen / 96.0, 1.99), 2), '<96%'];
    }

    protected function legendaBand(): array
    {
        return [
            ['nilai' => 1, 'label' => '<96%'],
            ['nilai' => 2, 'label' => '96-<98%'],
            ['nilai' => 3, 'label' => '98-<100%'],
            ['nilai' => 4, 'label' => 'tepat 100%'],
        ];
    }

    protected function target(): float
    {
        return 100.0;
    }
}
