<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Database\Query\Builder;

/**
 * Aturan exclude tambahan (di luar status_karyawan = AKTIF) untuk populasi
 * "karyawan" di dashboard Evaluasi Well: jabatan Presiden Direktur, Direktur
 * tanpa site/site HO, site Jakarta & Poltek, dan nama dummy/testing.
 */
final class SportEvaluationEmployeeExclusionRules
{
    /** @var list<string> */
    public const EXCLUDED_JABATAN_FUNGSIONAL = ['VISITOR', 'PRESIDEN DIREKTUR'];

    public const DIREKTUR_JABATAN = 'DIREKTUR';

    public const DIREKTUR_EXCLUDED_SITE = 'HO';

    /** @var list<string> Site yang di-resolve (site_dedicated / fallback e.site). */
    public const EXCLUDED_SITES = ['JAKARTA', 'POLTEK'];

    public const DUMMY_NAME_LIKE = '%DUMMY%';

    public function __construct(
        private readonly SportEvaluationKaryawanWellSiteResolver $siteResolver,
    ) {}

    /**
     * Terapkan exclude jabatan (Presiden Direktur, Direktur tanpa site/HO),
     * nama dummy, dan site Jakarta/Poltek ke builder ber-alias 'e'.
     */
    public function applyToQuery(Builder $query): Builder
    {
        $query
            ->whereRaw("UPPER(TRIM(COALESCE(e.jabatan_fungsional, ''))) NOT IN ('VISITOR', 'PRESIDEN DIREKTUR')")
            ->whereRaw("NOT (
                UPPER(TRIM(COALESCE(e.jabatan_fungsional, ''))) = 'DIREKTUR'
                AND (TRIM(COALESCE(e.site, '')) = '' OR UPPER(TRIM(e.site)) = 'HO')
            )")
            ->where(function (Builder $q): void {
                $q->whereNull('e.nama')
                    ->orWhereRaw("UPPER(e.nama) NOT LIKE ?", [self::DUMMY_NAME_LIKE]);
            });

        foreach (self::EXCLUDED_SITES as $site) {
            $query->whereNot(function (Builder $q) use ($site): void {
                $this->siteResolver->applySiteFilter($q, $site);
            });
        }

        return $query;
    }

    /**
     * Untuk konsumen yang sudah punya resolved site di PHP (mis. hasil
     * agregasi SportEvaluationInstallStatsService) — cek tanpa query builder.
     */
    public function isExcludedJabatanFungsional(string $jabatanFungsional): bool
    {
        return in_array(mb_strtoupper(trim($jabatanFungsional)), self::EXCLUDED_JABATAN_FUNGSIONAL, true);
    }

    public function isExcludedDirekturSite(string $jabatanFungsional, ?string $rawSite): bool
    {
        if (mb_strtoupper(trim($jabatanFungsional)) !== self::DIREKTUR_JABATAN) {
            return false;
        }

        $site = mb_strtoupper(trim((string) $rawSite));

        return $site === '' || $site === self::DIREKTUR_EXCLUDED_SITE;
    }

    public function isDummyName(?string $nama): bool
    {
        return mb_stripos((string) $nama, 'dummy') !== false;
    }

    public function isExcludedResolvedSite(string $resolvedSite): bool
    {
        return in_array(mb_strtoupper(trim($resolvedSite)), self::EXCLUDED_SITES, true);
    }

    /**
     * @param  array{jabatan_fungsional?:string|null, site?:string|null, resolved_site?:string|null, nama?:string|null}  $row
     */
    public function isExcludedRow(array $row): bool
    {
        $jabatan = (string) ($row['jabatan_fungsional'] ?? '');

        if ($this->isExcludedJabatanFungsional($jabatan)) {
            return true;
        }

        if ($this->isExcludedDirekturSite($jabatan, $row['site'] ?? null)) {
            return true;
        }

        if ($this->isExcludedResolvedSite((string) ($row['resolved_site'] ?? ''))) {
            return true;
        }

        return $this->isDummyName($row['nama'] ?? null);
    }
}
