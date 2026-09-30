<?php

declare(strict_types=1);

namespace Tests\Unit\MonitoringSafetyEngineering;

use App\Models\MonitoringSafetyEngineeringPicAssignment;
use App\Models\MonitoringSafetyEngineeringRecord;
use App\Services\MonitoringSafetyEngineering\MonitoringSafetyEngineeringPicScopeService;
use Tests\TestCase;

final class MonitoringSafetyEngineeringPicScopeServiceTest extends TestCase
{
    private MonitoringSafetyEngineeringPicScopeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MonitoringSafetyEngineeringPicScopeService;
    }

    public function test_user_tanpa_scope_boleh_semua_pasangan(): void
    {
        $scope = ['scoped' => false];

        $this->assertTrue($this->service->allowsPair('BMO 1', 'KDC', $scope));
        $this->assertTrue($this->service->allowsPair('GMO', 'PAMA', $scope));
        $this->assertSame('Semua site & perusahaan', $this->service->describe($scope));
    }

    public function test_pic_multi_perusahaan_hanya_boleh_pasangan_yang_diassign(): void
    {
        $scope = $this->scope(
            pairs: [
                ['site' => 'BMO 1', 'perusahaan' => 'KDC'],
                ['site' => 'BMO 2', 'perusahaan' => 'BUMA'],
            ],
            sites: ['BMO 1', 'BMO 2'],
            companies: ['BUMA', 'KDC'],
        );

        $this->assertTrue($this->service->allowsPair('BMO 1', 'KDC', $scope));
        $this->assertTrue($this->service->allowsPair('BMO 2', 'BUMA', $scope));

        // Silang antar assignment tidak boleh: dia pegang KDC di BMO 1, bukan di BMO 2.
        $this->assertFalse($this->service->allowsPair('BMO 2', 'KDC', $scope));
        $this->assertFalse($this->service->allowsPair('BMO 1', 'BUMA', $scope));
        $this->assertFalse($this->service->allowsPair('GMO', 'PAMA', $scope));
    }

    public function test_all_site_hanya_berlaku_untuk_perusahaannya_sendiri(): void
    {
        $scope = $this->scope(
            pairs: [['site' => 'BMO 1', 'perusahaan' => 'KDC']],
            sites: ['BMO 1'],
            companies: ['KDC', 'MTL'],
            allSiteCompanies: ['MTL'],
        );

        // MTL: semua site.
        $this->assertTrue($this->service->allowsPair('BMO 1', 'MTL', $scope));
        $this->assertTrue($this->service->allowsPair('MARINE', 'MTL', $scope));

        // KDC: hanya BMO 1.
        $this->assertTrue($this->service->allowsPair('BMO 1', 'KDC', $scope));
        $this->assertFalse($this->service->allowsPair('MARINE', 'KDC', $scope));
    }

    public function test_scope_tanpa_pasangan_menolak_semua(): void
    {
        $scope = $this->scope();

        $this->assertFalse($this->service->allowsPair('BMO 1', 'KDC', $scope));
        $this->assertSame('Belum ada perusahaan/site yang di-assign', $this->service->describe($scope));
    }

    public function test_spasi_berlebih_pada_pasangan_tetap_dikenali(): void
    {
        $scope = $this->scope(
            pairs: [['site' => 'BMO 1', 'perusahaan' => 'KDC']],
            sites: ['BMO 1'],
            companies: ['KDC'],
        );

        $this->assertTrue($this->service->allowsPair('  BMO 1  ', ' KDC ', $scope));
    }

    public function test_narrow_options_menyisakan_opsi_yang_diizinkan_plus_opsi_semua(): void
    {
        $options = ['' => 'Semua Site', 'BMO 1' => 'BMO 1', 'BMO 2' => 'BMO 2', 'GMO' => 'GMO'];

        $narrowed = $this->service->narrowOptions($options, ['BMO 1', 'GMO']);

        $this->assertSame(['' => 'Semua Site', 'BMO 1' => 'BMO 1', 'GMO' => 'GMO'], $narrowed);
    }

    public function test_narrow_options_tanpa_daftar_izin_tidak_mengubah_apa_pun(): void
    {
        $options = ['' => 'Semua Site', 'BMO 1' => 'BMO 1'];

        $this->assertSame($options, $this->service->narrowOptions($options, []));
    }

    public function test_describe_menyusun_ringkasan_per_perusahaan(): void
    {
        $scope = $this->scope(
            pairs: [
                ['site' => 'BMO 1', 'perusahaan' => 'KDC'],
                ['site' => 'BMO 2', 'perusahaan' => 'KDC'],
            ],
            sites: ['BMO 1', 'BMO 2'],
            companies: ['KDC', 'MTL'],
            allSiteCompanies: ['MTL'],
        );

        $this->assertSame('MTL (semua site) · KDC (BMO 1, BMO 2)', $this->service->describe($scope));
    }

    public function test_apply_to_query_membatasi_per_pasangan_dan_perusahaan_all_site(): void
    {
        $query = MonitoringSafetyEngineeringRecord::query();

        $this->service->applyToQuery($query, $this->scope(
            pairs: [['site' => 'BMO 1', 'perusahaan' => 'KDC']],
            sites: ['BMO 1'],
            companies: ['KDC', 'MTL'],
            allSiteCompanies: ['MTL'],
        ));

        $sql = $query->toSql();

        $this->assertStringContainsString('"perusahaan" in (?)', str_replace('`', '"', $sql));
        $this->assertStringContainsString('or ("site" = ? and "perusahaan" = ?)', str_replace('`', '"', $sql));
        $this->assertSame(['MTL', 'BMO 1', 'KDC'], $query->getBindings());
    }

    public function test_apply_to_query_menolak_semua_saat_scope_kosong(): void
    {
        $query = MonitoringSafetyEngineeringRecord::query();

        $this->service->applyToQuery($query, $this->scope());

        $this->assertStringContainsString('1 = 0', $query->toSql());
    }

    public function test_apply_to_query_tidak_mengubah_apa_pun_untuk_user_tanpa_scope(): void
    {
        $query = MonitoringSafetyEngineeringRecord::query();
        $before = $query->toSql();

        $this->service->applyToQuery($query, ['scoped' => false]);

        $this->assertSame($before, $query->toSql());
    }

    public function test_label_all_site_dikenali_dalam_beberapa_penulisan(): void
    {
        foreach (['ALL SITE', 'all site', 'All Site', 'Semua Site', 'ALL', ' allsite '] as $label) {
            $this->assertTrue(
                MonitoringSafetyEngineeringPicAssignment::isAllSitesLabel($label),
                $label . ' seharusnya dianggap all site',
            );
        }

        foreach (['BMO 1', 'GMO', 'MARINE', ''] as $label) {
            $this->assertFalse(
                MonitoringSafetyEngineeringPicAssignment::isAllSitesLabel($label),
                $label . ' seharusnya bukan all site',
            );
        }
    }

    /**
     * @param  list<array{site: string, perusahaan: string}>  $pairs
     * @param  list<string>  $sites
     * @param  list<string>  $companies
     * @param  list<string>  $allSiteCompanies
     * @return array<string, mixed>
     */
    private function scope(
        array $pairs = [],
        array $sites = [],
        array $companies = [],
        array $allSiteCompanies = [],
    ): array {
        return [
            'scoped' => true,
            'source' => 'assignment',
            'nama' => 'PIC UJI',
            'sid' => 'TEST1',
            'pairs' => $pairs,
            'sites' => $sites,
            'companies' => $companies,
            'all_site_companies' => $allSiteCompanies,
            'lock_site' => $allSiteCompanies === [] && count($sites) === 1,
            'lock_perusahaan' => count($companies) === 1,
            'all_sites' => $allSiteCompanies !== [],
        ];
    }
}
