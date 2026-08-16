<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

/**
 * Fase 2 (Saat Operasi) — Matriks Fit-to-Continue: status kelayakan operator
 * yang DINAMIS selama shift, gabungan hasil Fatigue Test pagi x kondisi alert
 * DMS real-time. Lihat docs/pra-operasi-safety-framework.md §3.2 untuk
 * penjelasan matriks lengkap.
 *
 * Beda dengan PraOperasiRiskScoreService (prediktif, dipakai SEBELUM shift):
 * ini murni membaca KEJADIAN yang sudah terjadi hari itu, tanpa riwayat 30 hari.
 */
final class PraOperasiFitToContinueService
{
    private const REPEAT_THRESHOLD = 2;

    public const STATUS_FIT = 'fit';

    public const STATUS_FIT_PANTAU = 'fit_pantau';

    public const STATUS_PERLU_PERHATIAN = 'perlu_perhatian';

    public const STATUS_TARIK = 'tarik';

    /**
     * Matriks: [tier FT pagi][kategori alert] => status.
     * Baris "merah" seluruhnya "tarik" — lihat catatan isRedFlagProcess().
     */
    private const MATRIX = [
        'hijau' => [
            'belum' => self::STATUS_FIT,
            'belum_diperiksa' => self::STATUS_FIT_PANTAU,
            'nyata_1' => self::STATUS_PERLU_PERHATIAN,
            'nyata_berulang' => self::STATUS_TARIK,
        ],
        'kuning' => [
            'belum' => self::STATUS_PERLU_PERHATIAN,
            'belum_diperiksa' => self::STATUS_PERLU_PERHATIAN,
            'nyata_1' => self::STATUS_TARIK,
            'nyata_berulang' => self::STATUS_TARIK,
        ],
        'merah' => [
            'belum' => self::STATUS_TARIK,
            'belum_diperiksa' => self::STATUS_TARIK,
            'nyata_1' => self::STATUS_TARIK,
            'nyata_berulang' => self::STATUS_TARIK,
        ],
    ];

    /**
     * @param  array{nyata:int, palsu:int, belum:int}  $alert
     */
    public function alertCategory(array $alert): string
    {
        if ($alert['nyata'] >= self::REPEAT_THRESHOLD) {
            return 'nyata_berulang';
        }
        if ($alert['nyata'] >= 1) {
            return 'nyata_1';
        }
        if ($alert['belum'] >= 1) {
            return 'belum_diperiksa';
        }

        return 'belum';
    }

    /**
     * @param  string|null  $fatigueTier  null = Fatigue Test belum dilakukan (diperlakukan seperti Merah — belum ada dasar untuk menyatakan fit)
     * @param  array{nyata:int, palsu:int, belum:int}  $alert
     */
    public function status(?string $fatigueTier, array $alert): string
    {
        $tier = $fatigueTier ?? 'merah';
        $category = $this->alertCategory($alert);

        return self::MATRIX[$tier][$category] ?? self::STATUS_TARIK;
    }

    /**
     * Red flag PROSES (bukan risiko orang): operator dengan Fatigue Test tier
     * Merah (atau belum dilakukan sama sekali) tapi tetap tercatat beroperasi —
     * artinya kontrol Pra Operasi seharusnya sudah mencegat ini pagi tadi.
     */
    public function isRedFlagProcess(?string $fatigueTier): bool
    {
        return $fatigueTier === 'merah' || $fatigueTier === null;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_FIT => 'Fit',
            self::STATUS_FIT_PANTAU => 'Fit (Pantau)',
            self::STATUS_PERLU_PERHATIAN => 'Perlu Perhatian',
            self::STATUS_TARIK => 'Tarik dari Unit',
            default => 'Tidak Diketahui',
        };
    }
}
