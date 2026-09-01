<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Actions\Isc\IscSyncActiveViolationsAction;
use App\Events\Isc\IscHazardEntered;
use App\Models\Isc\IscBoundaryEvent;
use App\Services\Isc\IscBesigmaViolationReader;
use App\Services\Isc\IscHazardBoundaryClassifier;
use App\Services\Isc\IscPobDemoDataset;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

final class IscSyncActiveViolationsActionTest extends TestCase
{
    public function test_maps_person_and_unit_rows(): void
    {
        $action = $this->action();
        $person = $this->invoke($action, 'mapPerson', [$this->personViolation()]);
        $unit = $this->invoke($action, 'mapUnit', [$this->unitViolation()]);

        $this->assertIsArray($person);
        $this->assertSame('person', $person['entity']);
        $this->assertSame('v-person-1', $person['besigma_violation_id']);
        $this->assertSame(IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER, $person['hazard_kind']);
        $this->assertSame('sid:BC100', $person['person_key']);
        $this->assertSame('person|u-1|hz-1', $person['dedup_key']);

        $this->assertIsArray($unit);
        $this->assertSame('unit', $unit['entity']);
        $this->assertSame('v-unit-1', $unit['besigma_violation_id']);
        $this->assertSame(IscHazardBoundaryClassifier::KIND_UNIT_DANGER, $unit['hazard_kind']);
        $this->assertSame('unit:unit-9', $unit['person_key']);
        $this->assertNull($unit['lat']);
    }

    public function test_find_existing_prefers_violation_id_then_fallback(): void
    {
        $action = $this->action();
        $byVid = new IscBoundaryEvent([
            'person_key' => 'sid:OLD',
            'entity' => 'person',
            'besigma_violation_id' => 'v-person-1',
            'user_id' => 'other',
            'hazard_boundary_id' => 'other',
        ]);
        $byFallback = new IscBoundaryEvent([
            'person_key' => 'sid:BC100',
            'entity' => 'person',
            'user_id' => 'u-1',
            'hazard_boundary_id' => 'hz-1',
        ]);
        $index = [
            'vid:v-person-1' => $byVid,
            'fb:person|u-1|hz-1' => $byFallback,
        ];
        $row = $this->invoke($action, 'mapPerson', [$this->personViolation()]);
        $found = $this->invoke($action, 'findExisting', [$index, $row]);

        $this->assertSame($byVid, $found);
    }

    public function test_creates_person_and_unit_without_duplicate(): void
    {
        $this->requireSqlite();
        Event::fake([IscHazardEntered::class]);
        $this->createEventTable();
        $action = $this->action();

        $first = $action->execute(true);
        $second = $action->execute(true);

        $this->assertSame(3, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(3, $second['updated']);
        $this->assertSame(3, IscBoundaryEvent::query()->count());
        Event::assertDispatchedTimes(IscHazardEntered::class, 3);

        $person = IscBoundaryEvent::query()->where('entity', 'person')->first();
        $this->assertNotNull($person);
        $this->assertSame('v-person-1', $person->besigma_violation_id);
        $this->assertSame(IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER, $person->hazard_kind);
        $this->assertSame('open', $person->status);

        $unit = IscBoundaryEvent::query()->where('entity', 'unit')->first();
        $this->assertNotNull($unit);
        $this->assertSame('v-unit-1', $unit->besigma_violation_id);
        $this->assertSame(IscHazardBoundaryClassifier::KIND_UNIT_DANGER, $unit->hazard_kind);
        $this->assertNull($unit->lat);
    }

    public function test_closes_open_when_besigma_inactive_and_keeps_in_progress(): void
    {
        $this->requireSqlite();
        Event::fake([IscHazardEntered::class]);
        $this->createEventTable();
        $open = IscBoundaryEvent::query()->create($this->eventAttrs([
            'besigma_violation_id' => 'gone',
            'person_key' => 'sid:GONE',
            'status' => 'open',
        ]));
        $busy = IscBoundaryEvent::query()->create($this->eventAttrs([
            'besigma_violation_id' => 'busy',
            'person_key' => 'sid:BUSY',
            'user_id' => 'u-busy',
            'status' => 'in_progress',
            'name' => 'PIC masih kerja',
        ]));

        $result = $this->action()->execute(false);

        $this->assertSame(1, $result['closed']);
        $this->assertSame('closed', $open->fresh()->status);
        $this->assertNotNull($open->fresh()->exited_at);
        $this->assertSame('in_progress', $busy->fresh()->status);
        $this->assertNotNull($busy->fresh()->exited_at);
    }

    public function test_action_source_never_mentions_besigma_connection(): void
    {
        $source = file_get_contents((string) (new \ReflectionClass(IscSyncActiveViolationsAction::class))->getFileName());

        $this->assertIsString($source);
        $this->assertStringNotContainsString('besigma_db', $source);
        $this->assertStringNotContainsString('DB::connection', $source);
    }

    private function action(): IscSyncActiveViolationsAction
    {
        return new IscSyncActiveViolationsAction(
            app(IscBesigmaViolationReader::class),
            app(IscPobDemoDataset::class),
        );
    }

    private function invoke(object $target, string $method, array $args): mixed
    {
        $ref = new ReflectionMethod($target, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($target, $args);
    }

    /**
     * @return array<string, mixed>
     */
    private function personViolation(): array
    {
        return [
            'id' => 'v-person-1',
            'user_id' => 'u-1',
            'sid' => 'BC100',
            'name' => 'Ali Unsafe',
            'company' => 'PT Demo',
            'job_title' => 'Operator',
            'site' => 'Binungan',
            'site_code' => 'BMO',
            'boundary_id' => 'hz-1',
            'hazard_name' => 'Zona bahaya',
            'hazard_kind' => IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER,
            'status' => 'DANGER',
            'entered_at' => now()->subMinutes(10)->toDateTimeString(),
            'lat' => 1.99,
            'lng' => 117.38,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unitViolation(): array
    {
        return [
            'id' => 'v-unit-1',
            'unit_id' => 'unit-9',
            'sid' => 'DT-01',
            'name' => 'DT-01',
            'company' => 'Vendor',
            'site_code' => 'LMO',
            'boundary_id' => 'hz-u',
            'hazard_name' => 'Zona unit',
            'hazard_kind' => IscHazardBoundaryClassifier::KIND_UNIT_DANGER,
            'status' => 'WARNING',
            'entered_at' => now()->subMinutes(5)->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function eventAttrs(array $overrides = []): array
    {
        return array_merge([
            'person_key' => 'sid:X',
            'entity' => 'person',
            'sid' => 'X',
            'name' => 'Orang',
            'lat' => 1.9,
            'lng' => 117.3,
            'hazard_boundary_id' => 'hz',
            'entered_at' => now()->subHour(),
            'status' => 'open',
            'rule_code' => 'hazard-entry',
            'besigma_violation_id' => 'v-x',
            'user_id' => 'u-x',
            'hazard_kind' => IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER,
            'besigma_status' => 'WARNING',
        ], $overrides);
    }

    private function requireSqlite(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite tidak tersedia di PHP ini.');
        }
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
