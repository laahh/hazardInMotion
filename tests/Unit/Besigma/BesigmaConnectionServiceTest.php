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

    public function test_probe_returns_diagnostic_keys(): void
    {
        $probe = app(BesigmaConnectionService::class)->probe();

        $this->assertArrayHasKey('connected', $probe);
        $this->assertArrayHasKey('tcp_reachable', $probe);
        $this->assertArrayHasKey('key_exists', $probe);
        $this->assertArrayHasKey('tunnel', $probe);
        $this->assertArrayHasKey('tables', $probe);
        $this->assertIsBool($probe['connected']);
        $this->assertIsArray($probe['tables']);

        if (! $probe['connected']) {
            $this->assertNotEmpty($probe['error']);
            $this->assertNotEmpty($probe['hint']);
        }
    }
}
