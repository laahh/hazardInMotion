<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomInspeksiHazardToolFilter;
use Tests\TestCase;

final class ControlRoomInspeksiHazardToolFilterTest extends TestCase
{
    public function test_delapan_tools_ocr_diterima(): void
    {
        $this->assertTrue(ControlRoomInspeksiHazardToolFilter::matches('Real Time - CCTV Support'));
        $this->assertTrue(ControlRoomInspeksiHazardToolFilter::matches('Real Time - Mining Eyes'));
        $this->assertTrue(ControlRoomInspeksiHazardToolFilter::matches('Real Time - DMS'));
        $this->assertTrue(ControlRoomInspeksiHazardToolFilter::matches('Real Time - CCTV Portable'));
        $this->assertTrue(ControlRoomInspeksiHazardToolFilter::matches('Post Event - CCTV Support'));
        $this->assertTrue(ControlRoomInspeksiHazardToolFilter::matches('Post Event - Mining Eyes'));
        $this->assertTrue(ControlRoomInspeksiHazardToolFilter::matches('Post Event - DMS'));
        $this->assertTrue(ControlRoomInspeksiHazardToolFilter::matches('post event - cctv portable'));
    }

    public function test_pengawasan_langsung_ditolak(): void
    {
        $this->assertFalse(ControlRoomInspeksiHazardToolFilter::matches('Pengawasan Langsung'));
        $this->assertFalse(ControlRoomInspeksiHazardToolFilter::matches('Real Time - Teropong'));
        $this->assertFalse(ControlRoomInspeksiHazardToolFilter::matches(''));
    }

    public function test_sql_predicate_menerima_kolom_bertabel(): void
    {
        $pred = ControlRoomInspeksiHazardToolFilter::sqlPredicate('o.tools_observasi');

        $this->assertStringContainsString('o.tools_observasi IN (', $pred['sql']);
        $this->assertStringNotContainsString('BTRIM', $pred['sql']);
        $this->assertNotSame('FALSE', $pred['sql']);
        $this->assertCount(8, $pred['bindings']);
    }

    public function test_sql_predicate_menolak_kolom_tidak_aman(): void
    {
        $pred = ControlRoomInspeksiHazardToolFilter::sqlPredicate('tools_observasi; DROP TABLE x');

        $this->assertSame('FALSE', $pred['sql']);
        $this->assertSame([], $pred['bindings']);
    }
}
