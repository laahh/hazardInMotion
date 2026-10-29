<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterKaryawanAktif as Aktif;
use Tests\TestCase;

/**
 * Bagian murni DmsRosterKaryawanAktif: jabatan VISITOR tidak dihitung sebagai
 * karyawan aktif (Overview cakupan & template roster).
 */
class DmsRosterKaryawanAktifTest extends TestCase
{
    public function test_visitor_dibuang_tanpa_membedakan_huruf(): void
    {
        $hasil = Aktif::rapikan([
            (object) ['sid' => 'a1', 'nama' => 'Ali', 'perusahaan' => 'PT X', 'jabatan' => 'Operator HD', 'site' => 'GMO'],
            (object) ['sid' => 'B2', 'nama' => 'Budi', 'perusahaan' => 'PT X', 'jabatan' => 'VISITOR', 'site' => 'GMO'],
            (object) ['sid' => 'C3', 'nama' => 'Cici', 'perusahaan' => 'PT X', 'jabatan' => ' Visitor ', 'site' => ''],
            (object) ['sid' => 'D4', 'nama' => 'Dedi', 'perusahaan' => 'PT X', 'jabatan' => 'visitor', 'site' => ''],
        ]);

        $this->assertSame(['A1'], array_column($hasil, 'sid'));
    }

    public function test_jabatan_lain_yang_mengandung_kata_visitor_tidak_ikut_dibuang(): void
    {
        $this->assertTrue(Aktif::dikecualikan('VISITOR'));
        $this->assertFalse(Aktif::dikecualikan('Visitor Coordinator'));
        $this->assertFalse(Aktif::dikecualikan(''));
    }

    public function test_daftar_jabatan_dikecualikan_dari_config(): void
    {
        config(['dms_roster.master.jabatan_dikecualikan' => ['visitor', 'Magang']]);

        $this->assertSame(['VISITOR', 'MAGANG'], Aktif::jabatanDikecualikan());
        $this->assertTrue(Aktif::dikecualikan('magang'));
    }
}
