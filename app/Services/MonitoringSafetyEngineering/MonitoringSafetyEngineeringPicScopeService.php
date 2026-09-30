<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringPicAssignment;
use App\Models\MonitoringSafetyEngineeringRecord;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Resolusi scope akses (perusahaan + site) seorang user pada Monitoring Safety Engineering.
 *
 * Sumber utama: tabel assignment `monitoring_safety_engineering_pic_assignments` yang dikelola
 * lewat menu Role Akses. Bila tabel belum ada atau user belum punya assignment, service jatuh
 * kembali ke file legacy NAMA_PIC.json supaya perilaku lama tetap jalan.
 */
final class MonitoringSafetyEngineeringPicScopeService
{
    /** @var list<array{site: string, perusahaan: string, nama: string, sid: string}>|null */
    private ?array $legacyEntries = null;

    /** @var array<string, array<string, mixed>> */
    private array $resolved = [];

    /**
     * @return array{
     *     scoped: bool,
     *     source: string,
     *     nama: ?string,
     *     sid: ?string,
     *     pairs: list<array{site: string, perusahaan: string}>,
     *     sites: list<string>,
     *     companies: list<string>,
     *     all_site_companies: list<string>,
     *     lock_site: bool,
     *     lock_perusahaan: bool,
     *     all_sites: bool
     * }
     */
    public function forCurrentUser(): array
    {
        $user = Auth::user();

        return $this->forUser($user instanceof User ? $user : null);
    }

    /**
     * @return array{
     *     scoped: bool,
     *     source: string,
     *     nama: ?string,
     *     sid: ?string,
     *     pairs: list<array{site: string, perusahaan: string}>,
     *     sites: list<string>,
     *     companies: list<string>,
     *     all_site_companies: list<string>,
     *     lock_site: bool,
     *     lock_perusahaan: bool,
     *     all_sites: bool
     * }
     */
    public function forUser(?User $user): array
    {
        $cacheKey = $user === null ? 'guest' : 'user:' . $user->getKey();

        if (array_key_exists($cacheKey, $this->resolved)) {
            /** @var array{scoped: bool, source: string, nama: ?string, sid: ?string, pairs: list<array{site: string, perusahaan: string}>, sites: list<string>, companies: list<string>, all_site_companies: list<string>, lock_site: bool, lock_perusahaan: bool, all_sites: bool} */
            return $this->resolved[$cacheKey];
        }

        return $this->resolved[$cacheKey] = $this->resolveFor($user);
    }

    /**
     * Buang memo scope (dipanggil setelah assignment diubah).
     */
    public function flushCache(): void
    {
        $this->resolved = [];
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  array<string, mixed>  $scope
     */
    public function applyToQuery(object $query, array $scope): void
    {
        if (! ($scope['scoped'] ?? false)) {
            return;
        }

        $pairs = $scope['pairs'] ?? [];
        $allSiteCompanies = $scope['all_site_companies'] ?? [];

        if ($pairs === [] && $allSiteCompanies === []) {
            // Sudah discope tapi tidak memegang apa pun: jangan bocorkan data.
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (object $inner) use ($pairs, $allSiteCompanies): void {
            if ($allSiteCompanies !== []) {
                $inner->whereIn('perusahaan', $allSiteCompanies);
            }

            foreach ($pairs as $pair) {
                $inner->orWhere(function (object $pairQuery) use ($pair): void {
                    $pairQuery->where('site', $pair['site'])->where('perusahaan', $pair['perusahaan']);
                });
            }
        });
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function allowsRecord(MonitoringSafetyEngineeringRecord $record, array $scope): bool
    {
        return $this->allowsPair(
            (string) ($record->site ?? ''),
            (string) ($record->perusahaan ?? ''),
            $scope,
        );
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    public function allowsPair(string $site, string $perusahaan, array $scope): bool
    {
        if (! ($scope['scoped'] ?? false)) {
            return true;
        }

        $site = trim($site);
        $perusahaan = trim($perusahaan);

        if (in_array($perusahaan, $scope['all_site_companies'] ?? [], true)) {
            return true;
        }

        foreach ($scope['pairs'] ?? [] as $pair) {
            if ($pair['site'] === $site && $pair['perusahaan'] === $perusahaan) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    public function applyToNewRow(array $row, array $scope): array
    {
        if (! ($scope['scoped'] ?? false)) {
            return $row;
        }

        if ($scope['lock_site'] && ($scope['sites'][0] ?? '') !== '') {
            $row['site'] = $scope['sites'][0];
        }

        if ($scope['lock_perusahaan'] && ($scope['companies'][0] ?? '') !== '') {
            $row['perusahaan'] = $scope['companies'][0];
        }

        return $row;
    }

    /**
     * Saring daftar opsi dropdown ([nilai => label]) agar hanya memuat site/perusahaan
     * yang boleh diakses. Entri kosong ("Semua …") selalu dipertahankan.
     *
     * @param  array<string, string>  $options
     * @param  list<string>  $allowed
     * @return array<string, string>
     */
    public function narrowOptions(array $options, array $allowed): array
    {
        if ($allowed === []) {
            return $options;
        }

        $narrowed = [];
        foreach ($options as $key => $label) {
            if ((string) $key === '' || in_array((string) $key, $allowed, true)) {
                $narrowed[$key] = $label;
            }
        }

        return $narrowed;
    }

    /**
     * Ringkasan scope satu baris untuk banner di dashboard.
     *
     * @param  array<string, mixed>  $scope
     */
    public function describe(array $scope): string
    {
        if (! ($scope['scoped'] ?? false)) {
            return 'Semua site & perusahaan';
        }

        $allSiteCompanies = $scope['all_site_companies'] ?? [];
        $byCompany = [];

        foreach ($allSiteCompanies as $company) {
            $byCompany[$company] = ['semua site'];
        }

        foreach ($scope['pairs'] ?? [] as $pair) {
            if (isset($byCompany[$pair['perusahaan']]) && $byCompany[$pair['perusahaan']] === ['semua site']) {
                continue;
            }

            $byCompany[$pair['perusahaan']][] = $pair['site'];
        }

        if ($byCompany === []) {
            return 'Belum ada perusahaan/site yang di-assign';
        }

        $parts = [];
        foreach ($byCompany as $company => $sites) {
            $parts[] = $company . ' (' . implode(', ', array_unique($sites)) . ')';
        }

        return implode(' · ', $parts);
    }

    /**
     * Entri mentah NAMA_PIC.json, dipakai juga oleh fitur impor assignment.
     *
     * @return list<array{site: string, perusahaan: string, nama: string, sid: string}>
     */
    public function legacyEntries(): array
    {
        if ($this->legacyEntries !== null) {
            return $this->legacyEntries;
        }

        $paths = [
            base_path('NAMA_PIC.json'),
            storage_path('app/monitoring_safety_engineering/nama_pic.json'),
        ];

        $decoded = null;
        foreach ($paths as $path) {
            if (! is_file($path)) {
                continue;
            }

            $raw = file_get_contents($path);
            if ($raw === false || $raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                break;
            }
        }

        $entries = [];
        foreach (is_array($decoded) ? $decoded : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $entries[] = [
                'site' => trim((string) ($row['site'] ?? '')),
                'perusahaan' => trim((string) ($row['perusahaan'] ?? '')),
                'nama' => trim((string) ($row['nama'] ?? '')),
                'sid' => trim((string) ($row['sid'] ?? '')),
            ];
        }

        $this->legacyEntries = $entries;

        return $this->legacyEntries;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFor(?User $user): array
    {
        $empty = $this->emptyScope();

        if ($user === null || $user->isAdmin()) {
            return $empty;
        }

        $fromDb = $this->assignmentRowsFor($user);
        if ($fromDb !== null) {
            return $this->buildScope($fromDb['rows'], $fromDb['nama'], $fromDb['sid'], 'assignment');
        }

        $matches = [];
        foreach ($this->legacyEntries() as $row) {
            if ($this->rowMatchesUser($row['nama'], $row['sid'], $user)) {
                $matches[] = ['site' => $row['site'], 'perusahaan' => $row['perusahaan']];
            }
        }

        if ($matches === []) {
            return $empty;
        }

        return $this->buildScope($matches, $user->name, null, 'legacy_json');
    }

    /**
     * Baris scope dari tabel assignment, atau null bila tabel belum ada / user belum punya assignment.
     *
     * @return array{rows: list<array{site: string, perusahaan: string}>, nama: string, sid: ?string}|null
     */
    private function assignmentRowsFor(User $user): ?array
    {
        if (! Schema::hasTable('monitoring_safety_engineering_pic_assignments')
            || ! Schema::hasTable('monitoring_safety_engineering_pic_assignment_scopes')) {
            return null;
        }

        $identities = $this->userIdentities($user);

        $assignments = MonitoringSafetyEngineeringPicAssignment::query()
            ->with('scopeRows')
            ->active()
            ->where(function ($query) use ($user, $identities): void {
                $query->where('user_id', $user->getKey());

                foreach ($identities as $identity) {
                    $query->orWhereRaw('LOWER(TRIM(nama)) = ?', [$identity]);
                    $query->orWhereRaw('LOWER(TRIM(sid)) = ?', [$identity]);
                }
            })
            ->get();

        if ($assignments->isEmpty()) {
            return null;
        }

        $rows = [];
        foreach ($assignments as $assignment) {
            foreach ($assignment->scopeRows as $scopeRow) {
                $rows[] = [
                    'site' => (string) $scopeRow->site,
                    'perusahaan' => (string) $scopeRow->perusahaan,
                ];
            }
        }

        /** @var MonitoringSafetyEngineeringPicAssignment $first */
        $first = $assignments->first();

        return [
            'rows' => $rows,
            'nama' => trim($first->nama) !== '' ? trim($first->nama) : (string) $user->name,
            'sid' => trim((string) $first->sid) !== '' ? trim((string) $first->sid) : null,
        ];
    }

    /**
     * @param  list<array{site: string, perusahaan: string}>  $rows
     * @return array<string, mixed>
     */
    private function buildScope(array $rows, ?string $nama, ?string $sid, string $source): array
    {
        $pairs = [];
        $sites = [];
        $companies = [];
        $allSiteCompanies = [];

        foreach ($rows as $row) {
            $site = trim($row['site']);
            $perusahaan = trim($row['perusahaan']);

            if ($perusahaan === '') {
                continue;
            }

            $companies[$perusahaan] = $perusahaan;

            if (MonitoringSafetyEngineeringPicAssignment::isAllSitesLabel($site)) {
                $allSiteCompanies[$perusahaan] = $perusahaan;

                continue;
            }

            if ($site === '') {
                continue;
            }

            $sites[$site] = $site;
            $pairs[$site . '|' . $perusahaan] = [
                'site' => $site,
                'perusahaan' => $perusahaan,
            ];
        }

        $siteList = array_values($sites);
        $companyList = array_values($companies);
        $allSiteCompanyList = array_values($allSiteCompanies);
        sort($siteList, SORT_NATURAL | SORT_FLAG_CASE);
        sort($companyList, SORT_NATURAL | SORT_FLAG_CASE);
        sort($allSiteCompanyList, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'scoped' => true,
            'source' => $source,
            'nama' => $nama !== null && $nama !== '' ? $nama : null,
            'sid' => $sid,
            'pairs' => array_values($pairs),
            'sites' => $siteList,
            'companies' => $companyList,
            'all_site_companies' => $allSiteCompanyList,
            'lock_site' => $allSiteCompanyList === [] && count($siteList) === 1,
            'lock_perusahaan' => count($companyList) === 1,
            'all_sites' => $allSiteCompanyList !== [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyScope(): array
    {
        return [
            'scoped' => false,
            'source' => 'none',
            'nama' => null,
            'sid' => null,
            'pairs' => [],
            'sites' => [],
            'companies' => [],
            'all_site_companies' => [],
            'lock_site' => false,
            'lock_perusahaan' => false,
            'all_sites' => false,
        ];
    }

    private function rowMatchesUser(string $nama, string $sid, User $user): bool
    {
        $identities = $this->userIdentities($user);

        if ($sid !== '' && in_array($this->normalizeKey($sid), $identities, true)) {
            return true;
        }

        return $nama !== '' && in_array($this->normalizeKey($nama), $identities, true);
    }

    /**
     * @return list<string>
     */
    private function userIdentities(User $user): array
    {
        $keys = [];
        $email = $this->normalizeKey((string) $user->email);
        $name = $this->normalizeKey((string) $user->name);

        if ($email !== '') {
            $keys[] = $email;
            $at = strpos($email, '@');
            if ($at !== false && $at > 0) {
                $keys[] = substr($email, 0, $at);
            }
        }

        if ($name !== '') {
            $keys[] = $name;
        }

        return array_values(array_unique($keys));
    }

    private function normalizeKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized;
    }
}
