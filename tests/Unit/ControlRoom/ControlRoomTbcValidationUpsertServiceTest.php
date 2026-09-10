<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Models\ControlRoom\ControlRoomTbcValidation;
use App\Services\ControlRoom\ControlRoomTbcValidationUpsertService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ControlRoomTbcValidationUpsertServiceTest extends TestCase
{
    public function test_tasklist_yang_sama_mengupdate_baris_lama(): void
    {
        if (! Schema::hasTable('control_room_tbc_validations')) {
            $this->markTestSkipped('Jalankan migrate control_room_tbc_validations dulu.');
        }

        ControlRoomTbcValidation::query()->where('tasklist', 'OCR-TBC-TEST')->delete();

        $service = new ControlRoomTbcValidationUpsertService();
        $first = $service->upsert([
            ['tasklist' => 'OCR-TBC-TEST', 'validator' => 'A', 'to_be_concerned_hazard' => 'Lama'],
        ], null);
        $this->assertSame(1, $first->created);
        $this->assertSame(0, $first->updated);

        $second = $service->upsert([
            ['tasklist' => 'OCR-TBC-TEST', 'validator' => 'B', 'to_be_concerned_hazard' => 'Baru'],
        ], null);
        $this->assertSame(0, $second->created);
        $this->assertSame(1, $second->updated);

        $row = ControlRoomTbcValidation::query()->where('tasklist', 'OCR-TBC-TEST')->first();
        $this->assertNotNull($row);
        $this->assertSame('B', $row->validator);
        $this->assertSame('Baru', $row->to_be_concerned_hazard);

        $row->delete();
    }
}
