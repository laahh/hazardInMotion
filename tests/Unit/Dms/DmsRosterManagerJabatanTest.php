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
}
