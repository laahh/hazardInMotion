<?php

declare(strict_types=1);

namespace App\Services\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringPicAssignment;
use App\Models\MonitoringSafetyEngineeringRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRUD assignment PIC Monitoring Safety Engineering: satu orang → banyak perusahaan & site.
 */
final class MonitoringSafetyEngineeringPicAssignmentService
{
    public function __construct(
        private readonly MonitoringSafetyEngineeringPicScopeService $picScope,
    ) {}

    public function tablesReady(): bool
    {
        return Schema::hasTable('monitoring_safety_engineering_pic_assignments')
            && Schema::hasTable('monitoring_safety_engineering_pic_assignment_scopes');
    }

    /**
     * @return Collection<int, MonitoringSafetyEngineeringPicAssignment>
     */
    public function listAssignments(string $search = ''): Collection
    {
        if (! $this->tablesReady()) {
            return new Collection;
        }

        $query = MonitoringSafetyEngineeringPicAssignment::query()
            ->with(['scopeRows', 'user:id,name,email'])
            ->orderBy('nama');

        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('nama', 'like', $like)
                    ->orWhere('sid', 'like', $like)
                    ->orWhereHas('scopeRows', function ($scopeQuery) use ($like): void {
                        $scopeQuery->where('perusahaan', 'like', $like)
                            ->orWhere('site', 'like', $like);
                    });
            });
        }

        return $query->get();
    }

    public function find(int $id): ?MonitoringSafetyEngineeringPicAssignment
    {
        if (! $this->tablesReady()) {
            return null;
        }

        return MonitoringSafetyEngineeringPicAssignment::query()
            ->with(['scopeRows', 'user:id,name,email'])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MonitoringSafetyEngineeringPicAssignment
    {
        return DB::transaction(function () use ($data): MonitoringSafetyEngineeringPicAssignment {
            $assignment = MonitoringSafetyEngineeringPicAssignment::create(
                $this->attributesFrom($data) + ['created_by' => Auth::id(), 'updated_by' => Auth::id()],
            );

            $this->syncScopes($assignment, $data['scopes'] ?? []);
            $this->picScope->flushCache();

            return $assignment->load('scopeRows');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MonitoringSafetyEngineeringPicAssignment $assignment, array $data): MonitoringSafetyEngineeringPicAssignment
    {
        return DB::transaction(function () use ($assignment, $data): MonitoringSafetyEngineeringPicAssignment {
            $assignment->fill($this->attributesFrom($data) + ['updated_by' => Auth::id()]);
            $assignment->save();

            $this->syncScopes($assignment, $data['scopes'] ?? []);
            $this->picScope->flushCache();

            return $assignment->load('scopeRows');
        });
    }

    public function delete(MonitoringSafetyEngineeringPicAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            $assignment->scopeRows()->delete();
            $assignment->delete();
            $this->picScope->flushCache();
        });
    }

    /**
     * Impor assignment awal dari NAMA_PIC.json. Orang yang sudah ada (nama + SID) hanya
     * ditambah scope-nya, tidak diduplikasi.
     *
     * @return array{created: int, scopes_created: int, skipped: int}
     */
    public function importFromLegacyJson(): array
    {
        $created = 0;
        $scopesCreated = 0;
        $skipped = 0;

        $grouped = [];
        foreach ($this->picScope->legacyEntries() as $entry) {
            $nama = trim($entry['nama']);
            $sid = trim($entry['sid']);
            $perusahaan = trim($entry['perusahaan']);
            $site = trim($entry['site']);

            if (($nama === '' && $sid === '') || $perusahaan === '' || $site === '') {
                $skipped++;

                continue;
            }

            $key = strtolower($nama) . '|' . strtolower($sid);
            $grouped[$key] ??= ['nama' => $nama, 'sid' => $sid, 'pairs' => []];
            $grouped[$key]['pairs'][$perusahaan . '|' . $site] = [
                'perusahaan' => $perusahaan,
                'site' => $site,
            ];
        }

        DB::transaction(function () use ($grouped, &$created, &$scopesCreated): void {
            foreach ($grouped as $row) {
                $assignment = MonitoringSafetyEngineeringPicAssignment::query()
                    ->where('nama', $row['nama'])
                    ->when($row['sid'] !== '', fn ($query) => $query->where('sid', $row['sid']))
                    ->first();

                if ($assignment === null) {
                    $assignment = MonitoringSafetyEngineeringPicAssignment::create([
                        'user_id' => $this->guessUserId($row['nama'], $row['sid']),
                        'nama' => $row['nama'],
                        'sid' => $row['sid'] !== '' ? $row['sid'] : null,
                        'is_active' => true,
                        'catatan' => 'Diimpor dari NAMA_PIC.json',
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                    $created++;
                }

                foreach ($row['pairs'] as $pair) {
                    $scope = $assignment->scopeRows()->firstOrCreate([
                        'perusahaan' => $pair['perusahaan'],
                        'site' => $pair['site'],
                    ]);

                    if ($scope->wasRecentlyCreated) {
                        $scopesCreated++;
                    }
                }
            }
        });

        $this->picScope->flushCache();

        return [
            'created' => $created,
            'scopes_created' => $scopesCreated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{sites: list<string>, companies: list<string>}
     */
    public function filterOptions(): array
    {
        $sites = array_map('strval', config('monitoring_safety_engineering.sites', []));
        $companies = array_map('strval', config('monitoring_safety_engineering.perusahaan', []));

        if (Schema::hasTable('monitoring_safety_engineering_records')) {
            $sites = array_merge($sites, MonitoringSafetyEngineeringRecord::query()
                ->distinct()
                ->orderBy('site')
                ->pluck('site')
                ->all());

            $companies = array_merge($companies, MonitoringSafetyEngineeringRecord::query()
                ->distinct()
                ->orderBy('perusahaan')
                ->pluck('perusahaan')
                ->all());
        }

        if ($this->tablesReady()) {
            $sites = array_merge($sites, DB::table('monitoring_safety_engineering_pic_assignment_scopes')
                ->distinct()
                ->pluck('site')
                ->all());

            $companies = array_merge($companies, DB::table('monitoring_safety_engineering_pic_assignment_scopes')
                ->distinct()
                ->pluck('perusahaan')
                ->all());
        }

        foreach ($this->picScope->legacyEntries() as $entry) {
            $sites[] = $entry['site'];
            $companies[] = $entry['perusahaan'];
        }

        return [
            'sites' => $this->cleanOptionList($sites, [MonitoringSafetyEngineeringPicAssignment::ALL_SITES]),
            'companies' => $this->cleanOptionList($companies),
        ];
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function userOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $user): array => [
                'id' => (int) $user->id,
                'label' => trim((string) $user->name) . ' — ' . (string) $user->email,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributesFrom(array $data): array
    {
        $sid = trim((string) ($data['sid'] ?? ''));
        $jabatan = trim((string) ($data['jabatan'] ?? ''));
        $catatan = trim((string) ($data['catatan'] ?? ''));
        $userId = (int) ($data['user_id'] ?? 0);

        return [
            'user_id' => $userId > 0 ? $userId : null,
            'nama' => trim((string) ($data['nama'] ?? '')),
            'sid' => $sid !== '' ? strtoupper($sid) : null,
            'jabatan' => $jabatan !== '' ? $jabatan : null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'catatan' => $catatan !== '' ? $catatan : null,
        ];
    }

    /**
     * @param  list<array{perusahaan?: string, sites?: list<string>}>  $scopes
     */
    private function syncScopes(MonitoringSafetyEngineeringPicAssignment $assignment, array $scopes): void
    {
        $pairs = [];

        foreach ($scopes as $scope) {
            $perusahaan = trim((string) ($scope['perusahaan'] ?? ''));
            if ($perusahaan === '') {
                continue;
            }

            foreach ((array) ($scope['sites'] ?? []) as $site) {
                $site = trim((string) $site);
                if ($site === '') {
                    continue;
                }

                $pairs[$perusahaan . '|' . $site] = [
                    'assignment_id' => $assignment->id,
                    'perusahaan' => $perusahaan,
                    'site' => $site,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $assignment->scopeRows()->delete();

        if ($pairs !== []) {
            DB::table('monitoring_safety_engineering_pic_assignment_scopes')->insert(array_values($pairs));
        }

        $assignment->unsetRelation('scopeRows');
    }

    private function guessUserId(string $nama, string $sid): ?int
    {
        $candidates = array_values(array_filter([
            strtolower(trim($nama)),
            strtolower(trim($sid)),
        ]));

        if ($candidates === []) {
            return null;
        }

        $user = User::query()
            ->where(function ($query) use ($candidates): void {
                foreach ($candidates as $candidate) {
                    $query->orWhereRaw('LOWER(name) = ?', [$candidate]);
                    $query->orWhereRaw('LOWER(email) = ?', [$candidate]);
                }
            })
            ->first(['id']);

        return $user === null ? null : (int) $user->id;
    }

    /**
     * @param  list<string|null>  $values
     * @param  list<string>  $prepend
     * @return list<string>
     */
    private function cleanOptionList(array $values, array $prepend = []): array
    {
        $clean = [];

        foreach (array_merge($prepend, $values) as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $clean[strtoupper($value)] ??= $value;
        }

        $list = array_values($clean);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }
}
