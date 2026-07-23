<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

/**
 * Parser metrik manual (workout_analyses) yang tersimpan sebagai VARCHAR bebas,
 * mis. distance "5,2 km" / "800 m", workout_time "45 min" / "1:05:00".
 *
 * WARNING: format string tidak konsisten. Parser ini best-effort untuk konteks
 * per karyawan; agregasi utama dashboard TETAP memakai strava_activities
 * (numerik) + daily_health_scores agar akurat.
 */
final class WorkoutMetricParser
{
    /**
     * Ubah string jarak menjadi meter. Mengembalikan null bila tak terbaca.
     */
    public function distanceToMeters(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $text = strtolower(trim($raw));
        if ($text === '') {
            return null;
        }

        if (! preg_match('/([0-9]+(?:[.,][0-9]+)?)/', $text, $m)) {
            return null;
        }

        $value = (float) str_replace(',', '.', $m[1]);

        // Deteksi satuan; default asumsikan kilometer bila hanya angka + "k".
        if (str_contains($text, 'km') || preg_match('/\bk\b/', $text)) {
            return $value * 1000;
        }

        if (str_contains($text, 'mi')) {
            return $value * 1609.344;
        }

        if (str_contains($text, 'm')) {
            return $value;
        }

        // Tanpa satuan: nilai kecil kemungkinan km, nilai besar kemungkinan meter.
        return $value <= 100 ? $value * 1000 : $value;
    }

    /**
     * Ubah string durasi menjadi detik. Mendukung "HH:MM:SS", "MM:SS",
     * "45 min", "1 jam 5 menit", "90m". Mengembalikan null bila tak terbaca.
     */
    public function durationToSeconds(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }

        $text = strtolower(trim($raw));
        if ($text === '') {
            return null;
        }

        // Format jam:menit:detik.
        if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $text, $m)) {
            if (isset($m[3]) && $m[3] !== '') {
                return ((int) $m[1] * 3600) + ((int) $m[2] * 60) + (int) $m[3];
            }

            return ((int) $m[1] * 60) + (int) $m[2];
        }

        $seconds = 0;
        $matched = false;

        if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*(?:jam|hour|hr|h)\b/', $text, $m)) {
            $seconds += (int) round((float) str_replace(',', '.', $m[1]) * 3600);
            $matched = true;
        }

        if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*(?:menit|min|m)\b/', $text, $m)) {
            $seconds += (int) round((float) str_replace(',', '.', $m[1]) * 60);
            $matched = true;
        }

        if (preg_match('/([0-9]+)\s*(?:detik|sec|s)\b/', $text, $m)) {
            $seconds += (int) $m[1];
            $matched = true;
        }

        if ($matched) {
            return $seconds;
        }

        // Hanya angka polos: asumsikan menit.
        if (preg_match('/^([0-9]+(?:[.,][0-9]+)?)$/', $text, $m)) {
            return (int) round((float) str_replace(',', '.', $m[1]) * 60);
        }

        return null;
    }
}
