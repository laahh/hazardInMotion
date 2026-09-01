<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use App\Models\EvaluasiWellMitraAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * CRUD & opsi dropdown assignment Mitra Kerja Evaluasi Well.
 */
final class SportEvaluationMitraAssignmentService
{
    public function __construct(
        private readonly BewellConnectionService $connection,
        private readonly SportEvaluationKaryawanWellSiteResolver $siteResolver,
        private readonly SportEvaluationCompanyAliasResolver $companyAliasResolver,
    ) {}

    /**
     * @return Collection<int, EvaluasiWellMitraAssignment>
     */
    public function listAssignments(): Collection
    {
        return EvaluasiWellMitraAssignment::query()
            ->with(['user:id,name,email', 'scopes'])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function find(int $id): ?EvaluasiWellMitraAssignment
    {
        return EvaluasiWellMitraAssignment::query()
            ->with(['user:id,name,email', 'scopes'])
            ->find($id);
    }

    public function findActiveForUser(int $userId): ?EvaluasiWellMitraAssignment
    {
        return EvaluasiWellMitraAssignment::query()
            ->with('scopes')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  array{
     *     user_id:int,
     *     is_active?:bool,
     *     scopes?:list<array{perusahaan?:string,sites?:list<string>}>,
     *     site?:string,
     *     perusahaan?:string
     * }  $data
     */
    public function create(array $data): EvaluasiWellMitraAssignment
    {
        $companies = $this->normalizeIncomingCompanies($data);
        if ($companies === []) {
            throw new InvalidArgumentException('Assignment mitra wajib punya minimal satu perusahaan dan satu site.');
        }

        return DB::transaction(function () use ($data, $companies): EvaluasiWellMitraAssignment {
            $first = $companies[0];
            $assignment = EvaluasiWellMitraAssignment::query()->create([
                'user_id' => (int) $data['user_id'],
                'site' => $first['sites'][0],
                'perusahaan' => $first['perusahaan'],
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
            $this->syncScopes($assignment, $companies);

            return $assignment->fresh(['user:id,name,email', 'scopes']) ?? $assignment;
        });
    }

    /**
     * @param  array{
     *     user_id?:int,
     *     is_active?:bool,
     *     scopes?:list<array{perusahaan?:string,sites?:list<string>}>,
     *     site?:string,
     *     perusahaan?:string
     * }  $data
     */
    public function update(EvaluasiWellMitraAssignment $assignment, array $data): EvaluasiWellMitraAssignment
    {
        $companies = array_key_exists('scopes', $data)
            ? $this->normalizeIncomingCompanies($data)
            : $assignment->groupedCompanySites();

        if ($companies === []) {
            throw new InvalidArgumentException('Assignment mitra wajib punya minimal satu perusahaan dan satu site.');
        }

        return DB::transaction(function () use ($assignment, $data, $companies): EvaluasiWellMitraAssignment {
            if (array_key_exists('user_id', $data)) {
                $assignment->user_id = (int) $data['user_id'];
            }
            if (array_key_exists('is_active', $data)) {
                $assignment->is_active = (bool) $data['is_active'];
            }

            $first = $companies[0];
            $assignment->site = $first['sites'][0];
            $assignment->perusahaan = $first['perusahaan'];
            $assignment->save();
            $this->syncScopes($assignment, $companies);

            return $assignment->fresh(['user:id,name,email', 'scopes']) ?? $assignment;
        });
    }

    public function delete(EvaluasiWellMitraAssignment $assignment): void
    {
        $assignment->delete();
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function userOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static function (User $user): array {
                $email = trim((string) $user->email);

                return [
                    'id' => (int) $user->id,
                    'label' => trim((string) $user->name).($email !== '' ? ' ('.$email.')' : ''),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{sites:list<string>,companies:list<string>}
     */
    public function filterOptions(): array
    {
        return Cache::remember('evaluasi_well:mitra_filter_options_v3', 300, function (): array {
            $sites = $this->siteResolver->distinctDedicatedSites();
            $companyList = [];

            if ($this->connection->isUp()) {
                try {
                    $db = DB::connection(BewellConnectionService::CONNECTION);

                    $fallbackSites = $db->table('employee_profiles')
                        ->whereNotNull('site')
                        ->where('site', '<>', '')
                        ->distinct()
                        ->orderBy('site')
                        ->pluck('site')
                        ->map(static fn (mixed $site): string => trim((string) $site))
                        ->filter(static fn (string $site): bool => $site !== '')
                        ->values()
                        ->all();
                    $sites = $this->siteResolver->mergeFilterSites($fallbackSites);

                    $rawCompanies = $db->table('employee_profiles')
                        ->whereNotNull('nama_perusahaan')
                        ->where('nama_perusahaan', '<>', '')
                        ->distinct()
                        ->orderBy('nama_perusahaan')
                        ->pluck('nama_perusahaan')
                        ->map(static fn (mixed $company): string => trim((string) $company))
                        ->filter(static fn (string $company): bool => $company !== '')
                        ->values()
                        ->all();

                    $this->mergeCompanyOptions($companyList, $rawCompanies);
                } catch (Throwable $e) {
                    report($e);
                }
            }

            $mitraCompanies = config('evaluasi_well_mitra_companies', []);
            if (is_array($mitraCompanies)) {
                $this->mergeCompanyOptions($companyList, $mitraCompanies);
            }

            // Fallback: daftar Minecon jika BeWell down / kosong dan config mitra kosong.
            if ($companyList === []) {
                $fallback = config('evaluasi_well_minecon_companies', []);
                if (is_array($fallback)) {
                    $this->mergeCompanyOptions($companyList, $fallback);
                }
            }

            $companies = array_keys($companyList);
            sort($companies, SORT_STRING);

            return [
                'sites' => $sites,
                'companies' => $companies,
            ];
        });
    }

    /**
     * Canonical scope: pasangan perusahaan × site (OR antar pasangan).
     *
     * @param  array<string, mixed>  $scope
     * @return array{
     *     site: string,
     *     perusahaan: string,
     *     pairs: list<array{site: string, perusahaan: string}>,
     *     companies: list<array{perusahaan: string, sites: list<string>}>
     * }
     */
    public function normalizeScope(array $scope): array
    {
        $grouped = [];

        if (isset($scope['companies']) && is_array($scope['companies'])) {
            foreach ($scope['companies'] as $companyRow) {
                if (! is_array($companyRow)) {
                    continue;
                }
                $company = trim((string) ($companyRow['perusahaan'] ?? $companyRow['company'] ?? ''));
                $sites = $companyRow['sites'] ?? [];
                if (! is_array($sites)) {
                    $sites = [$sites];
                }
                foreach ($sites as $site) {
                    $this->appendGroupedPair($grouped, $company, trim((string) $site));
                }
            }
        }

        if (isset($scope['pairs']) && is_array($scope['pairs'])) {
            foreach ($scope['pairs'] as $pair) {
                if (! is_array($pair)) {
                    continue;
                }
                $this->appendGroupedPair(
                    $grouped,
                    trim((string) ($pair['perusahaan'] ?? $pair['company'] ?? '')),
                    trim((string) ($pair['site'] ?? '')),
                );
            }
        }

        if ($grouped === []) {
            $this->appendGroupedPair(
                $grouped,
                trim((string) ($scope['perusahaan'] ?? $scope['company'] ?? '')),
                trim((string) ($scope['site'] ?? '')),
            );
        }

        $companies = [];
        $pairs = [];
        foreach ($grouped as $company => $sites) {
            $siteList = array_values($sites);
            sort($siteList, SORT_STRING);
            $companies[] = [
                'perusahaan' => $company,
                'sites' => $siteList,
            ];
            foreach ($siteList as $site) {
                $pairs[] = [
                    'site' => $site,
                    'perusahaan' => $company,
                ];
            }
        }

        usort(
            $pairs,
            static fn (array $a, array $b): int => [$a['perusahaan'], $a['site']] <=> [$b['perusahaan'], $b['site']]
        );
        usort(
            $companies,
            static fn (array $a, array $b): int => $a['perusahaan'] <=> $b['perusahaan']
        );

        $firstPair = $pairs[0] ?? ['site' => '', 'perusahaan' => ''];

        return [
            'site' => $firstPair['site'],
            'perusahaan' => $firstPair['perusahaan'],
            'pairs' => $pairs,
            'companies' => $companies,
        ];
    }

    /**
     * Apakah scope punya minimal satu dimensi pembatas.
     *
     * @param  array<string, mixed>  $scope
     */
    public function hasScope(array $scope): bool
    {
        $normalized = $this->normalizeScope($scope);

        return $normalized['pairs'] !== []
            || $normalized['site'] !== ''
            || $normalized['perusahaan'] !== '';
    }

    /**
     * Label ringkas untuk header dashboard / daftar assignment.
     *
     * @param  array<string, mixed>  $scope
     */
    public function scopeLabel(array $scope): string
    {
        $normalized = $this->normalizeScope($scope);
        if ($normalized['companies'] === []) {
            return '';
        }

        $parts = [];
        foreach ($normalized['companies'] as $company) {
            $sites = implode(', ', $company['sites']);
            $parts[] = $sites !== ''
                ? $company['perusahaan'].' ('.$sites.')'
                : $company['perusahaan'];
        }

        return implode(' · ', $parts);
    }

    /**
     * Terapkan scope site + perusahaan ke query employee_profiles (alias e).
     * Beberapa pasangan di-OR: (perusahaan A AND site ∈ sites A) OR (perusahaan B AND …).
     *
     * @param  array<string, mixed>  $scope
     */
    public function applyScopeToEmployeeQuery(Builder $query, array $scope): Builder
    {
        $normalized = $this->normalizeScope($scope);
        if (! $this->hasScope($normalized)) {
            return $query;
        }

        $companies = $normalized['companies'];
        if ($companies === []) {
            return $query;
        }

        return $query->where(function (Builder $outer) use ($companies): void {
            foreach ($companies as $index => $company) {
                $callback = function (Builder $inner) use ($company): void {
                    if ($company['perusahaan'] !== '') {
                        $this->applyCompanyFilter($inner, $company['perusahaan']);
                    }
                    $sites = $company['sites'];
                    if ($sites === []) {
                        return;
                    }
                    if (count($sites) === 1) {
                        $this->siteResolver->applySiteFilter($inner, $sites[0]);

                        return;
                    }

                    $inner->where(function (Builder $siteOuter) use ($sites): void {
                        foreach ($sites as $siteIndex => $site) {
                            if ($siteIndex === 0) {
                                $this->siteResolver->applySiteFilter($siteOuter, $site);

                                continue;
                            }
                            $siteOuter->orWhere(function (Builder $siteInner) use ($site): void {
                                $this->siteResolver->applySiteFilter($siteInner, $site);
                            });
                        }
                    });
                };

                if ($index === 0) {
                    $outer->where($callback);
                } else {
                    $outer->orWhere($callback);
                }
            }
        });
    }

    /**
     * @var array<string, list<int>|null>
     */
    private array $scopedIdsCache = [];

    /**
     * ID employee_profiles dalam scope. Null = tanpa pembatasan.
     *
     * @param  array<string, mixed>  $scope
     * @return list<int>|null
     */
    public function scopedEmployeeIds(array $scope): ?array
    {
        if (! $this->hasScope($scope)) {
            return null;
        }

        $normalized = $this->normalizeScope($scope);
        $cacheKey = $this->cacheKeySuffix($normalized);
        if (array_key_exists($cacheKey, $this->scopedIdsCache)) {
            return $this->scopedIdsCache[$cacheKey];
        }

        if (! $this->connection->isUp()) {
            $this->scopedIdsCache[$cacheKey] = [];

            return [];
        }

        try {
            $ids = [];
            foreach ($normalized['companies'] as $company) {
                $companyName = $company['perusahaan'];
                $sites = $company['sites'];
                $siteSet = [];
                foreach ($sites as $site) {
                    $siteSet[$site] = true;
                }

                $query = DB::connection(BewellConnectionService::CONNECTION)
                    ->table('employee_profiles as e')
                    ->select(['e.id', 'e.kode_sid', 'e.site']);

                if ($companyName !== '') {
                    $this->applyCompanyFilter($query, $companyName);

                    foreach ($query->get() as $row) {
                        if ($siteSet !== []) {
                            $resolved = $this->siteResolver->resolve(
                                isset($row->kode_sid) ? (string) $row->kode_sid : null,
                                isset($row->site) ? (string) $row->site : null,
                            );
                            if (! isset($siteSet[$resolved])) {
                                continue;
                            }
                        }

                        $ids[(int) $row->id] = (int) $row->id;
                    }

                    continue;
                }

                foreach ($sites as $site) {
                    $siteQuery = DB::connection(BewellConnectionService::CONNECTION)
                        ->table('employee_profiles as e')
                        ->select(['e.id']);
                    $this->siteResolver->applySiteFilter($siteQuery, $site);
                    foreach ($siteQuery->pluck('e.id') as $id) {
                        $ids[(int) $id] = (int) $id;
                    }
                }
            }

            $result = array_values($ids);
            $this->scopedIdsCache[$cacheKey] = $result;

            return $result;
        } catch (Throwable $e) {
            report($e);
            $this->scopedIdsCache[$cacheKey] = [];

            return [];
        }
    }

    /**
     * Suffix cache key aman dari scope.
     *
     * @param  array<string, mixed>  $scope
     */
    public function cacheKeySuffix(array $scope): string
    {
        if (! $this->hasScope($scope)) {
            return 'global';
        }

        $normalized = $this->normalizeScope($scope);

        return md5(json_encode([
            'pairs' => $normalized['pairs'],
        ]) ?: '');
    }

    /**
     * Payload filter dashboard/stats dari scope (termasuk companies + pairs).
     *
     * @param  array<string, mixed>  $scope
     * @return array{
     *     site: string,
     *     perusahaan: string,
     *     company: string,
     *     pairs: list<array{site: string, perusahaan: string}>,
     *     companies: list<array{perusahaan: string, sites: list<string>}>
     * }
     */
    public function toFilterPayload(array $scope): array
    {
        $normalized = $this->normalizeScope($scope);

        return [
            'site' => $normalized['site'],
            'perusahaan' => $normalized['perusahaan'],
            'company' => $normalized['perusahaan'],
            'pairs' => $normalized['pairs'],
            'companies' => $normalized['companies'],
        ];
    }

    /**
     * Decode companies/pairs dari request (array atau JSON string).
     *
     * @return list<array<string, mixed>>
     */
    public function decodeScopeCollection(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array{
     *     site: string,
     *     perusahaan: string,
     *     pairs: list<array{site: string, perusahaan: string}>,
     *     companies: list<array{perusahaan: string, sites: list<string>}>
     * }|null
     */
    public function scopeFromAssignment(?EvaluasiWellMitraAssignment $assignment): ?array
    {
        if ($assignment === null) {
            return null;
        }

        $assignment->loadMissing('scopes');
        $normalized = $this->normalizeScope([
            'companies' => $assignment->groupedCompanySites(),
        ]);

        if ($normalized['pairs'] === []) {
            return null;
        }

        return $normalized;
    }

    /**
     * Baris karyawan cocok jika salah satu pasangan assignment terpenuhi.
     *
     * @param  array<string, mixed>  $scope
     */
    public function rowMatchesScope(array $scope, string $resolvedSite, string $rawCompany): bool
    {
        if (! $this->hasScope($scope)) {
            return true;
        }

        foreach ($this->normalizeScope($scope)['pairs'] as $pair) {
            $siteOk = $pair['site'] === '' || $resolvedSite === $pair['site'];
            $companyOk = $pair['perusahaan'] === ''
                || $this->companyAliasResolver->matchesFilter($rawCompany, $pair['perusahaan']);
            if ($siteOk && $companyOk) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{perusahaan: string, sites: list<string>}>
     */
    private function normalizeIncomingCompanies(array $data): array
    {
        $scopes = $data['scopes'] ?? null;
        if (! is_array($scopes) || $scopes === []) {
            $company = $this->canonicalCompany((string) ($data['perusahaan'] ?? ''));
            $site = trim((string) ($data['site'] ?? ''));
            if ($company === '' || $site === '') {
                return [];
            }

            return [[
                'perusahaan' => $company,
                'sites' => [$site],
            ]];
        }

        $grouped = [];
        foreach ($scopes as $row) {
            if (! is_array($row)) {
                continue;
            }
            $company = $this->canonicalCompany((string) ($row['perusahaan'] ?? ''));
            if ($company === '') {
                continue;
            }
            $sites = $row['sites'] ?? [];
            if (! is_array($sites)) {
                $sites = [$sites];
            }
            foreach ($sites as $site) {
                $trimmed = trim((string) $site);
                if ($trimmed === '') {
                    continue;
                }
                $grouped[$company][$trimmed] = $trimmed;
            }
        }

        $companies = [];
        foreach ($grouped as $company => $sites) {
            $siteList = array_values($sites);
            sort($siteList, SORT_STRING);
            $companies[] = [
                'perusahaan' => $company,
                'sites' => $siteList,
            ];
        }

        return $companies;
    }

    /**
     * @param  list<array{perusahaan: string, sites: list<string>}>  $companies
     */
    private function syncScopes(EvaluasiWellMitraAssignment $assignment, array $companies): void
    {
        $assignment->scopes()->delete();

        $rows = [];
        foreach ($companies as $company) {
            foreach ($company['sites'] as $site) {
                $rows[] = [
                    'perusahaan' => $company['perusahaan'],
                    'site' => $site,
                ];
            }
        }

        if ($rows !== []) {
            $assignment->scopes()->createMany($rows);
        }
    }

    /**
     * @param  array<string, array<string, string>>  $grouped
     */
    private function appendGroupedPair(array &$grouped, string $company, string $site): void
    {
        if ($company === '' && $site === '') {
            return;
        }

        $key = $company !== '' ? $company : '';
        $grouped[$key] ??= [];
        if ($site !== '') {
            $grouped[$key][$site] = $site;
        }
    }

    /**
     * Filter employee_profiles (alias e) berdasarkan nama perusahaan + alias.
     */
    private function applyCompanyFilter(Builder $query, string $company): void
    {
        $names = $this->companyAliasResolver->matchingRawNames($company);
        if ($names === []) {
            $names = [$company];
        }
        $normalized = array_map(
            static fn (string $name): string => mb_strtoupper(trim($name)),
            $names
        );
        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        $query->whereRaw(
            'UPPER(TRIM(COALESCE(e.nama_perusahaan, \'\'))) IN ('.$placeholders.')',
            $normalized
        );
    }

    private function canonicalCompany(string $company): string
    {
        $resolved = $this->companyAliasResolver->resolve($company);

        return $resolved !== '' ? $resolved : trim($company);
    }

    /**
     * @param  array<string, true>  $companyList
     * @param  array<int|string, mixed>  $companies
     */
    private function mergeCompanyOptions(array &$companyList, array $companies): void
    {
        foreach ($companies as $company) {
            if (! is_string($company)) {
                continue;
            }

            $trimmed = trim($company);
            if ($trimmed === '') {
                continue;
            }

            $resolved = $this->companyAliasResolver->resolve($trimmed) ?: $trimmed;
            if ($resolved !== '') {
                $companyList[$resolved] = true;
            }
        }
    }
}
