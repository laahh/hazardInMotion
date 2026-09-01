<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Actions\Isc\IscInterventionStoreAction;
use App\Models\Isc\IscBoundaryEvent;
use App\Models\Isc\IscIntervention;
use App\Models\User;
use App\Services\Isc\IscHazardBoundaryClassifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class IscInterventionStoreActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            return;
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

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
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
            $table->string('besigma_violation_id', 64)->nullable();
            $table->string('user_id', 64)->nullable();
            $table->string('unit_id', 64)->nullable();
            $table->string('hazard_kind', 48)->nullable();
            $table->string('besigma_status', 24)->nullable();
            $table->timestamps();
        });
        Schema::create('isc_interventions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('isc_boundary_events')->cascadeOnDelete();
            $table->foreignId('pic_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 64);
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('submitted');
            $table->timestamps();
        });
        Schema::create('isc_intervention_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('intervention_id')->constrained('isc_interventions')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function test_store_uses_default_connection_not_besigma(): void
    {
        $source = file_get_contents((string) (new \ReflectionClass(IscInterventionStoreAction::class))->getFileName());

        $this->assertIsString($source);
        $this->assertStringNotContainsString('besigma_db', $source);
        $this->assertStringContainsString("status === 'open'", $source);
        $this->assertStringContainsString("'in_progress'", $source);
    }

    public function test_store_method_moves_open_event_to_in_progress(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite tidak tersedia di PHP ini.');
        }
        $pic = User::query()->create([
            'name' => 'PIC',
            'email' => 'pic@example.test',
            'password' => Hash::make('secret'),
            'role' => 'user',
        ]);
        $event = IscBoundaryEvent::query()->create([
            'person_key' => 'sid:BC002',
            'entity' => 'person',
            'sid' => 'BC002',
            'name' => 'Budi',
            'lat' => 1.99,
            'lng' => 117.38,
            'entered_at' => now()->subMinutes(20),
            'status' => 'open',
            'besigma_violation_id' => 'v-1',
            'hazard_kind' => IscHazardBoundaryClassifier::KIND_EMPLOYEE_DANGER,
        ]);

        $intervention = app(IscInterventionStoreAction::class)->execute($pic, [
            'event_id' => $event->id,
            'type' => 'himbauan',
            'notes' => 'Keluar zona sekarang',
        ]);

        $this->assertSame('submitted', $intervention->status);
        $this->assertSame('himbauan', $intervention->type);
        $this->assertSame('in_progress', $event->fresh()->status);
        $this->assertSame(1, IscIntervention::query()->count());
    }
}
