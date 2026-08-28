<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Database\Query\Builder;

/**
 * Aturan exclude tambahan (di luar status_karyawan = AKTIF) untuk populasi
 * "karyawan" di dashboard Evaluasi Well: jabatan Presiden Direktur, Direktur
 * tanpa site/site HO, site Jakarta & Poltek, dan nama dummy/testing.
 *
 * Semua kondisi sengaja dicek dari kolom mentah employee_profiles (bukan
 * resolved site dari karyawan_well) supaya tetap 1 WHERE ringan — join/lookup
 * silang tabel di sini pernah bikin query ke bewell_db (via tunnel) timeout.
 */
final class SportEvaluationEmployeeExclusionRules
{
    /** @var list<string> */
    public const EXCLUDED_JABATAN_FUNGSIONAL = ['VISITOR', 'PRESIDEN DIREKTUR'];

    public const DIREKTUR_JABATAN = 'DIREKTUR';

    public const DIREKTUR_EXCLUDED_SITE = 'HO';

    /** @var list<string> */
    public const EXCLUDED_SITES = ['JAKARTA', 'POLTEK'];

    public const DUMMY_NAME_LIKE = '%DUMMY%';

    /**
     * Terapkan exclude jabatan (Presiden Direktur, Direktur tanpa site/HO),
     * site Jakarta/Poltek, dan nama dummy ke builder ber-alias 'e'.
     */
    public function applyToQuery(Builder $query): Builder
    {
        return $query
            ->whereRaw("UPPER(TRIM(COALESCE(e.jabatan_fungsional, ''))) NOT IN ('VISITOR', 'PRESIDEN DIREKTUR')")
            ->whereRaw("NOT (
                UPPER(TRIM(COALESCE(e.jabatan_fungsional, ''))) = 'DIREKTUR'
                AND (TRIM(COALESCE(e.site, '')) = '' OR UPPER(TRIM(e.site)) = 'HO')
            )")
            ->whereRaw("UPPER(TRIM(COALESCE(e.site, ''))) NOT IN ('JAKARTA', 'POLTEK')")
            ->where(function (Builder $q): void {
                $q->whereNull('e.nama')
                    ->orWhereRaw('UPPER(e.nama) NOT LIKE ?', [self::DUMMY_NAME_LIKE]);
            });
    }

    /**
     * Versi PHP (array in-memory) dari aturan yang sama, untuk konsumen yang
     * sudah pegang baris employee_profiles hasil query terpisah.
     */
    public function isExcludedJabatanFungsional(string $jabatanFungsional): bool
    {
        return in_array(mb_strtoupper(trim($jabatanFungsional)), self::EXCLUDED_JABATAN_FUNGSIONAL, true);
    }

    public function isExcludedDirekturSite(string $jabatanFungsional, ?string $site): bool
    {
        if (mb_strtoupper(trim($jabatanFungsional)) !== self::DIREKTUR_JABATAN) {
            return false;
        }

        $siteUpper = mb_strtoupper(trim((string) $site));

        return $siteUpper === '' || $siteUpper === self::DIREKTUR_EXCLUDED_SITE;
    }

    public function isExcludedSite(?string $site): bool
    {
        return in_array(mb_strtoupper(trim((string) $site)), self::EXCLUDED_SITES, true);
    }

    public function isDummyName(?string $nama): bool
    {
        return mb_stripos((string) $nama, 'dummy') !== false;
    }

    /**
     * @param  array{jabatan_fungsional?:string|null, site?:string|null, nama?:string|null}  $row
     */
    public function isExcludedRow(array $row): bool
    {
        $jabatan = (string) ($row['jabatan_fungsional'] ?? '');
        $site = $row['site'] ?? null;

        if ($this->isExcludedJabatanFungsional($jabatan)) {
            return true;
        }

        if ($this->isExcludedDirekturSite($jabatan, $site)) {
            return true;
        }

        if ($this->isExcludedSite($site)) {
            return true;
        }

        return $this->isDummyName($row['nama'] ?? null);
    }
}
