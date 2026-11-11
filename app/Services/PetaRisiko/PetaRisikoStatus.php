<?php

namespace App\Services\PetaRisiko;

/**
 * Klasifikasi status tema bahaya sesuai kesepakatan review 11 Nov 2026:
 * - gap     : ada temuan SAP tapi masih open atau berulang → tindak lanjut
 * - tindak  : belum ada temuan SAP DAN tidak ada padanan pengendalian HIRA → harus dikendalikan
 * - fyi     : belum ada temuan SAP, tapi pengendalian HIRA sudah ada → informasi saja, tidak dicari-cari
 * - ok      : ada temuan SAP dan sudah terverifikasi (closed, tidak berulang)
 */
final class PetaRisikoStatus
{
    public const GAP = 'gap';

    public const TINDAK = 'tindak';

    public const FYI = 'fyi';

    public const OK = 'ok';

    /** Urutan keparahan: makin kecil makin perlu perhatian. */
    public const URUTAN = [self::GAP => 0, self::TINDAK => 1, self::FYI => 2, self::OK => 3];

    /**
     * @return array{0:string, 1:string} [status, alasan]
     */
    public static function dari(string $statusBuktiSap, string $padananHira, bool $adaKontrolHira): array
    {
        $bukti = strtolower(trim($statusBuktiSap));
        $adaPadanan = str_starts_with(strtolower(trim($padananHira)), 'ya');

        return match (true) {
            str_contains($bukti, 'berulang') => [self::GAP, 'berulang'],
            str_contains($bukti, 'terbuka') => [self::GAP, 'open'],
            str_starts_with($bukti, 'terverifikasi') => [self::OK, ''],
            $adaPadanan && $adaKontrolHira => [self::FYI, ''],
            default => [self::TINDAK, $adaPadanan ? 'tanpa-kontrol' : 'tanpa-padanan'],
        };
    }

    /** Status terburuk dari beberapa status (mis. satu tema di beberapa lokasi). */
    public static function terburuk(string ...$status): string
    {
        $hasil = self::OK;
        foreach ($status as $s) {
            if ((self::URUTAN[$s] ?? 99) < self::URUTAN[$hasil]) {
                $hasil = $s;
            }
        }

        return $hasil;
    }

    /** Level risiko teks → 1..4 (0 bila kosong/tidak dikenal). */
    public static function level(string $teks): int
    {
        return match (strtolower(trim($teks))) {
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'significant' => 4,
            default => 0,
        };
    }
}
