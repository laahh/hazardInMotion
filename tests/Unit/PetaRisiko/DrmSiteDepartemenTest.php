<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmSnapshotBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Site per departemen HIRA (config site_per_departemen): Shipping & Port and Barging Operation → MARINE,
 * Geology and Exploration → EKSPLORASI; site lain tetap lewat lokasi.
 */
class DrmSiteDepartemenTest extends TestCase
{
    private const CFG = ['site_per_departemen' => [
        'MARINE' => ['Shipping Dept.', 'Port and Barging Operation Dept.'],
        'EKSPLORASI' => ['Geology and Exploration Dept.'],
    ]];

    public function test_rute_dari_departemen_hira_tanpa_beda_huruf(): void
    {
        $hira = [
            '1' => ['dept' => 'Port and Barging Operation Dept.'],
            '2' => ['dept' => 'shipping dept.'],
            '3' => ['dept' => 'Geology and Exploration Dept.'],
            '4' => ['dept' => 'Binungan Mine Operation 2 Dept.'],
            '5' => [],
        ];

        $this->assertSame(['1' => 'MARINE', '2' => 'MARINE', '3' => 'EKSPLORASI'], DrmSnapshotBuilder::ruteSite($hira, self::CFG));
        $this->assertSame([], DrmSnapshotBuilder::ruteSite($hira, []));
    }

    public function test_pasangan_per_site_memindahkan_baris_departemen_ke_site_tujuan(): void
    {
        // DL 10 = jetty di LMO (bahaya 1 PBO, bahaya 4 tambang); DL 20 = transhipment MARINE (bahaya 2 Shipping, 5 lain);
        // DL 30 = pit LMO (bahaya 4).
        $dl = ['10' => ['site' => 'LMO'], '20' => ['site' => 'MARINE'], '30' => ['site' => 'LMO']];
        $pasangan = ['10' => ['1', '4'], '20' => ['2', '5'], '30' => ['4']];
        $rute = ['1' => 'MARINE', '2' => 'MARINE'];

        // MARINE: DL sendiri (semua bahaya) + DL jetty LMO hanya untuk bahaya PBO; status tetap dinilai di DL 10.
        [$ids, $p] = DrmSnapshotBuilder::pasanganSite('MARINE', $dl, $pasangan, $rute);
        $this->assertSame(['10', '20'], $ids);
        $this->assertSame(['10' => ['1'], '20' => ['2', '5']], $p);

        // LMO: bahaya PBO keluar dari jetty, bahaya tambang tetap.
        [$ids, $p] = DrmSnapshotBuilder::pasanganSite('LMO', $dl, $pasangan, $rute);
        $this->assertSame(['10', '30'], $ids);
        $this->assertSame(['10' => ['4'], '30' => ['4']], $p);

        // Site tujuan tanpa DL sendiri tetap terbentuk dari bahaya yang dirutekan.
        [$ids, $p] = DrmSnapshotBuilder::pasanganSite('EKSPLORASI', $dl, ['10' => ['3']], ['3' => 'EKSPLORASI']);
        $this->assertSame([['10'], ['10' => ['3']]], [$ids, $p]);
    }
}
