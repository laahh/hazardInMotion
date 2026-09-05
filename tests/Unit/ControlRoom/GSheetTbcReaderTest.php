<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\Source\GSheetTbcReader;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class GSheetTbcReaderTest extends TestCase
{
    public function test_tanpa_sheet_id_melempar_exception_yang_jelas(): void
    {
        $reader = new GSheetTbcReader(sheetId: '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belum dikonfigurasi');

        $reader->fetch();
    }

    public function test_parse_csv_valid_menjadi_baris_asosiatif(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "Tanggal,Nama,Kategori\n2026-01-05,BUDI,TBC\n2026-01-06,SITI,PSPP\n",
                200,
                ['Content-Type' => 'text/csv']
            ),
        ]);

        $reader = new GSheetTbcReader(sheetId: 'fake-sheet-id', gid: '0');
        $rows = $reader->fetch();

        $this->assertCount(2, $rows);
        $this->assertSame(['Tanggal' => '2026-01-05', 'Nama' => 'BUDI', 'Kategori' => 'TBC'], $rows->first());
        $this->assertSame('SITI', $rows->last()['Nama']);
    }

    public function test_respons_html_bukan_csv_melempar_exception(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response('<html>Sign in</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $reader = new GSheetTbcReader(sheetId: 'fake-sheet-id', gid: '0');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bukan CSV');

        $reader->fetch();
    }

    public function test_http_gagal_melempar_exception(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response('', 404),
        ]);

        $reader = new GSheetTbcReader(sheetId: 'fake-sheet-id', gid: '0');

        $this->expectException(RuntimeException::class);

        $reader->fetch();
    }

    public function test_csv_kosong_menghasilkan_collection_kosong(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response('', 200, ['Content-Type' => 'text/csv']),
        ]);

        $reader = new GSheetTbcReader(sheetId: 'fake-sheet-id', gid: '0');

        $this->assertCount(0, $reader->fetch());
    }
}
