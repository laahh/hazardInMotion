<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomAajCarSource;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use ReflectionProperty;
use Tests\TestCase;

final class ControlRoomAajCarSourceTest extends TestCase
{
    public function test_memilih_kolom_yang_ada_dan_null_untuk_kolom_opsional_hilang(): void
    {
        $src = $this->source([
            'id' => 'bigint',
            'sid_pelapor' => 'text',
            'tanggal_pembuatan' => 'timestamp without time zone',
            'jenis_laporan' => 'text',
            'nama_lokasi' => 'text',
        ]);

        $this->assertTrue($src->isUsable());
        $this->assertSame('CAST("nama_lokasi" AS text)', $src->text('lokasi'));
        $this->assertSame('CAST(NULL AS text)', $src->text('golden_rule'));
        $this->assertSame('FALSE', $src->present('url_foto'));
        $this->assertSame('CAST("tanggal_pembuatan" AS timestamp)', $src->atExpr());
        $this->assertSame(['sql' => 'TRUE', 'bindings' => []], $src->toolsPredicate());
    }

    public function test_kolom_wajib_hilang_membuat_sumber_tidak_usable(): void
    {
        $src = $this->source([
            'id' => 'bigint',
            'tanggal_pembuatan' => 'timestamp',
            'jenis_laporan' => 'text',
        ]);

        $this->assertFalse($src->isUsable());
    }

    public function test_timestamptz_dikonversi_ke_wita_dan_draft_jadi_fallback(): void
    {
        $src = $this->source([
            'id' => 'bigint',
            'sid_pelapor' => 'text',
            'tanggal_pembuatan' => 'timestamp with time zone',
            'bedraft_date' => 'timestamp with time zone',
            'jenis_laporan' => 'text',
            'name_tools_observation' => 'text',
        ]);

        $this->assertSame(
            "COALESCE((\"tanggal_pembuatan\" AT TIME ZONE 'Asia/Makassar'), (\"bedraft_date\" AT TIME ZONE 'Asia/Makassar'))",
            $src->atExpr(),
        );
        $tools = $src->toolsPredicate();
        $this->assertStringContainsString('BTRIM(CAST("name_tools_observation" AS text)) IN (', $tools['sql']);
        $this->assertNotEmpty($tools['bindings']);
    }

    public function test_tabel_tidak_ditemukan_tidak_usable(): void
    {
        $this->assertFalse($this->source([])->isUsable());
    }

    /**
     * @param  array<string, string>  $catalog
     */
    private function source(array $catalog): ControlRoomAajCarSource
    {
        $src = new ControlRoomAajCarSource(new PembatasanLVOlapQuery());
        (new ReflectionProperty($src, 'catalog'))->setValue($src, $catalog);

        return $src;
    }
}
