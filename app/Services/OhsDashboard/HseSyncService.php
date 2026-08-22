<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard;

use App\Exceptions\OhsDashboard\OhsDashboardException;
use App\Models\OhsDashboard\EmailSchedulerSetting;
use App\Models\OhsDashboard\Employee;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sumber data: view bcsid.crontable_bep_vw_m_karyawan_aktif pada database
 * `hse_automation` (Postgres RDS "postgresql-olap-bc-production") — view ini
 * sudah berisi karyawan AKTIF saja, seluruh company (~24 ribu baris saat
 * diverifikasi). Koneksi LANGSUNG ke RDS (`pgsql_direct`), tunnel SSH
 * (`pgsql_ssh`) sengaja tidak dipakai — sama seperti keputusan eksplisit di
 * App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader untuk
 * tabel bcsid lain: tunnel di server tidak selalu aktif, akses langsung lebih
 * andal.
 */
final class HseSyncService
{
    private const CONNECTION_DIRECT = 'pgsql_direct';

    private const EMPLOYEE_VIEW = 'bcsid.crontable_bep_vw_m_karyawan_aktif';

    private const UP_CACHE_KEY = 'ohs-dashboard:hse-sync:connection-v1';

    private const UP_CACHE_TTL = 20;

    private const SOCKET_TIMEOUT_SECONDS = 3;

    public function __construct(private readonly OhsDashboardSupport $support) {}

    /**
     * @return array{count: int, failedCompanyIds: list<mixed>, message: string}
     */
    public function syncNow(bool $respectSchedule = false): array
    {
        $row = EmailSchedulerSetting::instance();
        $now = $this->support->now();

        if ($respectSchedule) {
            $decision = $this->support->getHseSyncDecision((string) $row->hse_sync_last_key, $now);
            if (! $decision['shouldRun']) {
                return ['count' => 0, 'failedCompanyIds' => [], 'message' => $decision['reason']];
            }
        }

        $connection = $this->connectionName();
        if ($connection === null) {
            throw new OhsDashboardException('Database HSE (bcsid) tidak dapat dijangkau saat ini.');
        }

        try {
            $rows = DB::connection($connection)->select(
                'SELECT nik, kode_sid, nama, jabatan_struktural, departement, site_dedicated, nama_perusahaan, url_foto
                 FROM '.self::EMPLOYEE_VIEW.'
                 WHERE status_karyawan = ?',
                ['AKTIF'],
            );
        } catch (Throwable $e) {
            report($e);

            throw new OhsDashboardException('Gagal mengambil data karyawan dari database HSE: '.$e->getMessage());
        }

        $mapped = [];
        foreach ($rows as $item) {
            $rowMap = $this->mapEmployee((array) $item);
            if ($rowMap === null) {
                continue;
            }
            $empId = $rowMap['emp_id'];
            if (! isset($mapped[$empId])) {
                $mapped[$empId] = $rowMap;
            }
        }

        DB::transaction(function () use ($mapped): void {
            Employee::query()->delete();
            foreach (array_chunk(array_values($mapped), 500) as $chunk) {
                Employee::query()->insert($chunk);
            }
        });

        $count = count($mapped);
        Cache::forget(InitService::CACHE_KEY);
        $row->hse_sync_last_key = $this->support->formatISO($this->support->startOfWeekMonday($now));
        $row->hse_sync_last_run_at = $now;
        $row->hse_sync_last_count = $count;
        $row->save();

        return [
            'count' => $count,
            'failedCompanyIds' => [],
            'message' => 'Sinkronisasi HSE selesai. '.$count.' karyawan AKTIF.',
        ];
    }

    public function runScheduled(): array
    {
        return $this->syncNow(true);
    }

    private function connectionName(): ?string
    {
        try {
            $cached = Cache::remember(self::UP_CACHE_KEY, self::UP_CACHE_TTL, function (): string {
                return $this->isHostReachable(self::CONNECTION_DIRECT) && $this->ping(self::CONNECTION_DIRECT)
                    ? self::CONNECTION_DIRECT
                    : '';
            });
        } catch (Throwable $e) {
            report($e);
            $cached = $this->isHostReachable(self::CONNECTION_DIRECT) && $this->ping(self::CONNECTION_DIRECT)
                ? self::CONNECTION_DIRECT
                : '';
        }

        return $cached !== '' ? $cached : null;
    }

    private function isHostReachable(string $connection): bool
    {
        $host = config("database.connections.{$connection}.host");
        $port = config("database.connections.{$connection}.port");
        if (! is_string($host) || $host === '' || ! is_numeric($port) || (int) $port <= 0) {
            return true;
        }

        $socket = @fsockopen($host, (int) $port, $errno, $errstr, self::SOCKET_TIMEOUT_SECONDS);
        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    private function ping(string $connection): bool
    {
        try {
            DB::connection($connection)->select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function mapEmployee(array $item): ?array
    {
        $empId = trim((string) ($item['nik'] ?? ''));
        $empName = trim((string) ($item['nama'] ?? ''));
        if ($empId === '' || $empName === '') {
            return null;
        }

        return [
            'emp_id' => $empId,
            'sid' => $this->nullable($item['kode_sid'] ?? null),
            'emp_name' => $empName,
            'position' => $this->nullable($item['jabatan_struktural'] ?? null),
            'team' => $this->nullable($item['departement'] ?? null),
            'site_dedicated' => $this->nullable($item['site_dedicated'] ?? null),
            'company' => $this->nullable($item['nama_perusahaan'] ?? null),
            'photo_url' => $this->nullable($item['url_foto'] ?? null) ?? '',
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
