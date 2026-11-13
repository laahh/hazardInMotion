<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Models\User;
use App\Services\Dms\Roster\DmsRosterAksesPerusahaan;
use App\Services\Dms\Roster\DmsRosterApproverDepartemen as Approver;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

/**
 * Approver per departemen PT Berau Coal: daftar manager di config, departemen
 * karyawan dari salinan cache. Bagian database memakai SQLite di memori dan
 * dilewati bila ekstensinya tidak aktif
 * (php -d extension=pdo_sqlite vendor/bin/phpunit tests/Unit/Dms).
 */
class DmsRosterApproverDepartemenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        DmsRosterAksesPerusahaan::lupakanPeta([]);
        config(['dms_roster.master.approver_departemen.approver' => [
            'S69PK' => 'HEALTH & SAFETY PERFORMANCE ASSSURANCE',
            'WLUHX' => 'Environment Strategic & Sustainability Department',
        ]]);
    }

    protected function tearDown(): void
    {
        DmsRosterAksesPerusahaan::lupakanPeta();
        parent::tearDown();
    }

    public function test_daftar_tertanam_berisi_manager_berau_coal(): void
    {
        $config = require base_path('config/dms_roster.php');
        $tanam = $config['master']['approver_departemen'];

        $this->assertSame('PT Berau Coal', $tanam['perusahaan']);
        // 46 manager + SID kedua Ridho (W2PAR).
        $this->assertCount(47, $tanam['approver']);
        $this->assertContains('QUALITY & RISK MANAGEMENT SYSTEM DEPARTMENT', $tanam['approver']['K1I5I']);
        $this->assertSame('BINUNGAN MINE OPERATION AREA 1 DEPARTEMENT', $tanam['approver']['9KWOO']);
    }

    public function test_akun_dicocokkan_dari_sid_di_kolom_email_tanpa_beda_huruf(): void
    {
        $this->assertSame('HEALTH & SAFETY PERFORMANCE ASSSURANCE', $this->approver()->departemenUser($this->akun(' s69pk ')));
        $this->assertNull($this->approver()->departemenUser($this->akun('BUKAN1')));
        $this->assertNull($this->approver()->departemenUser(null));
    }

    public function test_petakan_mengelompokkan_sid_per_departemen_tanpa_beda_huruf_dan_spasi(): void
    {
        $peta = Approver::petakan([
            (object) ['sid' => ' a1 ', 'departemen' => 'Environment  Strategic & Sustainability Department'],
            (object) ['sid' => 'A2', 'departemen' => 'ENVIRONMENT STRATEGIC & SUSTAINABILITY DEPARTMENT '],
            (object) ['sid' => 'A3', 'departemen' => null],
            (object) ['sid' => '', 'departemen' => 'SECURITY DEPARTMENT'],
        ]);

        $this->assertSame(['ENVIRONMENT STRATEGIC & SUSTAINABILITY DEPARTMENT' => ['A1', 'A2']], $peta);
    }

    public function test_segarkan_gagal_tidak_menimpa_salinan_lama(): void
    {
        $this->isiSalinan(['SECURITY DEPARTMENT' => ['Z1']]);
        // Nama view tidak sah gagal sebelum menyentuh OLAP, lewat jalur catch yang sama.
        config(['dms_roster.master.approver_departemen.view_karyawan' => 'view; DROP']);
        $svc = $this->approver();

        $this->assertNull($svc->segarkan());
        $this->assertStringContainsString('tidak valid', (string) $svc->galatTerakhir());
        $this->assertSame(['SECURITY DEPARTMENT' => ['Z1']], Cache::get(Approver::KUNCI));
    }

    public function test_segarkan_hasil_kosong_tidak_menimpa_salinan_lama(): void
    {
        $this->isiSalinan(['SECURITY DEPARTMENT' => ['Z1']]);
        $svc = $this->approver();

        $this->assertNull($svc->simpanSalinan([]));
        $this->assertSame(['SECURITY DEPARTMENT' => ['Z1']], Cache::get(Approver::KUNCI));
    }

    public function test_departemen_approver_yang_ejaannya_tak_dikenal_dilaporkan(): void
    {
        $tak = Approver::departemenTakDikenal(['ENVIRONMENT STRATEGIC & SUSTAINABILITY DEPARTMENT' => ['A1']]);

        $this->assertSame(['HEALTH & SAFETY PERFORMANCE ASSSURANCE'], $tak);
    }

    public function test_tanpa_salinan_tidak_ada_akses(): void
    {
        $svc = $this->approver();

        $this->assertFalse($svc->dataTersedia());
        $this->assertSame([], $svc->uploadUntuk($this->akun('S69PK')));
        $this->assertFalse($svc->bolehReview($this->akun('S69PK'), 1, ['PT Berau Coal']));
    }

    public function test_manager_melihat_dan_mereview_roster_berisi_departemennya(): void
    {
        $this->siapkanDb();
        $this->isiSalinan(['HEALTH & SAFETY PERFORMANCE ASSSURANCE' => ['A1']]);
        $svc = $this->approver();
        $manager = $this->akun('S69PK');

        $this->assertSame([1, 3], $svc->uploadUntuk($manager));
        $this->assertTrue($svc->bolehLihat($manager, 1));
        $this->assertTrue($svc->bolehReview($manager, 1, ['PT Berau Coal']));
    }

    public function test_manager_tidak_bisa_roster_tanpa_departemennya(): void
    {
        $this->siapkanDb();
        $this->isiSalinan(['HEALTH & SAFETY PERFORMANCE ASSSURANCE' => ['A1']]);
        $svc = $this->approver();
        $manager = $this->akun('S69PK');

        $this->assertFalse($svc->bolehLihat($manager, 2));
        $this->assertFalse($svc->bolehReview($manager, 2, ['PT Berau Coal']));
    }

    public function test_roster_campur_perusahaan_lain_hanya_bisa_dilihat(): void
    {
        $this->siapkanDb();
        $this->isiSalinan(['HEALTH & SAFETY PERFORMANCE ASSSURANCE' => ['A1']]);
        $svc = $this->approver();
        $manager = $this->akun('S69PK');

        $this->assertTrue($svc->bolehLihat($manager, 3));
        $this->assertFalse($svc->bolehReview($manager, 3, ['PT Berau Coal', 'PT Primac Perkasa Indonesia']));
    }

    public function test_user_biasa_tidak_mendapat_akses_departemen(): void
    {
        $this->siapkanDb();
        $this->isiSalinan(['HEALTH & SAFETY PERFORMANCE ASSSURANCE' => ['A1']]);
        $svc = $this->approver();

        $this->assertSame([], $svc->uploadUntuk($this->akun('BUKAN1')));
        $this->assertFalse($svc->bolehReview($this->akun('BUKAN1'), 1, ['PT Berau Coal']));
    }

    public function test_batasi_upload_menggabungkan_assignment_dan_departemen(): void
    {
        $this->siapkanDb();
        DB::table('dms_roster_master_upload')->insert([['id' => 1], ['id' => 2], ['id' => 3]]);
        $q = fn () => \App\Models\DmsRosterMasterUpload::query();
        $akses = new DmsRosterAksesPerusahaan;

        $this->assertSame([1, 3], $akses->batasiUpload($q(), [], [1, 3])->orderBy('id')->pluck('id')->all());
        $this->assertSame([], $akses->batasiUpload($q(), [], [])->pluck('id')->all());
        $this->assertSame([1, 2, 3], $akses->batasiUpload($q(), null, [1])->orderBy('id')->pluck('id')->all());
    }

    private function approver(): Approver
    {
        return new Approver(app(PembatasanLVOlapQuery::class));
    }

    private function akun(string $sid): User
    {
        return new User(['email' => $sid]);
    }

    /**
     * @param  array<string, list<string>>  $peta
     */
    private function isiSalinan(array $peta): void
    {
        Cache::put(Approver::KUNCI, $peta, 3600);
    }

    private function siapkanDb(): void
    {
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite tidak aktif.');
        }
        config([
            'database.connections.uji_approver' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => 'uji_approver',
        ]);
        DB::purge('uji_approver');
        Schema::create('dms_roster_master_upload', static function (Blueprint $t): void {
            $t->id();
        });
        Schema::create('dms_roster_master_jadwal', static function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('upload_id');
            $t->string('kode_sid');
        });
        DB::table('dms_roster_master_jadwal')->insert([
            ['upload_id' => 1, 'kode_sid' => 'A1'],
            ['upload_id' => 1, 'kode_sid' => 'B1'],
            ['upload_id' => 2, 'kode_sid' => 'B1'],
            ['upload_id' => 3, 'kode_sid' => 'A1'],
        ]);
    }
}
