<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Actions\Isc\IscHazardReportStoreAction;
use App\Actions\Isc\IscInterventionStoreAction;
use App\Models\Isc\IscBoundaryEvent;
use App\Models\Isc\IscHazardReport;
use App\Models\User;
use App\Services\Isc\IscHazardEmployeeLookupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class IscHazardReportStoreActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite required');
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
            $table->timestamps();
        });
        Schema::create('isc_boundary_events', function (Blueprint $table): void {
            $table->id();
            $table->string('person_key', 128);
            $table->string('sid', 64)->nullable();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('iupk_site', 64)->nullable();
            $table->string('hazard_boundary_id', 64)->nullable();
            $table->string('hazard_name')->nullable();
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status', 24)->default('open');
            $table->string('rule_code', 64)->nullable();
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
        Schema::create('isc_hazard_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained('isc_boundary_events')->nullOnDelete();
            $table->foreignId('intervention_id')->nullable()->constrained('isc_interventions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username', 100)->nullable();
            $table->string('password')->nullable();
            $table->string('perusahaan', 255)->nullable();
            $table->string('pic_sid', 64)->nullable();
            $table->string('pic_npk', 64)->nullable();
            $table->string('pic_nama', 255)->nullable();
            $table->string('pic_jabatan', 255)->nullable();
            $table->string('tools_pengamatan', 100)->nullable();
            $table->string('site', 64)->nullable();
            $table->string('lokasi', 255)->nullable();
            $table->string('detail_lokasi', 255)->nullable();
            $table->text('keterangan_lokasi')->nullable();
            $table->string('sid_pelapor', 64);
            $table->string('npk_pelapor', 64)->nullable();
            $table->string('nama_pelapor', 255)->nullable();
            $table->string('jabatan_pelapor', 255)->nullable();
            $table->string('area_pja_bc', 255)->nullable();
            $table->string('area_pja_mitra', 255)->nullable();
            $table->string('foto_path')->nullable();
            $table->boolean('is_observasi_area_kritis')->default(false);
            $table->string('ketidaksesuaian', 255)->nullable();
            $table->string('sub_ketidaksesuaian', 255)->nullable();
            $table->string('quick_action', 255)->nullable();
            $table->text('deskripsi_temuan')->nullable();
            $table->string('status', 24)->default('submitted');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_store_creates_hazard_report_and_intervention(): void
    {
        $user = User::query()->create([
            'name' => 'PIC Test',
            'email' => 'pic-hazard@example.com',
            'password' => Hash::make('secret'),
        ]);
        $event = IscBoundaryEvent::query()->create([
            'person_key' => 'p1',
            'sid' => '2E2AF',
            'name' => 'Worker',
            'lat' => -1.1,
            'lng' => 117.1,
            'entered_at' => now(),
            'status' => 'open',
        ]);

        $lookup = Mockery::mock(IscHazardEmployeeLookupService::class);
        $lookup->shouldReceive('findBySid')
            ->with('2E2AF')
            ->andReturn([
                'sid' => '2E2AF',
                'npk' => '10000340',
                'nama' => 'IFA APRILLIANTO',
                'jabatan' => 'SAFETY EVALUATOR',
                'company' => 'PT Berau Coal Energy',
            ]);
        $lookup->shouldReceive('findBySid')
            ->with('VR9T7')
            ->andReturn([
                'sid' => 'VR9T7',
                'npk' => '10000100',
                'nama' => 'PIC NAME',
                'jabatan' => 'SUPERVISOR',
                'company' => 'PT Berau Coal Energy',
            ]);

        $action = new IscHazardReportStoreAction($lookup, app(IscInterventionStoreAction::class));
        $report = $action->execute($user, [
            'event_id' => $event->id,
            'username' => 'reporter1',
            'password' => 'plain-secret',
            'perusahaan' => 'PT Berau Coal Energy',
            'pic_sid' => 'VR9T7',
            'sid_pelapor' => '2E2AF',
            'tools_pengamatan' => 'Post Event - BeSigma',
            'site' => 'BMO',
            'deskripsi_temuan' => 'Temuan uji',
            'is_observasi_area_kritis' => true,
        ]);

        $this->assertInstanceOf(IscHazardReport::class, $report);
        $this->assertSame('2E2AF', $report->sid_pelapor);
        $this->assertSame('IFA APRILLIANTO', $report->nama_pelapor);
        $this->assertSame('VR9T7', $report->pic_sid);
        $this->assertSame('reporter1', $report->username);
        $this->assertTrue(Hash::check('plain-secret', (string) $report->getAttributes()['password']));
        $this->assertNotNull($report->intervention_id);
        $this->assertSame('in_progress', $event->fresh()->status);
    }
}
