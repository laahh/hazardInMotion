<?php

declare(strict_types=1);

namespace App\Services\SportEvaluation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Klien HTTP read-only ke ClickHouse OBDS (koneksi `clickhouse_obds`, 10.42.7.39)
 * untuk data tap RFID di `obds.ch_rfid_sap_cuti_all_site` (baris jenis_data = 'RFID').
 */
final class RfidClickHouseClient
{
    public const CONNECTION = 'clickhouse_obds';

    private const UP_CACHE_KEY = 'evaluasi_well:rfid_clickhouse_up_v1';

    private const UP_CACHE_TTL = 20;

    private const CONNECT_TIMEOUT = 3;

    public function up(): bool
    {
        try {
            return (bool) Cache::remember(self::UP_CACHE_KEY, self::UP_CACHE_TTL, fn (): bool => $this->ping());
        } catch (Throwable $e) {
            report($e);

            return $this->ping();
        }
    }

    /**
     * Jalankan SELECT dan kembalikan baris sebagai array asosiatif.
     *
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException bila konfigurasi kosong, koneksi gagal, atau ClickHouse mengembalikan error
     */
    public function select(string $sql): array
    {
        $body = $this->kirim(rtrim(trim($sql), ';').' FORMAT JSONEachRow', (int) ($this->config()['options']['timeout'] ?? 60));

        $rows = [];
        foreach (preg_split('/\r?\n/', $body) as $line) {
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (! is_array($row)) {
                throw new RuntimeException('Baris ClickHouse tidak valid: '.mb_substr($line, 0, 200));
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** Literal string ClickHouse yang aman (escape backslash dan kutip tunggal). */
    public static function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    private function ping(): bool
    {
        try {
            return trim($this->kirim('SELECT 1', 5)) === '1';
        } catch (Throwable) {
            return false;
        }
    }

    private function kirim(string $sql, int $timeout): string
    {
        $c = $this->config();
        $host = (string) ($c['host'] ?? '');
        $port = (string) ($c['port'] ?? '');
        if ($host === '' || $port === '') {
            throw new RuntimeException('Koneksi '.self::CONNECTION.' belum dikonfigurasi (host/port kosong).');
        }

        $url = ($c['options']['protocol'] ?? 'http').'://'.$host.':'.$port.'/';
        $response = Http::connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(max(5, $timeout))
            ->withBasicAuth((string) ($c['username'] ?? 'default'), (string) ($c['password'] ?? ''))
            ->withQueryParameters(['database' => (string) ($c['database'] ?? 'obds')])
            ->withBody($sql, 'text/plain; charset=utf-8')
            ->post($url);

        if (! $response->successful()) {
            throw new RuntimeException('ClickHouse '.self::CONNECTION.' HTTP '.$response->status().': '.mb_substr(trim($response->body()), 0, 300));
        }

        return $response->body();
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return (array) config('database.connections.'.self::CONNECTION, []);
    }
}
