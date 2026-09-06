<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Enums\ControlRoomShiftCode;
use App\Services\ControlRoom\ControlRoomRfidCheckinoutReader;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class ControlRoomRfidCheckinoutReaderTest extends TestCase
{
    public function test_jendela_shift_1_termasuk_grace_dua_jam(): void
    {
        $window = $this->reader()->window(CarbonImmutable::parse('2026-08-31'), ControlRoomShiftCode::S1);

        $this->assertSame('2026-08-31 04:00:00', $window['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-31 20:00:00', $window['end']->format('Y-m-d H:i:s'));
    }

    public function test_jendela_shift_2_lintas_tengah_malam_termasuk_checkout_pagi(): void
    {
        $window = $this->reader()->window(CarbonImmutable::parse('2026-08-31'), ControlRoomShiftCode::S2);
        $checkoutPagi = CarbonImmutable::parse('2026-09-01 07:30:00');

        $this->assertSame('2026-08-31 16:00:00', $window['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-01 08:00:00', $window['end']->format('Y-m-d H:i:s'));
        $this->assertTrue($checkoutPagi->gte($window['start']) && $checkoutPagi->lt($window['end']));
    }

    public function test_tap_shift_2_masuk_slot_tugas_bukan_tanggal_kalender_checkout(): void
    {
        $reader = $this->reader();
        $dutyDate = CarbonImmutable::parse('2026-08-31');
        $grouped = $reader->groupRowsIntoSlots(
            [
                ['sid' => 'FJAVJ', 'date' => $dutyDate, 'shift' => ControlRoomShiftCode::S1],
                ['sid' => '9ETFJ', 'date' => $dutyDate, 'shift' => ControlRoomShiftCode::S2],
            ],
            [
                (object) [
                    'kode_sid' => 'FJAVJ',
                    'jenis_checkinout' => 'CHECK IN',
                    'tanggal_checkinout' => '2026-08-31 07:30:00',
                    'gate' => 'POS 1',
                    'status_lolos' => 'PASSED',
                ],
                (object) [
                    'kode_sid' => 'FJAVJ',
                    'jenis_checkinout' => 'CHECK OUT',
                    'tanggal_checkinout' => '2026-08-31 12:20:00',
                    'gate' => 'POS 1',
                    'status_lolos' => 'PASSED',
                ],
                (object) [
                    'kode_sid' => 'FJAVJ',
                    'jenis_checkinout' => 'CHECK IN',
                    'tanggal_checkinout' => '2026-08-31 12:58:00',
                    'gate' => 'POS 1',
                    'status_lolos' => 'PASSED',
                ],
                (object) [
                    'kode_sid' => 'FJAVJ',
                    'jenis_checkinout' => 'CHECK OUT',
                    'tanggal_checkinout' => '2026-08-31 17:32:00',
                    'gate' => 'POS 1',
                    'status_lolos' => 'PASSED',
                ],
                (object) [
                    'kode_sid' => '9ETFJ',
                    'jenis_checkinout' => 'CHECK IN',
                    'tanggal_checkinout' => '2026-08-31 18:01:00',
                    'gate' => 'POS 2',
                    'status_lolos' => 'PASSED',
                ],
                (object) [
                    'kode_sid' => '9ETFJ',
                    'jenis_checkinout' => 'CHECK OUT',
                    'tanggal_checkinout' => '2026-09-01 07:30:00',
                    'gate' => 'POS 2',
                    'status_lolos' => 'PASSED',
                ],
                (object) [
                    'kode_sid' => 'FJAVJ',
                    'jenis_checkinout' => 'CHECK IN',
                    'tanggal_checkinout' => '2026-09-01 07:25:00',
                    'gate' => 'POS 1',
                    'status_lolos' => 'PASSED',
                ],
            ],
        );

        $s1 = $grouped[$reader->slotKey($dutyDate, ControlRoomShiftCode::S1, 'FJAVJ')];
        $s2 = $grouped[$reader->slotKey($dutyDate, ControlRoomShiftCode::S2, '9ETFJ')];

        $this->assertCount(4, $s1);
        $this->assertSame(['in', 'out', 'in', 'out'], array_column($s1, 'type'));
        $this->assertSame(['07:30', '12:20', '12:58', '17:32'], array_column($s1, 'time'));

        $this->assertCount(2, $s2);
        $this->assertSame('in', $s2[0]['type']);
        $this->assertSame('18:01', $s2[0]['time']);
        $this->assertSame('out', $s2[1]['type']);
        $this->assertSame('07:30', $s2[1]['time']);
        $this->assertSame('01 Sep', $s2[1]['date_label']);
        $this->assertSame(['07:30', '12:20', '12:58', '17:32'], array_column($s1, 'time'));
        $this->assertNotContains('07:25', array_column($s1, 'time'));
    }

    private function reader(): ControlRoomRfidCheckinoutReader
    {
        return new ControlRoomRfidCheckinoutReader(new PembatasanLVOlapQuery());
    }
}
