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
}
