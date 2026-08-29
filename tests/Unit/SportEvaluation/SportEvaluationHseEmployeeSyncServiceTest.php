<?php

declare(strict_types=1);

namespace Tests\Unit\SportEvaluation;

use App\Services\SportEvaluation\SportEvaluationEmployeeProfileService;
use App\Services\SportEvaluation\SportEvaluationHseEmployeeSyncService;
use ReflectionClass;
use Tests\TestCase;

final class SportEvaluationHseEmployeeSyncServiceTest extends TestCase
{
    public function test_writable_columns_never_include_id(): void
    {
        $this->assertNotContains('id', SportEvaluationEmployeeProfileService::WRITABLE_COLUMNS);
    }

    public function test_map_detail_updates_company_status_and_never_sets_id(): void
    {
        $payload = $this->mapDetail([
            'employee' => [
                'sid' => '6H2DF',
                'name' => 'AGUS CAHYONO',
                'status' => 'NONAKTIF',
                'company' => [
                    'id' => 99,
                    'name' => 'PT Baru',
                ],
                'department' => ['name' => 'Site Plant 2'],
                'functionalPosition' => ['name' => 'Operator'],
                'structuralPosition' => ['name' => 'Foreman'],
            ],
            'dedicatedSite' => ['name' => 'GMO'],
        ], '6H2DF');

        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('id', $payload);
        $this->assertSame('6H2DF', $payload['kode_sid']);
        $this->assertSame('AGUS CAHYONO', $payload['nama']);
        $this->assertSame('NONAKTIF', $payload['status_karyawan']);
        $this->assertSame('PT Baru', $payload['nama_perusahaan']);
        $this->assertSame(99, $payload['id_perusahaan']);
        $this->assertSame('GMO', $payload['site']);
        $this->assertSame('Site Plant 2', $payload['departement']);
        $this->assertSame('Operator', $payload['jabatan_fungsional']);
        $this->assertSame('Foreman', $payload['jabatan_struktural']);
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>|null
     */
    private function mapDetail(array $detail, string $fallbackSid): ?array
    {
        $ref = new ReflectionClass(SportEvaluationHseEmployeeSyncService::class);
        $service = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('mapDetailToWritable');
        $method->setAccessible(true);

        /** @var array<string, mixed>|null $payload */
        $payload = $method->invoke($service, $detail, $fallbackSid);

        return $payload;
    }
}
