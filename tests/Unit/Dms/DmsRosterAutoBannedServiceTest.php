<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterAutoBannedService;
use Tests\TestCase;

/**
 * Periode kerja > 70 hari baru dimasukkan ke Master Roster Banned di hari
 * ke-74 (aturan.onsite_banned_hari_ke), bukan sejak hari ke-71.
 */
class DmsRosterAutoBannedServiceTest extends TestCase
{
    public function test_onsite_sebelum_hari_ke_74_tidak_masuk_master(): void
    {
        $hasil = app(DmsRosterAutoBannedService::class)->calon([
            $this->onsite('AAA11', 71),
            $this->onsite('BBB22', 73),
        ]);

        $this->assertSame([], $hasil['calon']);
        $this->assertSame(['AAA11', 'BBB22'], array_column($hasil['dilewati'], 'sid'));
        $this->assertSame('periode kerja hari ke-73, masuk master di hari ke-74', $hasil['dilewati'][1]['alasan']);
    }

    public function test_ambang_mengikuti_config(): void
    {
        config(['dms_roster.reg_live.aturan.onsite_banned_hari_ke' => 80]);

        $hasil = app(DmsRosterAutoBannedService::class)->calon([$this->onsite('CCC33', 79)]);

        $this->assertSame([], $hasil['calon']);
        $this->assertSame('periode kerja hari ke-79, masuk master di hari ke-80', $hasil['dilewati'][0]['alasan']);
    }

    /**
     * @return array<string, mixed>
     */
    private function onsite(string $sid, int $hari): array
    {
        return [
            'sid' => $sid, 'nama' => 'Karyawan '.$sid, 'pt' => 'PMJ', 'site' => 'SMO',
            'nilai' => $hari.' hari', 'urut' => $hari, 'jatuh_tempo' => $hari >= 74,
            'tgl_pelanggaran_iso' => '2026-10-20',
        ];
    }
}
