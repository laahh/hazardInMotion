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
        $this->assertSame(3307, $meta['local_port']);
        $this->assertNotSame('', $meta['ssh_host']);
        $this->assertNotSame('', $meta['remote_host']);
        $this->assertArrayNotHasKey('password', $meta);
        $this->assertArrayNotHasKey('ssh_pkey_contents', $meta);
    }

    public function test_runtime_config_rewrites_direct_mysql_host_to_loopback_tunnel(): void
    {
        config([
            'database.connections.besigma_db.host' => '10.11.58.139',
            'database.connections.besigma_db.port' => 3306,
            'database.connections.besigma_db.remote_host' => '10.11.58.139',
            'database.connections.besigma_db.local_port' => 3307,
            'database.connections.besigma_db.ssh_host' => '13.250.29.29',
        ]);

        app(\App\Services\Besigma\BesigmaTunnelService::class)->applyRuntimeConfig();

        $this->assertSame('127.0.0.1', config('database.connections.besigma_db.host'));
        $this->assertSame(3307, (int) config('database.connections.besigma_db.port'));
        $this->assertSame('52.74.245.15', config('database.connections.besigma_db.ssh_host'));
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
                'engine' => 'InnoDB',
                'approx_rows' => 12,
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint', 'key' => 'PRI', 'nullable' => false, 'extra' => 'auto_increment'],
                    ['name' => 'polygon', 'type' => 'json', 'key' => '', 'nullable' => true, 'extra' => ''],
                ],
            ],
        ]);

        $this->assertStringContainsString('boundaries (BASE TABLE, InnoDB, ~12 rows, 2 cols)', $text);
        $this->assertStringContainsString('id bigint PRI NOT NULL auto_increment', $text);
        $this->assertStringContainsString('polygon json NULL', $text);
    }
}
