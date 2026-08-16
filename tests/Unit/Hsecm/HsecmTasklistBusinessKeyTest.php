<?php

declare(strict_types=1);

namespace Tests\Unit\Hsecm;

use App\Services\Hsecm\HsecmDatabaseRepository;
use App\Services\Hsecm\HsecmTasklistService;
use Mockery;
use PHPUnit\Framework\TestCase;

class HsecmTasklistBusinessKeyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(): HsecmTasklistService
    {
        return new HsecmTasklistService(Mockery::mock(HsecmDatabaseRepository::class));
    }

    public function test_keeps_short_business_key_unchanged(): void
    {
        $key = 'K49RF|HO|2026-08-12';

        $this->assertSame($key, $this->service()->compactBusinessKey($key));
    }

    public function test_hashes_tbc_business_key_that_exceeds_varchar_255(): void
    {
        $raw = 'Post Event BE DMS Unit BUS UT-501_Tgl 12-08-2026_Jam 08:36:43 WITA_Jalan Negara.'."\n"
            .'Terekam melalui CCTV, driver terlihat menunduk/melihat ke arah bawah sebanyak 2 kali saat unit beroperasi, yang mengindikasikan kemungkinan adanya aktivitas penggunaan handphone. Segera di follow up dan klarifikasi kepada driver untuk memastikan aktivitas yang dilakukan.|HO|8/12/2026|K49RF';

        $service = $this->service();
        $stored = $service->compactBusinessKey($raw);

        $this->assertGreaterThan(HsecmTasklistService::BUSINESS_KEY_STORAGE_MAX, mb_strlen($raw));
        $this->assertStringStartsWith('sha256:', $stored);
        $this->assertSame(71, strlen($stored));
        $this->assertSame($stored, $service->compactBusinessKey($raw));
    }

    public function test_keeps_exact_255_character_key(): void
    {
        $key = str_repeat('a', 255);

        $this->assertSame($key, $this->service()->compactBusinessKey($key));
    }
}
