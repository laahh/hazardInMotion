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
     *     filterOptions: array{sites:list<string>,companies:list<string>,divisions:list<string>,statuses:list<string>},
     *     employees: list<array<string,mixed>>,
     *     total: int,
     *     page: int,
     *     perPage: int,
     *     lastPage: int
     * }
     */
    public function indexPage(Request $request): array
    {
        $filters = $this->readFilters($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 15);
        if ($perPage < 5) {
            $perPage = 15;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $empty = [
            'connectionUp' => false,
            'filters' => $filters,
            'filterOptions' => [
                'sites' => [],
                'companies' => [],
                'divisions' => [],
                'statuses' => self::STATUS_OPTIONS,
            ],
            'employees' => [],
            'total' => 0,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => 1,
        ];

        if (! $this->connection->isUp()) {
            return $empty;
        }

        try {
            $search = $filters['q'];
            $base = $this->baseQuery();
            $filtered = $this->applyFilters(clone $base, $filters, $search);
            $total = (int) (clone $filtered)->count();
            $lastPage = max(1, (int) ceil($total / $perPage));
            if ($page > $lastPage) {
                $page = $lastPage;
            }
            $offset = ($page - 1) * $perPage;

            $rows = $filtered
                ->orderBy('e.nama')
                ->offset($offset)
                ->limit($perPage)
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

            $employees = $rows->map(static function ($row): array {
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
                'connectionUp' => true,
                'filters' => $filters,
                'filterOptions' => $this->buildFilterOptions(),
                'employees' => $employees,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'lastPage' => $lastPage,
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

            $orderColumnIndex = (int) data_get($request->input('order'), '0.column', 1);
            $orderDir = strtolower((string) data_get($request->input('order'), '0.dir', 'asc')) === 'desc'
                ? 'desc'
                : 'asc';

            $orderable = [
                1 => 'e.nama',
                2 => 'e.kode_sid',
                3 => 'e.site',
                4 => 'e.nama_perusahaan',
                5 => 'e.divisi',
                6 => 'e.status_karyawan',
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
     * Header kolom template import Excel.
     *
     * @return list<string>
     */
    public function importTemplateHeaders(): array
    {
        return [
            'nama',
            'kode_sid',
            'nik',
            'status_karyawan',
            'site',
            'usia',
            'divisi',
            'departement',
            'dept_dic',
            'kategori',
            'masa_kerja',
            'id_perusahaan',
            'nama_perusahaan',
            'level_jabatan',
            'kategori_karyawan',
            'jabatan_fungsional',
            'jabatan_struktural',
            'membership_tier',
            'foto',
            'avatar_url',
        ];
    }

    /**
     * @return list<list<string|int|null>>
     */
    public function importTemplateExampleRows(): array
    {
        return [
            [
                'AGUS CAHYONO',
                '6H2DF',
                '61091015',
                'AKTIF',
                'GMO',
                40,
                'ALL DIVISION',
                'Site Plant 2',
                '',
                '',
                '',
                0,
                'PT Pamapersada Nusantara',
                '',
                '',
                'Foreman/Group Leader',
                'WHEEL TYPE GL',
                '',
                '',
                '',
            ],
        ];
    }

    /**
     * Import baris Excel (baris 0 = header).
     * - kode_sid baru → create
     * - kode_sid sudah ada → update
     * Password selalu = bcrypt(kode_sid).
     *
     * @param  list<array<int, mixed>>  $rows
     * @return array{created:int,updated:int,skipped:int,errors:list<string>}
     */
    public function importRows(array $rows): array
    {
        if (! $this->connection->isUp()) {
            throw new RuntimeException('Koneksi BeWell tidak tersedia.');
        }

        if ($rows === []) {
            throw new InvalidArgumentException('File kosong atau tidak memiliki data.');
        }

        $header = array_map(
            static fn ($v): string => strtolower(trim((string) $v)),
            (array) $rows[0]
        );

        $namaIdx = $this->findColumnIndex($header, ['nama', 'name']);
        $sidIdx = $this->findColumnIndex($header, ['kode_sid', 'sid', 'kode sid']);

        if ($namaIdx === null || $sidIdx === null) {
            throw new InvalidArgumentException(
                'Kolom wajib: nama dan kode_sid. Unduh template untuk format yang benar.'
            );
        }

        $map = [
            'nik' => $this->findColumnIndex($header, ['nik']),
            'status_karyawan' => $this->findColumnIndex($header, ['status_karyawan', 'status']),
            'site' => $this->findColumnIndex($header, ['site']),
            'usia' => $this->findColumnIndex($header, ['usia']),
            'divisi' => $this->findColumnIndex($header, ['divisi']),
            'departement' => $this->findColumnIndex($header, ['departement', 'departemen', 'department']),
            'dept_dic' => $this->findColumnIndex($header, ['dept_dic']),
            'kategori' => $this->findColumnIndex($header, ['kategori']),
            'masa_kerja' => $this->findColumnIndex($header, ['masa_kerja']),
            'id_perusahaan' => $this->findColumnIndex($header, ['id_perusahaan']),
            'nama_perusahaan' => $this->findColumnIndex($header, ['nama_perusahaan', 'perusahaan', 'company']),
            'level_jabatan' => $this->findColumnIndex($header, ['level_jabatan']),
            'kategori_karyawan' => $this->findColumnIndex($header, ['kategori_karyawan']),
            'jabatan_fungsional' => $this->findColumnIndex($header, ['jabatan_fungsional', 'jabatan']),
            'jabatan_struktural' => $this->findColumnIndex($header, ['jabatan_struktural']),
            'membership_tier' => $this->findColumnIndex($header, ['membership_tier']),
            'foto' => $this->findColumnIndex($header, ['foto']),
            'avatar_url' => $this->findColumnIndex($header, ['avatar_url', 'avatar']),
        ];

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        for ($i = 1, $count = count($rows); $i < $count; $i++) {
            $row = (array) $rows[$i];
            $line = $i + 1;

            $nama = trim((string) ($row[$namaIdx] ?? ''));
            $kodeSid = trim((string) ($row[$sidIdx] ?? ''));

            if ($nama === '' && $kodeSid === '') {
                continue;
            }

            if ($nama === '' || $kodeSid === '') {
                $errors[] = "Baris {$line}: nama dan kode_sid wajib diisi.";
                $skipped++;
                continue;
            }

            $payload = [
                'nama' => $nama,
                'kode_sid' => $kodeSid,
                'status_karyawan' => 'AKTIF',
            ];

            foreach ($map as $field => $idx) {
                if ($idx === null) {
                    continue;
                }
                $raw = $row[$idx] ?? null;
                if ($raw === null || trim((string) $raw) === '') {
                    continue;
                }
                $payload[$field] = is_string($raw) ? trim($raw) : $raw;
            }

            if (! isset($payload['status_karyawan']) || trim((string) $payload['status_karyawan']) === '') {
                $payload['status_karyawan'] = 'AKTIF';
            }

            try {
                $existingId = $this->findIdByKodeSid($kodeSid);
                if ($existingId !== null) {
                    $this->update($existingId, $payload);
                    $updated++;
                } else {
                    $this->create($payload);
                    $created++;
                }
            } catch (Throwable $e) {
                $errors[] = "Baris {$line} ({$kodeSid}): ".$e->getMessage();
                $skipped++;
            }
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    public function findIdByKodeSid(string $kodeSid): ?int
    {
        $row = $this->db()->table('employee_profiles')
            ->where('kode_sid', $kodeSid)
            ->first(['id']);

        return $row !== null ? (int) $row->id : null;
    }

    /**
     * SID yang sudah ada di BeWell (untuk sync append-only).
     *
     * @param  list<string>  $kodeSids
     * @return array<string, true> map UPPER(kode_sid) => true
     */
    public function existingKodeSidMap(array $kodeSids): array
    {
        $normalized = [];
        foreach ($kodeSids as $sid) {
            $sid = mb_strtoupper(trim((string) $sid));
            if ($sid === '') {
                continue;
            }
            $normalized[$sid] = true;
        }

        if ($normalized === []) {
            return [];
        }

        if (! $this->connection->isUp()) {
            throw new RuntimeException('Koneksi BeWell tidak tersedia.');
        }

        $map = [];
        foreach (array_chunk(array_keys($normalized), 800) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rows = $this->db()->select(
                'SELECT kode_sid FROM employee_profiles WHERE UPPER(TRIM(kode_sid)) IN ('.$placeholders.')',
                $chunk
            );

            foreach ($rows as $row) {
                $key = mb_strtoupper(trim((string) ($row->kode_sid ?? '')));
                if ($key !== '') {
                    $map[$key] = true;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $possibleNames
     */
    private function findColumnIndex(array $header, array $possibleNames): ?int
    {
        foreach ($possibleNames as $name) {
            $idx = array_search($name, $header, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }

        return null;
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
