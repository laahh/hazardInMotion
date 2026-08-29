<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sync karyawan HSE → BeWell employee_profiles (upsert in-place).
 *
 * SID baru di-insert; SID existing di-update field-nya (perusahaan, status,
 * jabatan, dll.) tanpa mengubah id — agar food_analyses.user_id tetap nyambung.
 * Password existing tidak direset. Karyawan yang hilang dari roster HSE
 * di-set NONAKTIF.
 */
final class SportEvaluationHseEmployeeSyncService
{
    public function __construct(
        private readonly SportEvaluationHseEmployeeApiClient $apiClient,
        private readonly SportEvaluationEmployeeProfileService $employeeProfileService,
        private readonly BewellConnectionService $connection,
    ) {}

    /**
     * @return array{
     *     inserted: int,
     *     updated: int,
     *     skipped_invalid: int,
     *     failed: int,
     *     companies: int,
     *     sids_seen: int,
     *     deactivated: int,
     *     errors: list<string>
     * }
     */
    public function sync(): array
    {
        if (! $this->connection->isUp()) {
            throw new RuntimeException('Koneksi BeWell tidak tersedia. Pastikan tunnel aktif sebelum sync.');
        }

        $summary = [
            'inserted' => 0,
            'updated' => 0,
            'skipped_invalid' => 0,
            'failed' => 0,
            'companies' => 0,
            'sids_seen' => 0,
            'deactivated' => 0,
            'errors' => [],
        ];

        $token = $this->apiClient->login();
        $companies = $this->apiClient->getCompanies($token);
        $summary['companies'] = count($companies);

        Log::info('evaluasi_well.hse_sync.started', [
            'companies' => $summary['companies'],
        ]);

        /** @var array<string, string> $pendingSids UPPER(sid) => original sid */
        $pendingSids = [];

        foreach ($companies as $company) {
            $companyId = $this->extractCompanyId($company);
            if ($companyId === null) {
                continue;
            }

            try {
                $employees = $this->apiClient->getEmployees($token, $companyId);
            } catch (Throwable $e) {
                report($e);
                $summary['failed']++;
                $this->pushError($summary, 'Company '.$companyId.': '.$e->getMessage());

                continue;
            }

            foreach ($employees as $employee) {
                $sid = $this->extractSidFromListRow($employee);
                if ($sid === '') {
                    $summary['skipped_invalid']++;

                    continue;
                }
                $pendingSids[mb_strtoupper($sid)] = $sid;
            }
        }

        $allSids = array_values($pendingSids);
        $summary['sids_seen'] = count($allSids);

        // Deteksi karyawan resign/mutasi keluar: hanya jalan kalau semua company
        // berhasil diambil, supaya kegagalan API tidak salah menonaktifkan orang.
        if ($summary['failed'] === 0 && $allSids !== []) {
            $summary['deactivated'] = $this->employeeProfileService->deactivateMissingFromHse(
                array_keys($pendingSids)
            );

            Log::info('evaluasi_well.hse_sync.deactivated', [
                'deactivated' => $summary['deactivated'],
            ]);
        }

        $existingMap = $this->employeeProfileService->existingKodeSidMap($allSids);

        Log::info('evaluasi_well.hse_sync.sids_ready', [
            'seen' => $summary['sids_seen'],
            'existing' => count($existingMap),
            'new' => max(0, $summary['sids_seen'] - count($existingMap)),
        ]);

        $delayMs = max(0, (int) config('services.evaluasi_well_hse.detail_delay_ms', 50));
        $sids = array_values($pendingSids);

        foreach ($sids as $index => $sid) {
            try {
                $detail = $this->apiClient->getEmployeeDetailBySid($token, $sid);

                if ($index === 0) {
                    // Cuplikan sekali di awal sync agar asumsi nama field (mapDetailToWritable)
                    // bisa diverifikasi terhadap bentuk respons API yang sebenarnya, bukan tebakan.
                    $employeeSample = data_get($detail, 'employee');
                    Log::info('evaluasi_well.hse_sync.detail_sample', [
                        'sid' => $sid,
                        'detail_keys' => array_keys($detail),
                        'employee_keys' => is_array($employeeSample) ? array_keys($employeeSample) : null,
                    ]);
                }

                $payload = $this->mapDetailToWritable($detail, $sid);

                if ($payload === null) {
                    $summary['skipped_invalid']++;
                    $this->pushError($summary, 'SID '.$sid.': data tidak lengkap (nama/SID).');
                } else {
                    $this->upsertEmployee($payload, $existingMap, $summary);
                }
            } catch (Throwable $e) {
                report($e);
                $summary['failed']++;
                $this->pushError($summary, 'SID '.$sid.': '.$e->getMessage());
            }

            if ($delayMs > 0 && $index < count($sids) - 1) {
                usleep($delayMs * 1000);
            }

            if (($index + 1) % 100 === 0) {
                Log::info('evaluasi_well.hse_sync.progress', [
                    'processed' => $index + 1,
                    'total' => count($sids),
                    'inserted' => $summary['inserted'],
                    'updated' => $summary['updated'],
                    'failed' => $summary['failed'],
                ]);
            }
        }

        Log::info('evaluasi_well.hse_sync.finished', [
            'inserted' => $summary['inserted'],
            'updated' => $summary['updated'],
            'skipped_invalid' => $summary['skipped_invalid'],
            'failed' => $summary['failed'],
            'deactivated' => $summary['deactivated'],
        ]);

        return $summary;
    }

    /**
     * Insert SID baru atau update baris existing. id BeWell tidak pernah diubah.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, array{id: int, kode_sid: string}>  $existingMap
     * @param  array{inserted: int, updated: int, errors: list<string>}  $summary
     */
    private function upsertEmployee(array $payload, array &$existingMap, array &$summary): void
    {
        $upper = mb_strtoupper(trim((string) $payload['kode_sid']));
        $existing = $existingMap[$upper] ?? $this->employeeProfileService->findExistingByKodeSid(
            (string) $payload['kode_sid']
        );

        if ($existing !== null) {
            // Pertahankan kode_sid tersimpan agar login/password BeWell tidak bergeser
            // hanya karena perbedaan huruf besar-kecil dari HSE.
            $payload['kode_sid'] = $existing['kode_sid'];
            $this->employeeProfileService->update($existing['id'], $payload, false);
            $summary['updated']++;
            $existingMap[$upper] = $existing;

            return;
        }

        try {
            $id = $this->employeeProfileService->create($payload);
            $summary['inserted']++;
            $existingMap[$upper] = [
                'id' => $id,
                'kode_sid' => (string) $payload['kode_sid'],
            ];
        } catch (QueryException $e) {
            // Race: SID terisi antara lookup dan insert — update baris yang sudah ada.
            $raced = $this->employeeProfileService->findExistingByKodeSid((string) $payload['kode_sid']);
            if ($raced === null) {
                throw $e;
            }

            $payload['kode_sid'] = $raced['kode_sid'];
            $this->employeeProfileService->update($raced['id'], $payload, false);
            $summary['updated']++;
            $existingMap[$upper] = $raced;
        }
    }

    /**
     * @param  array<string, mixed>  $company
     */
    private function extractCompanyId(array $company): int|string|null
    {
        foreach (['id', 'companyId', 'company_id', 'idCompany', 'id_company'] as $key) {
            $value = $company[$key] ?? null;
            if (is_int($value) || (is_string($value) && trim($value) !== '')) {
                return is_int($value) ? $value : trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function extractSidFromListRow(array $row): string
    {
        $candidates = [
            data_get($row, 'sid'),
            data_get($row, 'sidCode'),
            data_get($row, 'kodeSid'),
            data_get($row, 'kode_sid'),
            data_get($row, 'employeeSid'),
            data_get($row, 'employee.sid'),
            data_get($row, 'employee.sidCode'),
            data_get($row, 'employee.kodeSid'),
            data_get($row, 'employee.kode_sid'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) || is_numeric($candidate)) {
                $sid = trim((string) $candidate);
                if ($sid !== '') {
                    return $sid;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>|null
     */
    private function mapDetailToWritable(array $detail, string $fallbackSid): ?array
    {
        $employee = data_get($detail, 'employee');
        if (! is_array($employee)) {
            $employee = $detail;
        }

        $sid = $this->firstNonEmptyString([
            data_get($detail, 'sid'),
            data_get($detail, 'sidCode'),
            data_get($employee, 'sid'),
            data_get($employee, 'sidCode'),
            data_get($employee, 'kodeSid'),
            data_get($employee, 'kode_sid'),
            $fallbackSid,
        ]);

        $nama = $this->firstNonEmptyString([
            data_get($employee, 'name'),
            data_get($employee, 'nama'),
            data_get($employee, 'fullName'),
            data_get($employee, 'full_name'),
            data_get($detail, 'name'),
            data_get($detail, 'nama'),
        ]);

        if ($sid === '' || $nama === '') {
            return null;
        }

        $nik = $this->extractNik($detail, $employee);
        $site = $this->firstNonEmptyString([
            data_get($detail, 'dedicatedSite.name'),
            data_get($detail, 'dedicatedSite.nama'),
            data_get($detail, 'dedicatedSite'),
            data_get($employee, 'dedicatedSite.name'),
            data_get($employee, 'site'),
            data_get($employee, 'siteName'),
        ]);

        $companyId = data_get($employee, 'company.id')
            ?? data_get($employee, 'company.companyId')
            ?? data_get($employee, 'idCompany')
            ?? data_get($employee, 'companyId');

        $companyName = $this->firstNonEmptyString([
            data_get($employee, 'company.name'),
            data_get($employee, 'company.nama'),
            data_get($employee, 'company.companyName'),
            data_get($employee, 'namaPerusahaan'),
            data_get($employee, 'nama_perusahaan'),
        ]);

        $departement = $this->firstNonEmptyString([
            data_get($employee, 'department.name'),
            data_get($employee, 'department.nama'),
            data_get($employee, 'departement'),
            data_get($employee, 'departmentName'),
        ]);

        $jabatanFungsional = $this->firstNonEmptyString([
            data_get($employee, 'functionalPosition.name'),
            data_get($employee, 'functionalPosition.nama'),
            data_get($employee, 'jabatanFungsional'),
            data_get($employee, 'jabatan_fungsional'),
        ]);

        $jabatanStruktural = $this->firstNonEmptyString([
            data_get($employee, 'structuralPosition.name'),
            data_get($employee, 'structuralPosition.nama'),
            data_get($employee, 'jabatanStruktural'),
            data_get($employee, 'jabatan_struktural'),
        ]);

        $divisi = $this->firstNonEmptyString([
            data_get($employee, 'division.name'),
            data_get($employee, 'division.nama'),
            data_get($employee, 'divisi'),
            data_get($employee, 'divisionName'),
        ]);

        $status = $this->normalizeStatus(
            $this->firstNonEmptyString([
                data_get($employee, 'status.name'),
                data_get($employee, 'status.nama'),
                data_get($employee, 'status'),
                data_get($employee, 'statusKaryawan'),
                data_get($employee, 'status_karyawan'),
            ])
        );

        $usia = data_get($employee, 'usia') ?? data_get($employee, 'age');
        $masaKerja = data_get($employee, 'masaKerja')
            ?? data_get($employee, 'masa_kerja')
            ?? data_get($employee, 'yearsOfService');

        $payload = [
            'kode_sid' => $sid,
            'nama' => $nama,
            'status_karyawan' => $status,
        ];

        if ($nik !== '') {
            $payload['nik'] = $nik;
        }
        if ($site !== '') {
            $payload['site'] = mb_substr($site, 0, 100);
        }
        if ($companyName !== '') {
            $payload['nama_perusahaan'] = mb_substr($companyName, 0, 255);
        }
        if (is_numeric($companyId)) {
            $payload['id_perusahaan'] = (int) $companyId;
        }
        if ($departement !== '') {
            $payload['departement'] = mb_substr($departement, 0, 255);
        }
        if ($jabatanFungsional !== '') {
            $payload['jabatan_fungsional'] = mb_substr($jabatanFungsional, 0, 255);
        }
        if ($jabatanStruktural !== '') {
            $payload['jabatan_struktural'] = mb_substr($jabatanStruktural, 0, 255);
        }
        if ($divisi !== '') {
            $payload['divisi'] = mb_substr($divisi, 0, 255);
        }
        if (is_numeric($usia)) {
            $payload['usia'] = (int) $usia;
        }
        if (is_string($masaKerja) || is_numeric($masaKerja)) {
            $payload['masa_kerja'] = mb_substr(trim((string) $masaKerja), 0, 100);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  array<string, mixed>  $employee
     */
    private function extractNik(array $detail, array $employee): string
    {
        $direct = $this->firstNonEmptyString([
            data_get($employee, 'nik'),
            data_get($employee, 'noKtp'),
            data_get($employee, 'ktp'),
            data_get($detail, 'nik'),
        ]);
        if ($direct !== '') {
            return $direct;
        }

        $identities = data_get($detail, 'identities');
        if (! is_array($identities)) {
            $identities = data_get($employee, 'identities');
        }
        if (! is_array($identities)) {
            return '';
        }

        foreach ($identities as $identity) {
            if (! is_array($identity)) {
                continue;
            }
            $type = mb_strtolower(trim((string) (
                $identity['type']
                ?? $identity['jenis']
                ?? $identity['identityType']
                ?? $identity['name']
                ?? ''
            )));
            $value = $this->firstNonEmptyString([
                $identity['value'] ?? null,
                $identity['number'] ?? null,
                $identity['nomor'] ?? null,
                $identity['nik'] ?? null,
            ]);
            if ($value === '') {
                continue;
            }
            if ($type === '' || str_contains($type, 'nik') || str_contains($type, 'ktp')) {
                return $value;
            }
        }

        return '';
    }

    private function normalizeStatus(string $raw): string
    {
        $value = mb_strtoupper(trim($raw));
        if ($value === '') {
            return 'AKTIF';
        }

        if (str_contains($value, 'NON') || str_contains($value, 'INACTIVE') || str_contains($value, 'TIDAK')) {
            return 'NONAKTIF';
        }

        return 'AKTIF';
    }

    /**
     * @param  list<mixed>  $candidates
     */
    private function firstNonEmptyString(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                continue;
            }
            if ($candidate === null) {
                continue;
            }
            $value = trim((string) $candidate);
            if ($value !== '' && $value !== '-') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array{errors: list<string>}  $summary
     */
    private function pushError(array &$summary, string $message): void
    {
        if (count($summary['errors']) >= 50) {
            return;
        }
        $summary['errors'][] = $message;
    }
}
