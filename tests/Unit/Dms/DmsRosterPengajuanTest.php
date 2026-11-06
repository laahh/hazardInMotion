<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Models\DmsRosterPengajuan;
use App\Services\Dms\Roster\DmsRosterMasterEditService as Edit;
use App\Services\Dms\Roster\DmsRosterPengajuanService as Pengajuan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;
use Tests\TestCase;

/**
 * Pengajuan dari Form Edit per SID: isi pengajuan (murni) dan alur ajukan →
 * setujui/tolak. Alur memakai SQLite di memori, tidak pernah menyentuh database
 * aplikasi; dilewati bila ekstensinya tidak aktif
 * (php -d extension=pdo_sqlite vendor/bin/phpunit tests/Unit/Dms).
 */
class DmsRosterPengajuanTest extends TestCase
{
    private const TTD = ['path' => 'roster-master/pengajuan/1/ttd-approval.png', 'sumber' => 'GAMBAR'];

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_rencana_mengambil_yang_berubah_saja_dan_urut_tanggal(): void
    {
        $r = Pengajuan::rencana($this->hari(), [
            ['tanggal' => '2026-11-06', 'kode' => 'OFF'],
            ['tanggal' => '2026-11-05', 'kode' => 'D1'],
            ['tanggal' => '2026-11-04', 'kode' => 'I'],
        ], []);

        $this->assertSame([], $r['galat']);
        $this->assertSame([
            ['tanggal' => '2026-11-04', 'lama' => null, 'baru' => 'I'],
            ['tanggal' => '2026-11-06', 'lama' => 'D2', 'baru' => 'OFF'],
        ], $r['perubahan']);
    }

    public function test_rencana_menolak_tanggal_tidak_sah_seluruhnya(): void
    {
        $r = Pengajuan::rencana($this->hari(), [
            ['tanggal' => '2026-11-06', 'kode' => 'OFF'],
            ['tanggal' => '2026-12-01', 'kode' => 'OFF'],
            ['tanggal' => '2026-11-03', 'kode' => 'OFF'],
            ['tanggal' => '2026-11-05', 'kode' => 'OFF'],
        ], ['2026-11-05' => 'CT']);

        $this->assertSame([], $r['perubahan']);
        $this->assertCount(3, $r['galat']);
        $this->assertStringContainsString('di luar rentang', $r['galat'][0]);
        $this->assertStringContainsString('di luar periode', $r['galat'][1]);
        $this->assertStringContainsString('menunggu approval', $r['galat'][2]);
    }

    public function test_rencana_kegiatan_onsite_menyimpan_nama_kegiatan(): void
    {
        $r = Pengajuan::rencana($this->hari(), [
            ['tanggal' => '2026-11-06', 'kode' => 'KG', 'kegiatan' => '  Rapat K3 tahunan '],
        ], []);

        $this->assertSame([], $r['galat']);
        $this->assertSame([
            ['tanggal' => '2026-11-06', 'lama' => 'D2', 'baru' => 'KG', 'kegiatan' => 'Rapat K3 tahunan'],
        ], $r['perubahan']);
    }

    public function test_rencana_kegiatan_onsite_tanpa_nama_ditolak(): void
    {
        $r = Pengajuan::rencana($this->hari(), [
            ['tanggal' => '2026-11-06', 'kode' => 'KG', 'kegiatan' => '   '],
        ], []);

        $this->assertSame([], $r['perubahan']);
        $this->assertStringContainsString('nama kegiatan wajib diisi', $r['galat'][0]);
    }

    public function test_rencana_kode_lain_mengabaikan_nama_kegiatan(): void
    {
        $r = Pengajuan::rencana($this->hari(), [
            ['tanggal' => '2026-11-06', 'kode' => 'OFF', 'kegiatan' => 'Training'],
        ], []);

        $this->assertSame([['tanggal' => '2026-11-06', 'lama' => 'D2', 'baru' => 'OFF']], $r['perubahan']);
    }

    public function test_menu_approval_wajib_login(): void
    {
        $this->getJson(route('dms.master-roster.pengajuan.index'))->assertUnauthorized();
        $this->postJson(route('dms.master-roster.pengajuan.approve', 1))->assertUnauthorized();
        $this->postJson(route('dms.master-roster.pengajuan.reject', 1))->assertUnauthorized();
    }

    public function test_mengajukan_belum_mengubah_jadwal_dan_tanggal_terkunci(): void
    {
        $this->siapkanDb();
        $hasil = $this->ajukan([['tanggal' => '2026-11-05', 'kode' => 'I']]);

        $this->assertSame([], $hasil['galat']);
        $this->assertSame(DmsRosterPengajuan::STATUS_PENDING, $hasil['pengajuan']->status);
        $this->assertSame('D1', $this->kode('2026-11-05'));
        $this->assertSame(['2026-11-05' => 'I'], app(Pengajuan::class)->menunggu('A1'));

        $ulang = $this->ajukan([['tanggal' => '2026-11-05', 'kode' => 'OFF']]);
        $this->assertNull($ulang['pengajuan']);
        $this->assertStringContainsString('menunggu approval', $ulang['galat'][0]);
    }

    public function test_setujui_memperbarui_jadwal_dan_mencatat_riwayat(): void
    {
        $this->siapkanDb();
        $p = $this->ajukan([
            ['tanggal' => '2026-11-05', 'kode' => 'I'],
            ['tanggal' => '2026-11-07', 'kode' => 'N1'],
        ])['pengajuan'];

        app(Pengajuan::class)->review($p, true, 'ok', 'Approver', self::TTD);

        $this->assertSame('I', $this->kode('2026-11-05'));
        $baru = DB::table('dms_roster_master_jadwal')->where('tanggal', '2026-11-07')->first();
        $this->assertSame('N1', $baru->kode);
        $this->assertSame('Budi', $baru->nama);
        $this->assertSame(1, (int) $baru->upload_id);

        $p->refresh();
        $this->assertSame(DmsRosterPengajuan::STATUS_APPROVED, $p->status);
        $this->assertSame('Approver', $p->direview_oleh);
        $this->assertSame(self::TTD['path'], $p->ttd_path);
        $this->assertSame([], app(Pengajuan::class)->menunggu('A1'));

        $log = DB::table('dms_roster_master_edit_log')->orderBy('id')->get();
        $this->assertCount(2, $log);
        $this->assertSame('Approver (pengajuan #'.$p->id.')', $log[0]->diubah_oleh);
        $this->assertSame('D1', $log[0]->nilai_lama);
        $this->assertSame('I', $log[0]->nilai_baru);
    }

    public function test_setujui_tanpa_tanda_tangan_ditolak(): void
    {
        $this->siapkanDb();
        $p = $this->ajukan([['tanggal' => '2026-11-05', 'kode' => 'I']])['pengajuan'];

        $this->expectException(RuntimeException::class);
        app(Pengajuan::class)->review($p, true, null, 'Approver');
    }

    public function test_tolak_tidak_mengubah_jadwal_dan_tidak_bisa_direview_ulang(): void
    {
        $this->siapkanDb();
        $p = $this->ajukan([['tanggal' => '2026-11-05', 'kode' => 'I']])['pengajuan'];

        app(Pengajuan::class)->review($p, false, 'tidak sesuai', 'Approver');

        $this->assertSame('D1', $this->kode('2026-11-05'));
        $this->assertSame(DmsRosterPengajuan::STATUS_REJECTED, $p->refresh()->status);
        $this->assertSame([], app(Pengajuan::class)->menunggu('A1'));

        $this->expectExceptionMessage('sudah direview');
        app(Pengajuan::class)->review($p, true, null, 'Approver', self::TTD);
    }

    public function test_setujui_yang_gagal_membatalkan_semuanya(): void
    {
        $this->siapkanDb();
        $p = $this->ajukan([['tanggal' => '2026-11-05', 'kode' => 'I'], ['tanggal' => '2026-11-07', 'kode' => 'N1']])['pengajuan'];
        // Periode roster dipersempit setelah diajukan: 7 Nov tidak lagi tercakup.
        DB::table('dms_roster_master_upload')->update(['periode_selesai' => '2026-11-06']);

        try {
            app(Pengajuan::class)->review($p, true, null, 'Approver', self::TTD);
            $this->fail('Seharusnya gagal');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('di luar periode', $e->getMessage());
        }

        $this->assertSame('D1', $this->kode('2026-11-05'));
        $this->assertSame(DmsRosterPengajuan::STATUS_PENDING, $p->refresh()->status);
    }

    /**
     * @return list<array{tanggal:string,kode:string,menunggu:bool,boleh:bool}>
     */
    private function hari(): array
    {
        return [
            ['tanggal' => '2026-11-03', 'kode' => '', 'menunggu' => false, 'boleh' => false],
            ['tanggal' => '2026-11-04', 'kode' => '', 'menunggu' => false, 'boleh' => true],
            ['tanggal' => '2026-11-05', 'kode' => 'D1', 'menunggu' => false, 'boleh' => true],
            ['tanggal' => '2026-11-06', 'kode' => 'D2', 'menunggu' => false, 'boleh' => true],
        ];
    }

    /**
     * @param  list<array{tanggal:string,kode:string}>  $sel
     * @return array{galat: list<string>, pengajuan: ?DmsRosterPengajuan}
     */
    private function ajukan(array $sel): array
    {
        $hariIni = CarbonImmutable::parse('2026-11-05');

        return app(Pengajuan::class)->ajukan(app(Edit::class)->jadwalSid('A1', $hariIni), $sel, null, 'Form SID A1 (127.0.0.1)');
    }

    private function kode(string $tanggal): ?string
    {
        return DB::table('dms_roster_master_jadwal')->where('tanggal', $tanggal)->value('kode');
    }

    /** Skema minimal di SQLite memori + satu roster: 1–30 Nov, baris 5 Nov (D1). */
    private function siapkanDb(): void
    {
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite tidak aktif.');
        }
        config(['database.connections.pengajuan_tes' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'database.default' => 'pengajuan_tes']);
        DB::purge('pengajuan_tes');
        CarbonImmutable::setTestNow('2026-11-05 08:00:00');

        Schema::create('dms_roster_master_upload', function (Blueprint $t) {
            $t->id();
            $t->date('periode_mulai');
            $t->date('periode_selesai');
            $t->string('status');
            $t->integer('jumlah_karyawan')->default(0);
            $t->integer('jumlah_jadwal')->default(0);
            $t->timestamps();
        });
        Schema::create('dms_roster_master_jadwal', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('upload_id');
            $t->string('kode_sid');
            $t->string('nama')->nullable();
            $t->string('perusahaan')->nullable();
            $t->string('jabatan')->nullable();
            $t->string('site')->nullable();
            $t->date('tanggal');
            $t->string('kode');
            $t->string('jam_masuk')->nullable();
            $t->string('jam_keluar')->nullable();
            $t->integer('menit_kerja')->nullable();
        });
        (require database_path('migrations/2026_10_29_100000_create_dms_roster_master_edit_log_table.php'))->up();
        (require database_path('migrations/2026_11_05_100000_create_dms_roster_pengajuan_table.php'))->up();

        DB::table('dms_roster_master_upload')->insert(['id' => 1, 'periode_mulai' => '2026-11-01', 'periode_selesai' => '2026-11-30', 'status' => 'APPROVED', 'created_at' => '2026-10-30 08:00:00']);
        DB::table('dms_roster_master_jadwal')->insert(['upload_id' => 1, 'kode_sid' => 'A1', 'nama' => 'Budi', 'perusahaan' => 'PT Uji', 'jabatan' => 'Operator', 'site' => 'LMO', 'tanggal' => '2026-11-05', 'kode' => 'D1', 'jam_masuk' => '06:00', 'jam_keluar' => '18:00', 'menit_kerja' => 720]);
    }
}
