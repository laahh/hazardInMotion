<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use App\Models\EvaluasiWellMitraAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            ->with(['user:id,name,email'])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function find(int $id): ?EvaluasiWellMitraAssignment
    {
        return EvaluasiWellMitraAssignment::query()
            ->with(['user:id,name,email'])
            ->find($id);
    }

    public function findActiveForUser(int $userId): ?EvaluasiWellMitraAssignment
    {
        return EvaluasiWellMitraAssignment::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  array{user_id:int,site:string,perusahaan:string,is_active?:bool}  $data
     */
    public function create(array $data): EvaluasiWellMitraAssignment
    {
        return EvaluasiWellMitraAssignment::query()->create([
            'user_id' => (int) $data['user_id'],
            'site' => trim((string) $data['site']),
            'perusahaan' => $this->canonicalCompany((string) $data['perusahaan']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    /**
     * @param  array{user_id?:int,site?:string,perusahaan?:string,is_active?:bool}  $data
     */
    public function update(EvaluasiWellMitraAssignment $assignment, array $data): EvaluasiWellMitraAssignment
    {
        if (array_key_exists('user_id', $data)) {
            $assignment->user_id = (int) $data['user_id'];
        }
        if (array_key_exists('site', $data)) {
            $assignment->site = trim((string) $data['site']);
        }
        if (array_key_exists('perusahaan', $data)) {
            $assignment->perusahaan = $this->canonicalCompany((string) $data['perusahaan']);
        }
        if (array_key_exists('is_active', $data)) {
            $assignment->is_active = (bool) $data['is_active'];
        }
        $assignment->save();

        return $assignment->fresh(['user:id,name,email']) ?? $assignment;
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
        return Cache::remember('evaluasi_well:mitra_filter_options_v2', 300, function (): array {
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

                    foreach ($rawCompanies as $company) {
                        $resolved = $this->companyAliasResolver->resolve($company) ?: $company;
                        if ($resolved !== '') {
                            $companyList[$resolved] = true;
                        }
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            }

            // Fallback: daftar Minecon jika BeWell down / kosong.
            if ($companyList === []) {
                $fallback = config('evaluasi_well_minecon_companies', []);
                if (is_array($fallback)) {
                    foreach ($fallback as $company) {
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

            $companies = array_keys($companyList);
            sort($companies, SORT_STRING);

            return [
                'sites' => $sites,
                'companies' => $companies,
            ];
        });
    }

    /**
     * Apakah scope punya minimal satu dimensi pembatas.
     *
     * @param  array{site?:string,perusahaan?:string,company?:string}  $scope
     */
    public function hasScope(array $scope): bool
    {
        $site = trim((string) ($scope['site'] ?? ''));
        $company = trim((string) ($scope['perusahaan'] ?? $scope['company'] ?? ''));

        return $site !== '' || $company !== '';
    }

    /**
     * Terapkan scope site + perusahaan ke query employee_profiles (alias e).
     *
     * @param  array{site?:string,perusahaan?:string,company?:string}  $scope
     */
    public function applyScopeToEmployeeQuery(Builder $query, array $scope): Builder
    {
        $site = trim((string) ($scope['site'] ?? ''));
        $company = trim((string) ($scope['perusahaan'] ?? $scope['company'] ?? ''));

        if ($site !== '') {
            $this->siteResolver->applySiteFilter($query, $site);
        }

        if ($company !== '') {
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

        return $query;
    }

    /**
     * @var array<string, list<int>|null>
     */
    private array $scopedIdsCache = [];

    /**
     * ID employee_profiles dalam scope. Null = tanpa pembatasan.
     *
     * @param  array{site?:string,perusahaan?:string,company?:string}  $scope
     * @return list<int>|null
     */
    public function scopedEmployeeIds(array $scope): ?array
    {
        if (! $this->hasScope($scope)) {
            return null;
        }

        $cacheKey = $this->cacheKeySuffix($scope);
        if (array_key_exists($cacheKey, $this->scopedIdsCache)) {
            return $this->scopedIdsCache[$cacheKey];
        }

        if (! $this->connection->isUp()) {
            $this->scopedIdsCache[$cacheKey] = [];

            return [];
        }

        try {
            $query = DB::connection(BewellConnectionService::CONNECTION)
                ->table('employee_profiles as e')
                ->select('e.id');

            $this->applyScopeToEmployeeQuery($query, $scope);

            $ids = $query
                ->pluck('e.id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            $this->scopedIdsCache[$cacheKey] = $ids;

            return $ids;
        } catch (Throwable $e) {
            report($e);
            $this->scopedIdsCache[$cacheKey] = [];

            return [];
        }
    }

    /**
     * Suffix cache key aman dari scope.
     *
     * @param  array{site?:string,perusahaan?:string,company?:string}  $scope
     */
    public function cacheKeySuffix(array $scope): string
    {
        if (! $this->hasScope($scope)) {
            return 'global';
        }

        return md5(json_encode([
            'site' => trim((string) ($scope['site'] ?? '')),
            'perusahaan' => trim((string) ($scope['perusahaan'] ?? $scope['company'] ?? '')),
        ]) ?: '');
    }

    /**
     * @return array{site:string,perusahaan:string}|null
     */
    public function scopeFromAssignment(?EvaluasiWellMitraAssignment $assignment): ?array
    {
        if ($assignment === null) {
            return null;
        }

        $site = trim((string) $assignment->site);
        $perusahaan = trim((string) $assignment->perusahaan);
        if ($site === '' || $perusahaan === '') {
            return null;
        }

        return [
            'site' => $site,
            'perusahaan' => $perusahaan,
        ];
    }

    private function canonicalCompany(string $company): string
    {
        $resolved = $this->companyAliasResolver->resolve($company);

        return $resolved !== '' ? $resolved : trim($company);
    }
}
