<?php

declare(strict_types=1);

namespace App\Services\OhsDashboard;

use App\Exceptions\OhsDashboard\OhsDashboardException;
use App\Models\OhsDashboard\EmailSchedulerSetting;
use App\Models\OhsDashboard\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class HseSyncService
{
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

        $apiKey = trim((string) config('ohs-dashboard.hse.api_key'));
        if ($apiKey === '') {
            throw new OhsDashboardException('HSE_API_KEY belum dikonfigurasi. Isi HSE_API_KEY di .env.');
        }

        $base = rtrim((string) config('ohs-dashboard.hse.base'), '/');
        $timeout = (int) config('ohs-dashboard.hse.timeout', 120);
        $companyPageSize = (int) config('ohs-dashboard.hse.company_page_size', 1000);
        $employeePageSize = (int) config('ohs-dashboard.hse.employee_page_size', 30000);
        $concurrency = (int) config('ohs-dashboard.hse.concurrency', 8);

        $companyUrl = $base.'/sid2/api/ftwApi/getCompany?page=1&size='.$companyPageSize;
        $companyResponse = Http::timeout($timeout)
            ->withHeaders(['x-api-key' => $apiKey])
            ->get($companyUrl);

        if (! $companyResponse->successful()) {
            throw new OhsDashboardException('Gagal mengambil daftar company dari API HSE.');
        }

        $companyIds = [];
        foreach ((array) ($companyResponse->json('results') ?? []) as $company) {
            if (! is_array($company)) {
                continue;
            }
            $id = $company['id'] ?? $company['companyId'] ?? null;
            if ($id !== null && $id !== '') {
                $companyIds[] = $id;
            }
        }

        $mapped = [];
        $failedCompanyIds = [];
        $chunks = array_chunk($companyIds, max(1, $concurrency));

        foreach ($chunks as $chunk) {
            foreach ($chunk as $companyId) {
                $employees = $this->fetchEmployees($base, $apiKey, $companyId, $employeePageSize, $timeout);
                if ($employees === null) {
                    $failedCompanyIds[] = $companyId;
                    continue;
                }
                foreach ($employees as $item) {
                    $rowMap = $this->mapEmployee($item);
                    if ($rowMap === null) {
                        continue;
                    }
                    $empId = $rowMap['emp_id'];
                    if (! isset($mapped[$empId])) {
                        $mapped[$empId] = $rowMap;
                    }
                }
            }
        }

        DB::transaction(function () use ($mapped): void {
            Employee::query()->delete();
            foreach (array_chunk(array_values($mapped), 500) as $chunk) {
                Employee::query()->insert($chunk);
            }
        });

        $count = count($mapped);
        $row->hse_sync_last_key = $this->support->formatISO($this->support->startOfWeekMonday($now));
        $row->hse_sync_last_run_at = $now;
        $row->hse_sync_last_count = $count;
        $row->save();

        $message = 'Sinkronisasi HSE selesai. '.$count.' karyawan AKTIF.';
        if ($failedCompanyIds !== []) {
            $message .= ' Gagal company: '.implode(', ', array_map('strval', $failedCompanyIds)).'.';
        }

        return ['count' => $count, 'failedCompanyIds' => $failedCompanyIds, 'message' => $message];
    }

    public function runScheduled(): array
    {
        return $this->syncNow(true);
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function fetchEmployees(string $base, string $apiKey, mixed $companyId, int $size, int $timeout): ?array
    {
        $url = $base.'/sid2/api/ftwApi/getEmployee?companyId='.$companyId.'&page=1&size='.$size;
        $attempt = 0;

        while ($attempt < 2) {
            $attempt++;
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders(['x-api-key' => $apiKey])
                    ->get($url);
                if ($response->successful()) {
                    $results = $response->json('results');

                    return is_array($results) ? $results : [];
                }
            } catch (Throwable $e) {
                report($e);
                Log::warning('OHS HSE employee fetch failed', [
                    'companyId' => $companyId,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function mapEmployee(array $item): ?array
    {
        $status = strtoupper(trim((string) ($item['status'] ?? '')));
        if ($status !== 'AKTIF') {
            return null;
        }

        $empId = trim((string) ($item['npk'] ?? ''));
        $empName = trim((string) ($item['name'] ?? ''));
        if ($empId === '' || $empName === '') {
            return null;
        }

        return [
            'emp_id' => $empId,
            'sid' => $this->nullable($item['sidCode'] ?? null),
            'emp_name' => $empName,
            'position' => $this->nullable($item['structuralPosition'] ?? null),
            'team' => $this->nullable($item['departmentName'] ?? null),
            'site_dedicated' => $this->nullable($item['dedicatedSite'] ?? null),
            'company' => $this->nullable($item['companyName'] ?? null),
            'photo_url' => '',
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
