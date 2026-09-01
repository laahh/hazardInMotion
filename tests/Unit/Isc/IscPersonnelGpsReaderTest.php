<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Services\Isc\IscPersonnelGpsReader;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class IscPersonnelGpsReaderTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_today_window_uses_app_timezone_start_of_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 01:15:00', 'Asia/Makassar'));

        $this->assertSame('2026-09-01 00:00:00', IscPersonnelGpsReader::todayStart('Asia/Makassar'));
        $this->assertSame('2026-09-02 00:00:00', IscPersonnelGpsReader::tomorrowStart('Asia/Makassar'));
        $this->assertSame('2026-08-20 00:00:00', IscPersonnelGpsReader::dayStart('2026-08-20', 'Asia/Makassar'));
        $this->assertSame('2026-08-21 00:00:00', IscPersonnelGpsReader::dayEndExclusive('2026-08-20', 'Asia/Makassar'));
    }

    public function test_only_gps_updated_today_is_accepted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 13:00:00', 'Asia/Makassar'));

        $this->assertTrue(IscPersonnelGpsReader::isUpdatedToday('2026-09-01 00:00:00', 'Asia/Makassar'));
        $this->assertTrue(IscPersonnelGpsReader::isUpdatedToday('2026-09-01 12:59:59', 'Asia/Makassar'));
        $this->assertFalse(IscPersonnelGpsReader::isUpdatedToday('2026-08-31 23:59:59', 'Asia/Makassar'));
        $this->assertFalse(IscPersonnelGpsReader::isUpdatedToday('2026-09-02 00:00:00', 'Asia/Makassar'));
        $this->assertFalse(IscPersonnelGpsReader::isUpdatedToday(null, 'Asia/Makassar'));
        $this->assertFalse(IscPersonnelGpsReader::isUpdatedToday('', 'Asia/Makassar'));
    }
}
