<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Models\DmsRosterMasterUpload as Upload;
use App\Services\Dms\Roster\DmsRosterMasterService;
use App\Services\Dms\Roster\DmsRosterReviewDepartemen;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Tests\TestCase;

/**
 * Manager departemen hanya memutuskan baris departemennya: baris itu pindah ke
 * upload baru yang direview, sisanya tetap menunggu di upload asal.
 * (php -d extension=pdo_sqlite vendor/bin/phpunit tests/Unit/Dms).
 */
class DmsRosterReviewDepartemenTest extends TestCase
{
    private const HSE = 'HEALTH & SAFETY';

    private const TTD = ['path' => 'ttd.png', 'sumber' => 'GAMBAR'];

    protected function setUp(): void
    {
        parent::setUp();
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite tidak aktif.');
        }
        config([
            'database.connections.uji_review' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => 'uji_review',
        ]);
        DB::purge('uji_review');
        Storage::fake('local');
        $this->buatTabel();
    }

    public function test_setuju_memindahkan_baris_departemen_ke_upload_baru_yang_disetujui(): void
    {
        $asal = $this->uploadTigaKaryawan();

        $hasil = $this->review($asal, ['A1', 'A2'], true);

        $this->assertNotSame($asal->id, $hasil->id);
        $this->assertSame(Upload::STATUS_APPROVED, $hasil->status);
        $this->assertSame('Pak HSE', $hasil->direview_oleh);
        $this->assertSame(self::HSE, $hasil->departemen_site);
        $this->assertSame(2, (int) $hasil->jumlah_karyawan);
        $this->assertSame(3, (int) $hasil->jumlah_jadwal);
        $this->assertSame(['A1', 'A2'], $this->sidDi($hasil->id));
    }

    public function test_sisa_karyawan_tetap_menunggu_di_upload_asal(): void
    {
        $asal = $this->uploadTigaKaryawan();

        $this->review($asal, ['A1', 'A2'], true);

        $asal->refresh();
        $this->assertSame(Upload::STATUS_PENDING, $asal->status);
        $this->assertNull($asal->direview_oleh);
        $this->assertSame(1, (int) $asal->jumlah_karyawan);
        $this->assertSame(1, (int) $asal->jumlah_jadwal);
        $this->assertSame(['B1'], $this->sidDi($asal->id));
    }

    public function test_periode_tiap_upload_mengikuti_barisnya(): void
    {
        $asal = $this->uploadTigaKaryawan();

        $hasil = $this->review($asal, ['A1', 'A2'], true);

        $this->assertSame('2026-12-01', $hasil->periode_mulai->toDateString());
        $this->assertSame('2026-12-02', $hasil->periode_selesai->toDateString());
        $asal->refresh();
        $this->assertSame('2026-12-03', $asal->periode_mulai->toDateString());
        $this->assertSame('2026-12-03', $asal->periode_selesai->toDateString());
    }

    public function test_tanggal_upload_dipertahankan_agar_urutan_roster_terbaru_tidak_berubah(): void
    {
        $asal = $this->uploadTigaKaryawan();

        $hasil = $this->review($asal, ['A1', 'A2'], true);

        $this->assertSame('2026-11-01 08:00:00', $hasil->created_at->toDateTimeString());
    }

    public function test_tanda_tangan_disimpan_atas_upload_baru_dan_file_asli_ikut_tersalin(): void
    {
        $asal = $this->uploadTigaKaryawan();
        $idTtd = null;

        $hasil = app(DmsRosterReviewDepartemen::class)->review($asal, ['A1', 'A2'], self::HSE, true, null, 'Pak HSE',
            function (Upload $u) use (&$idTtd): array {
                $idTtd = $u->id;

                return self::TTD;
            });

        $this->assertSame($hasil->id, $idTtd);
        $this->assertSame('ttd.png', $hasil->ttd_path);
        $this->assertSame('roster-master/'.$hasil->id.'/roster.xlsx', $hasil->path_file);
        Storage::disk('local')->assertExists($hasil->path_file);
        Storage::disk('local')->assertExists($asal->path_file);
    }

    public function test_tolak_hanya_menolak_baris_departemen(): void
    {
        $asal = $this->uploadTigaKaryawan();

        $hasil = $this->review($asal, ['A1', 'A2'], false, 'Jadwal belum sesuai');

        $this->assertSame(Upload::STATUS_REJECTED, $hasil->status);
        $this->assertSame('Jadwal belum sesuai', $hasil->catatan_review);
        $this->assertNull($hasil->ttd_path);
        $this->assertSame(Upload::STATUS_PENDING, $asal->refresh()->status);
    }

    public function test_file_yang_seluruhnya_departemen_ini_direview_tanpa_dipecah(): void
    {
        $asal = $this->uploadTigaKaryawan();

        $hasil = $this->review($asal, ['A1', 'A2', 'B1'], true);

        $this->assertSame($asal->id, $hasil->id);
        $this->assertSame(Upload::STATUS_APPROVED, $hasil->status);
        $this->assertSame(1, DB::table('dms_roster_master_upload')->count());
    }

    public function test_dua_manager_berurutan_masing_masing_hanya_departemennya(): void
    {
        $asal = $this->uploadTigaKaryawan();

        $pertama = $this->review($asal, ['A1'], true);
        $kedua = $this->review($asal->refresh(), ['A2'], true);

        $this->assertSame(['A1'], $this->sidDi($pertama->id));
        $this->assertSame(['A2'], $this->sidDi($kedua->id));
        $this->assertSame(['B1'], $this->sidDi($asal->id));
        $this->assertSame(Upload::STATUS_PENDING, $asal->refresh()->status);
    }

    public function test_tanpa_karyawan_departemen_ditolak_tanpa_perubahan(): void
    {
        $asal = $this->uploadTigaKaryawan();

        try {
            $this->review($asal, ['Z9'], true);
            $this->fail('Seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak memuat', $e->getMessage());
        }

        $this->assertSame(1, DB::table('dms_roster_master_upload')->count());
        $this->assertSame(['A1', 'A2', 'B1'], $this->sidDi($asal->id));
    }

    public function test_roster_yang_sudah_direview_tidak_bisa_diulang(): void
    {
        $asal = $this->uploadTigaKaryawan();
        $asal->update(['status' => Upload::STATUS_APPROVED]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sudah direview');

        $this->review($asal, ['A1'], true);
    }

    public function test_tanda_tangan_gagal_membatalkan_seluruh_pemecahan(): void
    {
        $asal = $this->uploadTigaKaryawan();

        try {
            app(DmsRosterReviewDepartemen::class)->review($asal, ['A1'], self::HSE, true, null, 'Pak HSE',
                static fn (Upload $u): array => throw new RuntimeException('Tanda tangan belum digambar.'));
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException $e) {
            $this->assertSame('Tanda tangan belum digambar.', $e->getMessage());
        }

        $this->assertSame(1, DB::table('dms_roster_master_upload')->count());
        $this->assertSame(['A1', 'A2', 'B1'], $this->sidDi($asal->id));
    }

    public function test_riwayat_edit_ikut_pindah_bersama_barisnya(): void
    {
        Schema::create('dms_roster_master_edit_log', static function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('upload_id')->nullable();
            $t->string('kode_sid');
        });
        $asal = $this->uploadTigaKaryawan();
        DB::table('dms_roster_master_edit_log')->insert([
            ['upload_id' => $asal->id, 'kode_sid' => 'A1'],
            ['upload_id' => $asal->id, 'kode_sid' => 'B1'],
        ]);

        $hasil = $this->review($asal, ['A1'], true);

        $this->assertSame((int) $hasil->id, (int) DB::table('dms_roster_master_edit_log')->where('kode_sid', 'A1')->value('upload_id'));
        $this->assertSame((int) $asal->id, (int) DB::table('dms_roster_master_edit_log')->where('kode_sid', 'B1')->value('upload_id'));
    }

    public function test_grid_dan_tumpang_bisa_dibatasi_ke_karyawan_departemen(): void
    {
        $asal = $this->uploadTigaKaryawan();
        $lain = Upload::query()->create($this->dataUpload(['status' => Upload::STATUS_APPROVED]));
        DB::table('dms_roster_master_jadwal')->insert([
            $this->baris($lain->id, 'A1', '2026-12-01'),
            $this->baris($lain->id, 'B1', '2026-12-03'),
        ]);
        $master = app(DmsRosterMasterService::class);

        $grid = $master->grid($asal, 1, 25, '', null, ['A1', 'A2']);

        $this->assertSame(2, $grid['total']);
        $this->assertSame(['A1', 'A2'], array_column($grid['karyawan'], 'sid'));
        $this->assertSame(2, $master->jumlahTumpang($asal));
        $this->assertSame(1, $master->jumlahTumpang($asal, ['A1', 'A2']));
    }

    /** @param  list<string>  $sids */
    private function review(Upload $upload, array $sids, bool $setuju, ?string $catatan = null): Upload
    {
        return app(DmsRosterReviewDepartemen::class)->review($upload, $sids, self::HSE, $setuju, $catatan, 'Pak HSE',
            static fn (Upload $u): ?array => $setuju ? self::TTD : null);
    }

    /** A1 punya 2 hari, A2 dan B1 masing-masing 1 hari. */
    private function uploadTigaKaryawan(): Upload
    {
        $upload = Upload::query()->create($this->dataUpload([
            'path_file' => 'roster-master/1/roster.xlsx',
            'jumlah_karyawan' => 3,
            'jumlah_jadwal' => 4,
        ]));
        DB::table('dms_roster_master_upload')->where('id', $upload->id)->update(['created_at' => '2026-11-01 08:00:00']);
        Storage::disk('local')->put('roster-master/1/roster.xlsx', 'isi');
        DB::table('dms_roster_master_jadwal')->insert([
            $this->baris($upload->id, 'A1', '2026-12-01'),
            $this->baris($upload->id, 'A1', '2026-12-02'),
            $this->baris($upload->id, 'A2', '2026-12-01'),
            $this->baris($upload->id, 'B1', '2026-12-03'),
        ]);

        return $upload->refresh();
    }

    /** @return array<string, mixed> */
    private function dataUpload(array $ubah = []): array
    {
        return $ubah + [
            'judul' => 'ROSTER BC BMO 1',
            'periode_mulai' => '2026-12-01',
            'periode_selesai' => '2026-12-03',
            'nama_file' => 'roster.xlsx',
            'path_file' => '',
            'jumlah_karyawan' => 0,
            'jumlah_jadwal' => 0,
            'status' => Upload::STATUS_PENDING,
            'diupload_oleh' => 'Pengupload',
        ];
    }

    /** @return array<string, mixed> */
    private function baris(int $uploadId, string $sid, string $tanggal): array
    {
        return ['upload_id' => $uploadId, 'kode_sid' => $sid, 'nama' => 'Nama '.$sid, 'perusahaan' => 'PT Berau Coal', 'tanggal' => $tanggal, 'kode' => 'DS'];
    }

    /** @return list<string> */
    private function sidDi(int $uploadId): array
    {
        return DB::table('dms_roster_master_jadwal')->where('upload_id', $uploadId)
            ->distinct()->orderBy('kode_sid')->pluck('kode_sid')->all();
    }

    private function buatTabel(): void
    {
        Schema::create('dms_roster_master_upload', static function (Blueprint $t): void {
            $t->id();
            $t->string('judul', 150);
            $t->date('periode_mulai');
            $t->date('periode_selesai');
            $t->string('departemen_site', 255)->nullable();
            $t->string('nama_file', 255);
            $t->string('path_file', 500);
            $t->unsignedInteger('jumlah_karyawan')->default(0);
            $t->unsignedInteger('jumlah_jadwal')->default(0);
            $t->string('status', 20)->default('PENDING');
            $t->text('catatan')->nullable();
            $t->string('diupload_oleh', 150);
            $t->string('direview_oleh', 150)->nullable();
            $t->timestamp('direview_pada')->nullable();
            $t->text('catatan_review')->nullable();
            $t->string('ttd_path')->nullable();
            $t->string('ttd_sumber')->nullable();
            $t->timestamps();
        });
        Schema::create('dms_roster_master_jadwal', static function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('upload_id');
            $t->string('kode_sid', 20);
            $t->string('nama', 150)->nullable();
            $t->string('perusahaan', 255)->nullable();
            $t->string('jabatan', 150)->nullable();
            $t->string('site', 60)->nullable();
            $t->date('tanggal');
            $t->string('kode', 4);
            $t->time('jam_masuk')->nullable();
            $t->time('jam_keluar')->nullable();
            $t->unsignedSmallInteger('menit_kerja')->nullable();
        });
    }
}
