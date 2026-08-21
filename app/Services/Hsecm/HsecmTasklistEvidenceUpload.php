<?php

declare(strict_types=1);

namespace App\Services\Hsecm;

use Illuminate\Http\UploadedFile;

final class HsecmTasklistEvidenceUpload
{
    public static function appMaxKilobytes(): int
    {
        try {
            $maxKb = (int) config('hsecm.tasklist_evidence.max_kb', 10240);
        } catch (\Throwable) {
            $maxKb = 10240;
        }

        return $maxKb > 0 ? $maxKb : 10240;
    }

    public static function appMaxBytes(): int
    {
        return self::appMaxKilobytes() * 1024;
    }

    /**
     * Batas efektif: min(app 10 MB, PHP upload_max_filesize, PHP post_max_size).
     */
    public static function phpLimitBytes(): int
    {
        $upload = self::parseIniSize((string) ini_get('upload_max_filesize'));
        $post = self::parseIniSize((string) ini_get('post_max_size'));
        $limits = array_values(array_filter(
            [$upload, $post],
            static fn (int $bytes): bool => $bytes > 0
        ));

        return $limits === [] ? 0 : min($limits);
    }

    public static function phpLimitIsBelowAppMax(): bool
    {
        $phpLimit = self::phpLimitBytes();

        return $phpLimit > 0 && $phpLimit < self::appMaxBytes();
    }

    public static function formatMegabytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0';
        }

        $mb = $bytes / 1024 / 1024;
        $rounded = round($mb, 1);

        return fmod($rounded, 1.0) === 0.0
            ? (string) (int) $rounded
            : (string) $rounded;
    }

    public static function parseIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        return (int) ini_parse_quantity($value);
    }

    public static function errorMessage(UploadedFile $file): string
    {
        $phpMb = self::formatMegabytes(self::phpLimitBytes());

        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File terlalu besar untuk batas upload server saat ini ('
                .$phpMb
                .' MB). Form mengizinkan 10 MB, tetapi PHP memotong file sebelum validasi. Kompres file atau minta admin menaikkan upload_max_filesize, post_max_size, dan nginx client_max_body_size (minimal 16M).',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian. Silakan coba lagi.',
            UPLOAD_ERR_NO_FILE => 'Upload satu file evidence untuk semua item yang dipilih.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Server tidak dapat menerima file saat ini. Hubungi admin.',
            default => 'Gagal mengupload evidence. Pastikan ukuran maks. 10 MB dan koneksi stabil, lalu coba lagi.',
        };
    }

    public static function postMaxExceededMessage(): string
    {
        $phpMb = self::formatMegabytes(self::phpLimitBytes());

        return 'Ukuran unggahan melebihi batas server (saat ini '
            .$phpMb
            .' MB). File 7 MB akan gagal jika PHP post_max_size/upload_max_filesize lebih kecil dari 10 MB.';
    }
}
