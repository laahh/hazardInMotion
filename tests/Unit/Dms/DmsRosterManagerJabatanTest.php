<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterApproverDepartemen;
use PHPUnit\Framework\TestCase;

final class DmsRosterManagerJabatanTest extends TestCase
{
    public function test_petakan_manager_menjadikan_sid_huruf_besar_dan_membuang_baris_kosong(): void
    {
        $peta = DmsRosterApproverDepartemen::petakanManager([
            ['sid' => ' lsdev ', 'departemen' => 'MINING TECHNOLOGY DEPARTMENT'],
            ['sid' => 'UC9NZ', 'departemen' => 'ENVIRONMENT DEPARTMENT'],
            ['sid' => '', 'departemen' => 'HR'],
            ['sid' => 'X1', 'departemen' => '  '],
        ]);

        $this->assertSame([
            'LSDEV' => 'MINING TECHNOLOGY DEPARTMENT',
            'UC9NZ' => 'ENVIRONMENT DEPARTMENT',
        ], $peta);
    }

    public function test_daftar_nama_menerima_string_atau_daftar_departemen(): void
    {
        $this->assertSame(['HR'], DmsRosterApproverDepartemen::daftarNama(' HR '));
        $this->assertSame(['A', 'B'], DmsRosterApproverDepartemen::daftarNama(['A', ' B ', '', 'A', null]));
        $this->assertSame([], DmsRosterApproverDepartemen::daftarNama(null));
    }
}
