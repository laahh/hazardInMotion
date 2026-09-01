<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Models\Isc\IscBoundaryEvent;
use App\Services\Isc\IscHazardBoundaryClassifier;
use App\Services\Isc\IscMapsInterventionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class IscMapsInterventionServiceTest extends TestCase
{
    public function test_demo_payload_includes_entity_kind_and_violation_id(): void
    {
        $data = app(IscMapsInterventionService::class)->payload(null, true);

        $this->assertSame('demo', $data['source']);
        $this->assertNotEmpty($data['tasks']);
        $first = $data['tasks'][0];
        $this->assertArrayHasKey('entity', $first);
        $this->assertArrayHasKey('hazard_kind', $first);
        $this->assertArrayHasKey('besigma_violation_id', $first);
        $this->assertContains($first['entity'], ['person', 'unit']);
        $kinds = array_column($data['tasks'], 'entity');
        $this->assertContains('person', $kinds);
        $this->assertContains('unit', $kinds);
        $this->assertGreaterThan(0, $data['summary']['open']);
        $this->assertArrayHasKey('employee_danger', $data['summary']['kinds']);
    }

    public function test_local_payload_maps_sync_columns(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite tidak tersedia di PHP ini.');
        }
        $this->useSqliteMemory();
        $this->createEventTable();
        IscBoundaryEvent::query()->create([
            'person_key' => 'sid:BC002',
            'entity' => 'person',
            'sid' => 'BC002',
            'name' => 'Budi',
            'lat' => 1.99,
            'lng' => 117.38,
            'iupk_site' => 'Binungan',
            'hazard_boundary_id' => 'hz',
            'hazard_name' => 'Zona',
            'entered_at' => now()->subMinutes(12),
            'status' => 'open',
            'besigma_violation_id' => 'v-local-1',
            'user_id' => 'u-2',
            'hazard_kind' => IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER,
            'besigma_status' => 'DANGER',
        ]);

        $data = app(IscMapsInterventionService::class)->payload(null, false);
        $this->assertSame('local', $data['source']);
        $this->assertSame('v-local-1', $data['tasks'][0]['besigma_violation_id']);
        $this->assertSame('person', $data['tasks'][0]['entity']);
        $this->assertSame(IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER, $data['tasks'][0]['hazard_kind']);
        $this->assertTrue($data['tasks'][0]['has_point']);
    }

    private function useSqliteMemory(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        \Illuminate\Support\Facades\DB::purge('sqlite');
        \Illuminate\Support\Facades\DB::reconnect('sqlite');
    }

    private function createEventTable(): void
    {
        Schema::create('isc_boundary_events', function (Blueprint $table): void {
            $table->id();
            $table->string('person_key', 128);
            $table->string('entity', 16)->default('person');
            $table->string('sid', 64)->nullable();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('iupk_site', 64)->nullable();
            $table->string('hazard_boundary_id', 64)->nullable();
            $table->string('hazard_name')->nullable();
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status', 24)->default('open');
            $table->string('rule_code', 64)->nullable();
            $table->string('besigma_violation_id', 64)->nullable()->unique();
            $table->string('user_id', 64)->nullable();
            $table->string('unit_id', 64)->nullable();
            $table->string('hazard_kind', 48)->nullable();
            $table->string('besigma_status', 24)->nullable();
            $table->timestamps();
        });
    }
}

