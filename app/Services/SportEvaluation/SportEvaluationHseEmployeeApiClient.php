<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Client HTTP ke HSE Automation (API key statis atau login Beats legacy).
 */
final class SportEvaluationHseEmployeeApiClient
{
    private const DETAIL_EXPAND = 'employee.functionalPosition,employee.structuralPosition,employee.department,employee.company,dedicatedSite,employee.status,identities,competencies,licences';

    /** Panjang body respons error yang disimpan di pesan exception/log — cukup untuk diagnosa, tidak membanjiri log. */
    private const ERROR_BODY_PREVIEW_LENGTH = 800;

    /** Jumlah percobaan total (percobaan pertama + retry) untuk 5xx/koneksi gagal — server HSE kadang timeout sesaat saat beban tinggi. */
    private const MAX_ATTEMPTS = 3;

    /** Jeda antar percobaan retry. */
    private const RETRY_DELAY_MICROSECONDS = 700_000;

    /**
     * Ambil token untuk header x-api-key.
     *
     * Prioritas:
     * 1. API key statis dari env
     * 2. Login Beats legacy
     */
    public function login(): string
    {
        $apiKey = trim((string) config('services.evaluasi_well_hse.api_key', ''));
        if ($apiKey !== '') {
            return $apiKey;
        }

        $username = trim((string) config('services.evaluasi_well_hse.username', ''));
        $password = (string) config('services.evaluasi_well_hse.password', '');

        if ($username === '' || $password === '') {
            throw new RuntimeException('Kredensial HSE belum dikonfigurasi. Isi EVALUASI_WELL_HSE_API_KEY atau fallback legacy EVALUASI_WELL_HSE_USERNAME / EVALUASI_WELL_HSE_PASSWORD.');
        }

        $url = $this->url((string) config('services.evaluasi_well_hse.login_path', '/beats/api/mobile/login'));

        try {
            $response = $this->sendWithRetry(fn () => $this->http()->post($url, [
                'username' => $username,
                'password' => $password,
            ]));
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Gagal menghubungi API login Beats: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw $this->failureException($response, 'Login Beats', $url);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Respons login Beats tidak valid.');
        }

        $token = $this->extractToken($json);
        if ($token === '') {
            Log::error('HSE Automation login Beats: token tidak ditemukan di respons', [
                'url' => $url,
                'keys' => array_keys($json),
                'success' => $json['success'] ?? null,
                'message' => $json['message'] ?? null,
                'privileges' => $json['privileges'] ?? null,
                'token_raw' => $json['token'] ?? null,
            ]);

            throw new RuntimeException('Token tidak ditemukan di respons login Beats.');
        }

        return $token;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCompanies(string $token): array
    {
        $size = max(1, (int) config('services.evaluasi_well_hse.company_page_size', 1000));
        $url = $this->url('/sid2/api/ftwApi/getCompany');

        try {
            $response = $this->sendWithRetry(fn () => $this->http($token)->get($url, [
                'page' => 1,
                'size' => $size,
            ]));
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Gagal mengambil daftar perusahaan HSE: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw $this->failureException($response, 'getCompany', $url);
        }

        $json = $response->json();

        return $this->extractList(is_array($json) ? $json : []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getEmployees(string $token, int|string $companyId): array
    {
        $size = max(1, (int) config('services.evaluasi_well_hse.employee_page_size', 30000));
        $url = $this->url('/sid2/api/ftwApi/getEmployee');

        try {
            $response = $this->sendWithRetry(fn () => $this->http($token)->get($url, [
                'companyId' => $companyId,
                'page' => 1,
                'size' => $size,
            ]));
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Gagal mengambil karyawan company '.$companyId.': '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw $this->failureException($response, 'getEmployee company '.$companyId, $url);
        }

        $json = $response->json();

        return $this->extractList(is_array($json) ? $json : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getEmployeeDetailBySid(string $token, string $sid): array
    {
        $sid = trim($sid);
        if ($sid === '') {
            throw new RuntimeException('SID kosong.');
        }

        $url = $this->url('/sid2/employeeInfo/bySid/'.rawurlencode($sid));

        try {
            $response = $this->sendWithRetry(fn () => $this->http($token)->get($url, [
                'expand' => self::DETAIL_EXPAND,
            ]));
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Gagal mengambil detail SID '.$sid.': '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw $this->failureException($response, 'Detail SID '.$sid, $url);
        }

        $json = $response->json();
        if (! is_array($json)) {
            return [];
        }

        // Beberapa endpoint membungkus di data / result / content.
        foreach (['data', 'result', 'content', 'employeeInfo'] as $key) {
            if (isset($json[$key]) && is_array($json[$key]) && ! array_is_list($json[$key])) {
                return $json[$key];
            }
        }

        return $json;
    }

    private function http(?string $token = null): PendingRequest
    {
        $timeout = max(10, (int) config('services.evaluasi_well_hse.timeout', 120));
        $request = Http::timeout($timeout)
            ->acceptJson()
            ->asJson();

        if ($token !== null && $token !== '') {
            $request = $request->withHeaders([
                'x-api-key' => $token,
            ]);
        }

        return $request;
    }

    private function url(string $path): string
    {
        $base = rtrim((string) config('services.evaluasi_well_hse.base_url', ''), '/');
        if ($base === '') {
            throw new RuntimeException('EVALUASI_WELL_HSE_BASE_URL belum dikonfigurasi.');
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $base.'/'.ltrim($path, '/');
    }

    /**
     * Kirim request dengan retry otomatis untuk kegagalan yang kemungkinan sesaat
     * (5xx dari server HSE atau exception koneksi/timeout). Respons 4xx tidak
     * di-retry karena mengulang tidak akan mengubah hasil.
     */
    private function sendWithRetry(\Closure $send): Response
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = $send();
            } catch (Throwable $e) {
                $lastException = $e;
                if ($attempt < self::MAX_ATTEMPTS) {
                    usleep(self::RETRY_DELAY_MICROSECONDS);

                    continue;
                }

                throw $e;
            }

            if ($response->serverError() && $attempt < self::MAX_ATTEMPTS) {
                usleep(self::RETRY_DELAY_MICROSECONDS);

                continue;
            }

            return $response;
        }

        throw $lastException ?? new RuntimeException('Permintaan gagal tanpa detail.');
    }

    /**
     * Bangun exception yang menyertakan status + cuplikan body respons, dan
     * mencatat detail lengkapnya ke log agar kegagalan API eksternal (HSE
     * Automation) bisa didiagnosa tanpa harus mereproduksi di server lain.
     */
    private function failureException(Response $response, string $context, string $url): RuntimeException
    {
        $status = $response->status();
        $body = mb_substr((string) $response->body(), 0, self::ERROR_BODY_PREVIEW_LENGTH);

        Log::error('HSE Automation API gagal: '.$context, [
            'url' => $url,
            'status' => $status,
            'body' => $body,
        ]);

        $message = $context.' gagal (HTTP '.$status.').';
        if ($body !== '') {
            $message .= ' Respons: '.$body;
        }

        return new RuntimeException($message);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractToken(array $json): string
    {
        $candidates = [
            data_get($json, 'token'),
            data_get($json, 'apiKey'),
            data_get($json, 'api_key'),
            data_get($json, 'x-api-key'),
            data_get($json, 'accessToken'),
            data_get($json, 'access_token'),
            data_get($json, 'data.token'),
            data_get($json, 'data.apiKey'),
            data_get($json, 'data.api_key'),
            data_get($json, 'data.accessToken'),
            data_get($json, 'data.access_token'),
            data_get($json, 'result.token'),
            data_get($json, 'result.apiKey'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    private function extractList(array $json): array
    {
        foreach (['data', 'content', 'result', 'items', 'rows', 'list'] as $key) {
            $value = $json[$key] ?? null;
            if (is_array($value) && array_is_list($value)) {
                return array_values(array_filter($value, 'is_array'));
            }
            // Nested pagination: data.content / data.data
            if (is_array($value) && ! array_is_list($value)) {
                foreach (['data', 'content', 'items', 'rows'] as $inner) {
                    $nested = $value[$inner] ?? null;
                    if (is_array($nested) && array_is_list($nested)) {
                        return array_values(array_filter($nested, 'is_array'));
                    }
                }
            }
        }

        if (array_is_list($json)) {
            return array_values(array_filter($json, 'is_array'));
        }

        return [];
    }
}
