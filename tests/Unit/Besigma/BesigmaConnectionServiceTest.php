<?php

declare(strict_types=1);

namespace Tests\Unit\Besigma;

use App\Services\Besigma\BesigmaConnectionService;
use Tests\TestCase;

final class BesigmaConnectionServiceTest extends TestCase
{
    public function test_connection_name_is_besigma_db(): void
    {
        $this->assertSame('besigma_db', BesigmaConnectionService::CONNECTION);
    }

    public function test_tunnel_meta_maps_config_without_password(): void
    {
        $meta = app(BesigmaConnectionService::class)->tunnelMeta();

        $this->assertSame('127.0.0.1', $meta['local_host']);
        $this->assertSame(5433, $meta['local_port']);
        $this->assertNotSame('', $meta['ssh_host']);
        $this->assertNotSame('', $meta['remote_host']);
        $this->assertArrayNotHasKey('password', $meta);
        $this->assertArrayNotHasKey('ssh_pkey_contents', $meta);
    }

    public function test_target_meta_uses_olap_besigma_database(): void
    {
        $target = app(BesigmaConnectionService::class)->targetMeta();

        $this->assertSame('pgsql', $target['driver']);
        $this->assertSame('127.0.0.1', $target['host']);
        $this->assertSame(5433, $target['port']);
        $this->assertSame('besigma', $target['database']);
        $this->assertSame('safety_evaluator_2', $target['username']);
    }

    public function test_runtime_config_rewrites_direct_rds_host_to_loopback_tunnel(): void
    {
        config([
            'database.connections.besigma_db.host' => 'postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com',
            'database.connections.besigma_db.port' => 5432,
            'database.connections.besigma_db.remote_host' => 'postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com',
            'database.connections.besigma_db.local_port' => 5433,
            'database.connections.besigma_db.ssh_host' => '52.74.245.15',
        ]);

        app(\App\Services\Besigma\BesigmaTunnelService::class)->applyRuntimeConfig();

        $this->assertSame('127.0.0.1', config('database.connections.besigma_db.host'));
        $this->assertSame(5433, (int) config('database.connections.besigma_db.port'));
        $this->assertSame('13.212.87.127', config('database.connections.besigma_db.ssh_host'));
    }

    public function test_probe_returns_diagnostic_keys(): void
    {
        $probe = app(BesigmaConnectionService::class)->probe();

        $this->assertArrayHasKey('connected', $probe);
        $this->assertArrayHasKey('tcp_reachable', $probe);
        $this->assertArrayHasKey('key_exists', $probe);
        $this->assertArrayHasKey('tunnel', $probe);
        $this->assertArrayHasKey('schema', $probe);
        $this->assertIsBool($probe['connected']);
        $this->assertIsArray($probe['tables']);
        $this->assertIsArray($probe['schema']);

        if (! $probe['connected']) {
            $this->assertNotEmpty($probe['error']);
            $this->assertNotEmpty($probe['hint']);
        }
    }

    public function test_schema_as_text_lists_table_and_columns(): void
    {
        $text = app(BesigmaConnectionService::class)->schemaAsText([
            [
                'name' => 'boundaries',
                'type' => 'BASE TABLE',
                'engine' => null,
                'approx_rows' => 12,
                'columns' => [
                    ['name' => 'id', 'type' => 'uuid', 'key' => 'PRI', 'nullable' => false, 'extra' => ''],
                    ['name' => 'polygon', 'type' => 'json', 'key' => '', 'nullable' => true, 'extra' => ''],
                ],
            ],
        ]);

        $this->assertStringContainsString('boundaries (BASE TABLE, ~12 rows, 2 cols)', $text);
        $this->assertStringContainsString('id uuid PRI NOT NULL', $text);
        $this->assertStringContainsString('polygon json NULL', $text);
    }

    public function test_host_blocked_opens_circuit_so_is_up_does_not_retry(): void
    {
        $service = app(BesigmaConnectionService::class);
        $service->rememberFailure(new \RuntimeException('FATAL: too many connections for role "safety_evaluator_2"'));

        $this->assertFalse($service->isUp());
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has('besigma:circuit_v1'));
    }
}
