<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringRecord;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class MonitoringSafetyEngineeringPicScopeService
{
    /** @var list<array{site: string, perusahaan: string, nama: string, sid: string}>|null */
    private ?array $entries = null;

    /**
     * @return array{
     *     scoped: bool,
     *     nama: ?string,
     *     sid: ?string,
     *     pairs: list<array{site: string, perusahaan: string}>,
     *     sites: list<string>,
     *     companies: list<string>,
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
     *     nama: ?string,
     *     sid: ?string,
     *     pairs: list<array{site: string, perusahaan: string}>,
     *     sites: list<string>,
     *     companies: list<string>,
     *     lock_site: bool,
     *     lock_perusahaan: bool,
     *     all_sites: bool
     * }
     */
    public function forUser(?User $user): array
    {
        $empty = [
            'scoped' => false,
            'nama' => null,
            'sid' => null,
            'pairs' => [],
            'sites' => [],
            'companies' => [],
            'lock_site' => false,
            'lock_perusahaan' => false,
            'all_sites' => false,
        ];

        if ($user === null || $user->isAdmin()) {
            return $empty;
        }

        $matches = [];
        foreach ($this->entries() as $row) {
            if ($this->rowMatchesUser($row, $user)) {
                $matches[] = $row;
            }
        }

        if ($matches === []) {
            return $empty;
        }

        $pairs = [];
        $sites = [];
        $companies = [];
        $allSites = false;

        foreach ($matches as $row) {
            $site = trim($row['site']);
            $perusahaan = trim($row['perusahaan']);
            if ($this->isAllSitesLabel($site)) {
                $allSites = true;
            } elseif ($site !== '') {
                $sites[$site] = $site;
            }
            if ($perusahaan !== '') {
                $companies[$perusahaan] = $perusahaan;
            }
            if (! $this->isAllSitesLabel($site) && $site !== '' && $perusahaan !== '') {
                $pairs[$site . '|' . $perusahaan] = [
                    'site' => $site,
                    'perusahaan' => $perusahaan,
                ];
            }
        }

        $siteList = array_values($sites);
        $companyList = array_values($companies);

        return [
            'scoped' => true,
            'nama' => $matches[0]['nama'] !== '' ? $matches[0]['nama'] : $user->name,
            'sid' => $matches[0]['sid'] !== '' ? $matches[0]['sid'] : null,
            'pairs' => array_values($pairs),
            'sites' => $siteList,
            'companies' => $companyList,
            'lock_site' => ! $allSites && count($siteList) === 1,
            'lock_perusahaan' => count($companyList) === 1,
            'all_sites' => $allSites,
        ];
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

        $companies = $scope['companies'] ?? [];
        $pairs = $scope['pairs'] ?? [];
        $allSites = (bool) ($scope['all_sites'] ?? false);

        if ($allSites) {
            if ($companies !== []) {
                $query->whereIn('perusahaan', $companies);
            }

            return;
        }

        if ($pairs === []) {
            if ($companies !== []) {
                $query->whereIn('perusahaan', $companies);
            }
            if (($scope['sites'] ?? []) !== []) {
                $query->whereIn('site', $scope['sites']);
            }

            return;
        }

        $query->where(function (object $inner) use ($pairs): void {
            foreach ($pairs as $index => $pair) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $inner->{$method}(function (object $pairQuery) use ($pair): void {
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
        $companies = $scope['companies'] ?? [];
        $pairs = $scope['pairs'] ?? [];

        if ((bool) ($scope['all_sites'] ?? false)) {
            return $companies === [] || in_array($perusahaan, $companies, true);
        }

        foreach ($pairs as $pair) {
            if ($pair['site'] === $site && $pair['perusahaan'] === $perusahaan) {
                return true;
            }
        }

        $sites = $scope['sites'] ?? [];

        $siteOk = $sites === [] || in_array($site, $sites, true);
        $companyOk = $companies === [] || in_array($perusahaan, $companies, true);

        return $siteOk && $companyOk;
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
     * @return list<array{site: string, perusahaan: string, nama: string, sid: string}>
     */
    private function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
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

        $this->entries = $entries;

        return $this->entries;
    }

    /**
     * @param  array{site: string, perusahaan: string, nama: string, sid: string}  $row
     */
    private function rowMatchesUser(array $row, User $user): bool
    {
        $identities = $this->userIdentities($user);

        if ($row['sid'] !== '' && in_array($this->normalizeKey($row['sid']), $identities, true)) {
            return true;
        }

        return $row['nama'] !== '' && in_array($this->normalizeKey($row['nama']), $identities, true);
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

    private function isAllSitesLabel(string $site): bool
    {
        $key = $this->normalizeKey($site);

        return $key === 'all site' || $key === 'allsite' || $key === 'semua site' || $key === 'all';
    }
}
