<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomSapDetailTbcEnricher;
use App\Services\ControlRoom\Source\GSheetTbcReader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ControlRoomSapDetailTbcEnricherTest extends TestCase
{
    public function test_hazard_inspeksi_ditandai_sudah_atau_belum_tbc(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push("Tasklist,Kategori\n9374205,TBC\n", 200, ['Content-Type' => 'text/csv'])
                ->push("Tasklist\n9374205\n", 200, ['Content-Type' => 'text/csv']),
        ]);

        $payload = (new ControlRoomSapDetailTbcEnricher(
            new GSheetTbcReader(sheetId: 'fake-detail-tbc', gid: '0'),
        ))->enrich([
            'cards' => [
                ['id' => '9374205', 'type' => 'hazard', 'status' => 'CLOSED'],
                ['id' => '111', 'type' => 'inspeksi', 'status' => 'OPEN'],
                ['id' => '999', 'type' => 'observasi', 'status' => 'OPEN'],
            ],
        ]);

        $this->assertTrue($payload['tbc_loaded']);
        $this->assertSame('sudah', $payload['cards'][0]['tbc']);
        $this->assertSame('belum', $payload['cards'][1]['tbc']);
        $this->assertNull($payload['cards'][2]['tbc']);
        $this->assertCount(2, $payload['tbc_cards']);
        $this->assertSame(2, $payload['tbc_counts']['all']);
        $this->assertSame(1, $payload['tbc_counts']['sudah']);
        $this->assertSame(1, $payload['tbc_counts']['belum']);
    }

    public function test_tab_tbc_memakai_kartu_basis_bila_diberikan(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push("Tasklist,Kategori
500,TBC
", 200, ['Content-Type' => 'text/csv'])
                ->push("Tasklist
500
", 200, ['Content-Type' => 'text/csv']),
        ]);

        $payload = (new ControlRoomSapDetailTbcEnricher(
            new GSheetTbcReader(sheetId: 'fake-detail-tbc-basis', gid: '0'),
        ))->enrich([
            'cards' => [
                ['id' => '9374205', 'type' => 'hazard', 'status' => 'CLOSED'],
                ['id' => '999', 'type' => 'observasi', 'status' => 'OPEN'],
            ],
        ], [
            ['id' => '500', 'type' => 'hazard', 'status' => 'OPEN'],
            ['id' => '501', 'type' => 'inspeksi', 'status' => 'OPEN'],
        ]);

        $this->assertTrue($payload['tbc_loaded']);
        $this->assertSame(['500', '501'], array_column($payload['tbc_cards'], 'id'));
        $this->assertSame(['all' => 2, 'sudah' => 1, 'belum' => 1], $payload['tbc_counts']);
        $this->assertSame('belum', $payload['cards'][0]['tbc']);
        $this->assertNull($payload['cards'][1]['tbc']);
    }

    public function test_tanpa_hazard_inspeksi_tbc_tidak_dimuat(): void
    {
        $payload = (new ControlRoomSapDetailTbcEnricher(
            new GSheetTbcReader(sheetId: '', gid: '0'),
        ))->enrich([
            'cards' => [
                ['id' => '999', 'type' => 'observasi', 'status' => 'OPEN'],
            ],
        ]);

        $this->assertFalse($payload['tbc_loaded']);
        $this->assertSame([], $payload['tbc_cards']);
        $this->assertSame(0, $payload['tbc_counts']['all']);
    }
}
