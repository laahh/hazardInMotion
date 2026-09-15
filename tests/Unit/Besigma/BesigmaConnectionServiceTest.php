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

    public function test_target_meta_uses_direct_rds_defaults(): void
    {
        $target = app(BesigmaConnectionService::class)->targetMeta();

        $this->assertSame('pgsql', $target['driver']);
        $this->assertSame('direct', $target['mode']);
        $this->assertSame('besigma', $target['database']);
        $this->assertStringContainsString('besigma_db', $target['search_path']);
        $this->assertSame('safety_evaluator_2', $target['username']);
        $this->assertSame(
            config('database.connections.besigma_db.host'),
            $target['host']
        );
        $this->assertSame(
            (int) config('database.connections.besigma_db.port'),
            $target['port']
        );
    }

    public function test_runtime_config_rewrites_legacy_tunnel_to_direct_rds(): void
    {
        config([
            'database.connections.besigma_db.host' => '127.0.0.1',
            'database.connections.besigma_db.port' => 3307,
        ]);

        app(\App\Services\Besigma\BesigmaTunnelService::class)->applyRuntimeConfig();

        $this->assertSame(
            'postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com',
            config('database.connections.besigma_db.host')
        );
        $this->assertSame(5432, (int) config('database.connections.besigma_db.port'));
    }

    public function test_probe_returns_diagnostic_keys(): void
    {
        $probe = app(BesigmaConnectionService::class)->probe();

        $this->assertArrayHasKey('connected', $probe);
        $this->assertArrayHasKey('tcp_reachable', $probe);
        $this->assertArrayHasKey('target', $probe);
        $this->assertArrayHasKey('schema', $probe);
        $this->assertIsBool($probe['connected']);
        $this->assertIsArray($probe['tables']);
        $this->assertIsArray($probe['schema']);
        $this->assertSame('direct', $probe['target']['mode'] ?? null);

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

    public function test_sql_error_does_not_open_circuit(): void
    {
        $service = app(BesigmaConnectionService::class);
        $service->forgetCachedStatus();
        $service->rememberFailure(new \RuntimeException('SQLSTATE[42P01]: Undefined table: relation "boundary_status" does not exist'));

        $this->assertFalse(\Illuminate\Support\Facades\Cache::has('besigma:circuit_v1'));
    }
}
