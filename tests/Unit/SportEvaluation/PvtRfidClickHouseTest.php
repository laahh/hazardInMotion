<?php

declare(strict_types=1);

namespace Tests\Unit\SportEvaluation;

use App\Services\SportEvaluation\RfidClickHouseClient;
use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader as Rfid;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tap RFID dibaca dari ClickHouse OBDS (obds.aaj_vw_checkinout_rfid), bukan view Postgres OLAP.
 */
class PvtRfidClickHouseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'database.connections.clickhouse_obds.host' => 'ch.uji', 'database.connections.clickhouse_obds.port' => '8123']);
        Cache::flush();
    }

    public function test_check_in_harian_dibaca_dari_clickhouse_satu_baris_per_sid(): void
    {
        $sql = [];
        Http::fake(function (Request $req) use (&$sql) {
            $sql[] = $req->body();
            if (trim($req->body()) === 'SELECT 1') {
                return Http::response("1\n");
            }

            return Http::response(
                json_encode(['kode_sid' => 'AB12C', 'tanggal_checkinout' => '2026-11-11 05:40:12.000', 'nama_karyawan' => 'Uji', 'perusahaan' => 'PT Uji', 'gate' => 'Gate A', 'jenis_checkinout' => 'CHECK IN', 'status_lolos' => 'PASSED'])."\n"
            );
        });

        $hasil = app(Rfid::class)->firstPassedCheckinsForDate('2026-11-11');

        $this->assertSame(['AB12C'], array_keys($hasil));
        $this->assertSame('2026-11-11 05:40:12', $hasil['AB12C']['checked_in_at']);
        $query = end($sql);
        $this->assertStringContainsString('FROM obds.aaj_vw_checkinout_rfid FINAL', $query);
        $this->assertStringContainsString("date >= toDateTime64('2026-11-11 00:00:00', 3)", $query);
        $this->assertStringContainsString('LIMIT 1 BY upperUTF8(kode_sid)', $query);
        $this->assertStringEndsWith('FORMAT JSONEachRow', trim($query));
    }

    public function test_daftar_sid_di_escape_dan_clickhouse_mati_mengembalikan_kosong(): void
    {
        $this->assertSame("'O\\'NEIL\\\\X'", RfidClickHouseClient::quote("O'NEIL\\X"));

        Http::fake(fn () => Http::response('error', 500));
        $rfid = app(Rfid::class);

        $this->assertFalse($rfid->rfidUp());
        $this->assertSame([], $rfid->lastPassedForSidsBetween('2026-11-11 00:00:00', '2026-11-12 00:00:00', ['AB12C']));
    }
}
