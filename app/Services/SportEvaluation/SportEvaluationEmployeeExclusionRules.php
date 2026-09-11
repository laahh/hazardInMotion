<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Database\Query\Builder;

/**
 * Aturan exclude tambahan (di luar status_karyawan = AKTIF) untuk populasi
 * "karyawan" di dashboard Evaluasi Well: jabatan Presiden Direktur, Direktur
 * tanpa site/site HO, site Jakarta & Poltek, perusahaan di luar hitungan
 * persentase Performance (Politeknik Sinarmas, Sinarmas Maritim, Fusi),
 * dan nama dummy/testing.
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

    /**
     * Compact key lama (huruf besar, tanpa spasi/titik/strip) — tetap ada
     * supaya konsumen yang baca konstanta ini tidak pecah.
     */
    public const EXCLUDED_COMPANY_COMPACT = 'POLITEKNIKSINARMASBERAUCOAL';

    public const DUMMY_NAME_LIKE = '%DUMMY%';

    /**
     * Terapkan exclude jabatan (Presiden Direktur, Direktur tanpa site/HO),
     * site Jakarta/Poltek, perusahaan Performance yang dikecualikan,
     * dan nama dummy ke builder ber-alias 'e'.
     */
    public function applyToQuery(Builder $query): Builder
    {
        [$companySql, $companyBindings] = $this->companyNotExcludedPredicate('e');

        return $query
            ->whereRaw("UPPER(TRIM(COALESCE(e.jabatan_fungsional, ''))) NOT IN ('VISITOR', 'PRESIDEN DIREKTUR')")
            ->whereRaw("NOT (
                UPPER(TRIM(COALESCE(e.jabatan_fungsional, ''))) = 'DIREKTUR'
                AND (TRIM(COALESCE(e.site, '')) = '' OR UPPER(TRIM(e.site)) = 'HO')
            )")
            ->whereRaw("UPPER(TRIM(COALESCE(e.site, ''))) NOT IN ('JAKARTA', 'POLTEK')")
            ->whereRaw($companySql, $companyBindings)
            ->where(function (Builder $q): void {
                $q->whereNull('e.nama')
                    ->orWhereRaw('UPPER(e.nama) NOT LIKE ?', [self::DUMMY_NAME_LIKE]);
            });
    }

    /**
     * Predikat SQL: nama_perusahaan bukan Politeknik Sinarmas, Sinarmas Maritim, atau Fusi.
     *
     * @return array{0: string, 1: list<string>}
     */
    public function companyNotExcludedPredicate(string $alias = 'e'): array
    {
        $compact = $this->companyCompactSqlExpr($alias);

        return [
            "NOT (
                {$compact} LIKE ?
                OR {$compact} LIKE ?
                OR {$compact} LIKE ?
                OR {$compact} LIKE ?
                OR {$compact} LIKE ?
            )",
            [
                '%POLITEKNIKSINARMAS%',
                '%SINARMAS%MARITIM%',
                '%SINARMAS%MARITIN%',
                'FUSI%',
                'PTFUSI%',
            ],
        ];
    }

    public function isExcludedCompany(?string $company): bool
    {
        $compact = $this->compactCompanyName((string) $company);
        if ($compact === '') {
            return false;
        }

        if (str_contains($compact, 'POLITEKNIKSINARMAS')) {
            return true;
        }

        if (str_contains($compact, 'SINARMAS')
            && (str_contains($compact, 'MARITIM') || str_contains($compact, 'MARITIN'))
        ) {
            return true;
        }

        return str_starts_with($compact, 'FUSI') || str_starts_with($compact, 'PTFUSI');
    }

    public function compactCompanyName(string $company): string
    {
        $upper = mb_strtoupper(trim($company));

        return str_replace([' ', '.', ',', '-'], '', $upper);
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
     * @param  array{
     *     jabatan_fungsional?:string|null,
     *     site?:string|null,
     *     nama?:string|null,
     *     nama_perusahaan?:string|null,
     *     company?:string|null
     * }  $row
     */
    public function isExcludedRow(array $row): bool
    {
        $jabatan = (string) ($row['jabatan_fungsional'] ?? '');
        $site = $row['site'] ?? null;
        $company = $row['nama_perusahaan'] ?? $row['company'] ?? null;

        if ($this->isExcludedJabatanFungsional($jabatan)) {
            return true;
        }

        if ($this->isExcludedDirekturSite($jabatan, $site)) {
            return true;
        }

        if ($this->isExcludedSite($site)) {
            return true;
        }

        if ($this->isExcludedCompany(is_string($company) ? $company : null)) {
            return true;
        }

        return $this->isDummyName($row['nama'] ?? null);
    }

    private function companyCompactSqlExpr(string $alias): string
    {
        $column = $this->safeAlias($alias).'.nama_perusahaan';

        return "REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE({$column}, ''))), ' ', ''), '.', ''), '-', '')";
    }

    private function safeAlias(string $alias): string
    {
        $trimmed = trim($alias);

        return $trimmed !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $trimmed) === 1
            ? $trimmed
            : 'e';
    }
}
