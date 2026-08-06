<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Create / Read / Update employee_profiles di bewell_db.
 *
 * Tidak menghapus baris. password_hash di-set bcrypt(kode_sid) saat create/update
 * (sesuai login BeWell: password = SID). Hash tidak pernah ditampilkan di UI.
 */
final class SportEvaluationEmployeeProfileService
{
    public const CONNECTION = 'bewell_db';

    /** @var list<string> */
    public const SAFE_COLUMNS = [
        'id',
        'nik',
        'nama',
        'foto',
        'avatar_url',
        'site',
        'usia',
        'divisi',
        'departement',
        'dept_dic',
        'kategori',
        'kode_sid',
        'masa_kerja',
        'id_perusahaan',
        'nama_perusahaan',
        'level_jabatan',
        'status_karyawan',
        'kategori_karyawan',
        'jabatan_fungsional',
        'jabatan_struktural',
        'membership_tier',
        'last_login_at',
        'login_count',
        'last_login_ip',
        'last_platform',
    ];

    /** @var list<string> */
    public const WRITABLE_COLUMNS = [
        'nik',
        'nama',
        'foto',
        'avatar_url',
        'site',
        'usia',
        'divisi',
        'departement',
        'dept_dic',
        'kategori',
        'kode_sid',
        'masa_kerja',
        'id_perusahaan',
        'nama_perusahaan',
        'level_jabatan',
        'status_karyawan',
        'kategori_karyawan',
        'jabatan_fungsional',
        'jabatan_struktural',
        'membership_tier',
    ];

    /** @var list<string> */
    public const STATUS_OPTIONS = [
        'AKTIF',
        'NONAKTIF',
    ];

    public function __construct(
        private readonly BewellConnectionService $connection,
    ) {}

    /**
     * @return array{
     *     connectionUp: bool,
     *     filters: array{q:string,site:string,company:string,division:string,status:string},
     *     filterOptions: array{sites:list<string>,companies:list<string>,divisions:list<string>,statuses:list<string>}
     * }
     */
    public function indexPage(Request $request): array
    {
        $filters = $this->readFilters($request);
        $empty = [
            'connectionUp' => false,
            'filters' => $filters,
            'filterOptions' => [
                'sites' => [],
                'companies' => [],
                'divisions' => [],
                'statuses' => self::STATUS_OPTIONS,
            ],
        ];

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            return [
                'connectionUp' => true,
                'filters' => $filters,
                'filterOptions' => $this->buildFilterOptions(),
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return array{sites:list<string>,companies:list<string>,divisions:list<string>,statuses:list<string>}
     */
    public function buildFilterOptions(): array
    {
        $db = $this->db();

        $sites = $db->table('employee_profiles')
            ->whereNotNull('site')
            ->where('site', '<>', '')
            ->distinct()
            ->orderBy('site')
            ->pluck('site')
            ->map(static fn ($v): string => (string) $v)
            ->values()
            ->all();

        $companies = $db->table('employee_profiles')
            ->whereNotNull('nama_perusahaan')
            ->where('nama_perusahaan', '<>', '')
            ->distinct()
            ->orderBy('nama_perusahaan')
            ->pluck('nama_perusahaan')
            ->map(static fn ($v): string => (string) $v)
            ->values()
            ->all();

        $divisions = $db->table('employee_profiles')
            ->whereNotNull('divisi')
            ->where('divisi', '<>', '')
            ->distinct()
            ->orderBy('divisi')
            ->pluck('divisi')
            ->map(static fn ($v): string => (string) $v)
            ->values()
            ->all();

        $statusesFromDb = $db->table('employee_profiles')
            ->whereNotNull('status_karyawan')
            ->where('status_karyawan', '<>', '')
            ->distinct()
            ->orderBy('status_karyawan')
            ->pluck('status_karyawan')
            ->map(static fn ($v): string => (string) $v)
            ->values()
            ->all();

        $statuses = array_values(array_unique(array_merge(self::STATUS_OPTIONS, $statusesFromDb)));

        return [
            'sites' => $sites,
            'companies' => $companies,
            'divisions' => $divisions,
            'statuses' => $statuses,
        ];
    }

    /**
     * @return array{q:string,site:string,company:string,division:string,status:string}
     */
    public function readFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q', '')),
            'site' => trim((string) $request->input('site', '')),
            'company' => trim((string) $request->input('company', '')),
            'division' => trim((string) $request->input('division', '')),
            'status' => trim((string) $request->input('status', '')),
        ];
    }

    /**
     * DataTables server-side.
     *
     * @return array<string, mixed>
     */
    public function datatable(Request $request): array
    {
        $draw = (int) $request->input('draw', 1);

        if (! $this->connection->isUp()) {
            return [
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ];
        }

        try {
            $filters = $this->readFilters($request);
            $search = trim((string) $request->input('search.value', ''));
            if ($search === '' && $filters['q'] !== '') {
                $search = $filters['q'];
            }

            $start = max(0, (int) $request->input('start', 0));
            $length = (int) $request->input('length', 10);
            if ($length < 1) {
                $length = 10;
            }
            if ($length > 100) {
                $length = 100;
            }

            $orderColumnIndex = (int) data_get($request->input('order'), '0.column', 0);
            $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'asc')) === 'desc'
                ? 'desc'
                : 'asc';

            $orderable = [
                0 => 'e.nama',
                1 => 'e.kode_sid',
                2 => 'e.site',
                3 => 'e.nama_perusahaan',
                4 => 'e.divisi',
                5 => 'e.departement',
                6 => 'e.jabatan_fungsional',
                7 => 'e.status_karyawan',
            ];
            $orderBy = $orderable[$orderColumnIndex] ?? 'e.nama';

            $base = $this->baseQuery();
            $recordsTotal = (int) (clone $base)->count();

            $filtered = $this->applyFilters(clone $base, $filters, $search);
            $recordsFiltered = (int) (clone $filtered)->count();

            $rows = $filtered
                ->orderBy($orderBy, $orderDir)
                ->offset($start)
                ->limit($length)
                ->get([
                    'e.id',
                    'e.nama',
                    'e.kode_sid',
                    'e.nik',
                    'e.site',
                    'e.nama_perusahaan',
                    'e.divisi',
                    'e.departement',
                    'e.jabatan_fungsional',
                    'e.status_karyawan',
                ]);

            $data = $rows->map(static function ($row): array {
                return [
                    'id' => (int) $row->id,
                    'nama' => (string) ($row->nama ?? '-'),
                    'kode_sid' => (string) ($row->kode_sid ?? '-'),
                    'nik' => (string) ($row->nik ?? '-'),
                    'site' => (string) ($row->site ?? '-'),
                    'company' => (string) ($row->nama_perusahaan ?? '-'),
                    'divisi' => (string) ($row->divisi ?? '-'),
                    'departement' => (string) ($row->departement ?? '-'),
                    'jabatan_fungsional' => (string) ($row->jabatan_fungsional ?? '-'),
                    'status_karyawan' => (string) ($row->status_karyawan ?? '-'),
                ];
            })->all();

            return [
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        if (! $this->connection->isUp()) {
            return null;
        }

        try {
            $row = $this->db()->table('employee_profiles')
                ->select(self::SAFE_COLUMNS)
                ->where('id', $id)
                ->first();
        } catch (Throwable $e) {
            report($e);

            // Fallback jika kolom audit login (018) belum ada di environment.
            $row = $this->db()->table('employee_profiles')
                ->select(array_merge(['id'], self::WRITABLE_COLUMNS))
                ->where('id', $id)
                ->first();
        }

        if ($row === null) {
            return null;
        }

        return (array) $row;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): int
    {
        if (! $this->connection->isUp()) {
            throw new RuntimeException('Koneksi BeWell tidak tersedia.');
        }

        $payload = $this->normalizeWritable($input);
        $this->assertUniqueSidNik($payload['kode_sid'] ?? null, $payload['nik'] ?? null, null);
        $payload['password_hash'] = $this->hashPasswordFromSid((string) $payload['kode_sid']);

        // id di BeWell bukan AUTO_INCREMENT. Hanya INSERT baris baru (tidak pernah
        // update/upsert/overwrite). Alokasi id = MAX(id)+1 dengan cek bentrok + retry.
        $maxAttempts = 8;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $id = (int) $this->db()->transaction(function () use ($payload): int {
                    $nextId = $this->allocateNextEmployeeProfileId();

                    // Guard ekstra: jangan pernah menulis ke id yang sudah terpakai.
                    if ($this->db()->table('employee_profiles')->where('id', $nextId)->exists()) {
                        throw new RuntimeException('Alokasi id bentrok; mencoba ulang.');
                    }

                    $insertPayload = $payload;
                    $insertPayload['id'] = $nextId;

                    // INSERT murni — bukan updateOrInsert / upsert.
                    $this->db()->table('employee_profiles')->insert($insertPayload);

                    return $nextId;
                });

                $this->invalidateCaches($id);

                return $id;
            } catch (RuntimeException $e) {
                $lastError = $e;
                if (! str_contains($e->getMessage(), 'bentrok')) {
                    throw $e;
                }
            } catch (QueryException $e) {
                $lastError = $e;
                // 1062 = duplicate entry (race concurrent insert).
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }
            }

            usleep(25_000 * $attempt);
        }

        report($lastError);

        throw new RuntimeException('Gagal mengalokasikan id karyawan baru tanpa bentrok. Coba lagi.');
    }

    /**
     * Password login BeWell = Kode SID (bcrypt). Hash tidak dikembalikan ke UI.
     */
    private function hashPasswordFromSid(string $kodeSid): string
    {
        $sid = trim($kodeSid);
        if ($sid === '') {
            throw new InvalidArgumentException('Kode SID wajib diisi untuk membuat password.');
        }

        return Hash::make($sid);
    }

    /**
     * Ambil id berikutnya setelah id terbesar yang ada (MAX(id) + 1).
     * Melewati id yang kebetulan sudah terpakai (tidak menimpa).
     */
    private function allocateNextEmployeeProfileId(): int
    {
        $locked = $this->db()->table('employee_profiles')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first(['id']);

        $candidate = $locked !== null ? ((int) $locked->id + 1) : 1;

        // Jika ada gap aneh / race residual, maju sampai slot kosong.
        while ($this->db()->table('employee_profiles')->where('id', $candidate)->exists()) {
            $candidate++;
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(int $id, array $input): void
    {
        if (! $this->connection->isUp()) {
            throw new RuntimeException('Koneksi BeWell tidak tersedia.');
        }

        $existing = $this->find($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Karyawan tidak ditemukan.');
        }

        $payload = $this->normalizeWritable($input);
        $this->assertUniqueSidNik($payload['kode_sid'] ?? null, $payload['nik'] ?? null, $id);
        // Sync password = SID agar akun bisa login di app BeWell.
        $payload['password_hash'] = $this->hashPasswordFromSid((string) $payload['kode_sid']);

        $this->db()->table('employee_profiles')
            ->where('id', $id)
            ->update($payload);

        $this->invalidateCaches($id);
    }

    public function isKodeSidTaken(string $kodeSid, ?int $ignoreId = null): bool
    {
        $q = $this->db()->table('employee_profiles')
            ->where('kode_sid', $kodeSid);

        if ($ignoreId !== null) {
            $q->where('id', '<>', $ignoreId);
        }

        return $q->exists();
    }

    public function isNikTaken(string $nik, ?int $ignoreId = null): bool
    {
        $q = $this->db()->table('employee_profiles')
            ->where('nik', $nik);

        if ($ignoreId !== null) {
            $q->where('id', '<>', $ignoreId);
        }

        return $q->exists();
    }

    private function baseQuery(): Builder
    {
        return $this->db()->table('employee_profiles as e');
    }

    /**
     * @param  array{q:string,site:string,company:string,division:string,status:string}  $filters
     */
    private function applyFilters(Builder $query, array $filters, string $search): Builder
    {
        if ($filters['site'] !== '') {
            $query->where('e.site', $filters['site']);
        }
        if ($filters['company'] !== '') {
            $query->where('e.nama_perusahaan', $filters['company']);
        }
        if ($filters['division'] !== '') {
            $query->where('e.divisi', 'like', '%'.$filters['division'].'%');
        }
        if ($filters['status'] !== '') {
            $query->where('e.status_karyawan', $filters['status']);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('e.nama', 'like', $like)
                    ->orWhere('e.kode_sid', 'like', $like)
                    ->orWhere('e.nik', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeWritable(array $input): array
    {
        $payload = [];

        foreach (self::WRITABLE_COLUMNS as $column) {
            if (! array_key_exists($column, $input)) {
                continue;
            }

            $value = $input[$column];

            if ($value === null || $value === '') {
                $payload[$column] = null;
                continue;
            }

            if ($column === 'usia' || $column === 'id_perusahaan') {
                $payload[$column] = is_numeric($value) ? (int) $value : null;
                continue;
            }

            if ($column === 'status_karyawan') {
                $payload[$column] = strtoupper(trim((string) $value));
                continue;
            }

            $payload[$column] = trim((string) $value);
        }

        if (! isset($payload['nama']) || $payload['nama'] === null || $payload['nama'] === '') {
            throw new InvalidArgumentException('Nama wajib diisi.');
        }
        if (! isset($payload['kode_sid']) || $payload['kode_sid'] === null || $payload['kode_sid'] === '') {
            throw new InvalidArgumentException('Kode SID wajib diisi.');
        }
        if (! isset($payload['status_karyawan']) || $payload['status_karyawan'] === null || $payload['status_karyawan'] === '') {
            $payload['status_karyawan'] = 'AKTIF';
        }

        return $payload;
    }

    private function assertUniqueSidNik(?string $kodeSid, ?string $nik, ?int $ignoreId): void
    {
        if ($kodeSid !== null && $kodeSid !== '' && $this->isKodeSidTaken($kodeSid, $ignoreId)) {
            throw new InvalidArgumentException('Kode SID sudah digunakan.');
        }

        if ($nik !== null && $nik !== '' && $this->isNikTaken($nik, $ignoreId)) {
            throw new InvalidArgumentException('NIK sudah digunakan.');
        }
    }

    private function invalidateCaches(int $userId): void
    {
        $keys = [
            'sport_eval:filter_options_v2',
            'sport_eval:profile:v2:'.$userId,
            'evaluasi_well:weekly_uploads:filters_v2',
            'evaluasi_well:install_stats:raw_employees:v2',
            'evaluasi_well:install_stats:filter_options:v4',
            'evaluasi_well:install_stats:kpi_card_total:v1',
            'evaluasi_well:nutrition:dashboard',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    private function db(): \Illuminate\Database\Connection
    {
        return DB::connection(self::CONNECTION);
    }
}
