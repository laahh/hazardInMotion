<?php

declare(strict_types=1);

namespace Tests\Feature\PraOperasi;

use App\Enums\AutoBannedUnbanStatus;
use App\Models\PengawasUnbanRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

/**
 * Form publik /form/pengajuan-treatment-banned sebagai pengajuan unbanned
 * pengawas. Memakai SQLite di memori, tidak pernah menyentuh database aplikasi
 * (php -d extension=pdo_sqlite vendor/bin/phpunit tests/Feature/PraOperasi/PengawasUnbanPublicTest.php).
 */
class PengawasUnbanPublicTest extends TestCase
{
    private const URL = '/form/pengajuan-treatment-banned';

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite tidak aktif.');
        }
        config(['database.connections.unban_tes' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'database.default' => 'unban_tes']);
        DB::purge('unban_tes');
        Storage::fake('local');

        Schema::create('validasi_status_banned_pengawas', function (Blueprint $t) {
            $t->date('tanggal_kerja');
            $t->string('sid', 20);
            $t->string('nik', 50)->nullable();
            $t->text('nama')->nullable();
            $t->string('site', 30)->nullable();
            $t->text('perusahaan')->nullable();
            $t->string('status_banned', 60)->nullable();
            $t->text('alasan_banned')->nullable();
            $t->primary(['tanggal_kerja', 'sid']);
        });
        $this->buatTabelPengajuan();

        $baris = static fn (string $tanggal, string $sid, string $status): array => [
            'tanggal_kerja' => $tanggal, 'sid' => $sid, 'nik' => 'NIK-'.$sid, 'nama' => 'Pengawas '.$sid, 'site' => 'BMO 1',
            'perusahaan' => 'PT Contoh', 'status_banned' => $status, 'alasan_banned' => 'HARI BERJALAN, INS 0/1',
        ];
        DB::table('validasi_status_banned_pengawas')->insert([
            $baris('2026-11-05', '04DC0', 'BANNED SAP'),
            $baris('2026-11-06', '04DC0', 'BANNED SAP'),
            $baris('2026-11-06', 'AMAN1', 'TIDAK BANNED'),
        ]);
    }

    public function test_form_tampil_dengan_judul_pengawas(): void
    {
        $this->get(self::URL)->assertOk()->assertSee('Form Pengajuan Unbanned Pengawas');
    }

    public function test_cari_sid_mengembalikan_tanggal_banned_terbaru_dulu_tanpa_nik(): void
    {
        $r = $this->getJson(self::URL.'/lookup-sid?sid=%2004dc0%20')->assertOk();

        $r->assertJsonPath('found', true)
            ->assertJsonPath('can_submit', true)
            ->assertJsonPath('data.karyawan', 'Pengawas 04DC0')
            ->assertJsonPath('banned_log_options.0.value', '2026-11-06')
            ->assertJsonPath('banned_log_options.1.value', '2026-11-05');
        $this->assertStringNotContainsString('NIK-04DC0', $r->getContent());
    }

    public function test_sid_tidak_banned_atau_tidak_dikenal_tidak_ditemukan(): void
    {
        $this->getJson(self::URL.'/lookup-sid?sid=AMAN1')->assertJsonPath('found', false);
        $this->getJson(self::URL.'/lookup-sid?sid=TIDAKADA')->assertJsonPath('found', false);
    }

    public function test_kirim_menyimpan_salinan_master_dan_file_bukti(): void
    {
        $this->post(self::URL, $this->isian())
            ->assertRedirect()
            ->assertRedirectContains('/sukses');

        $p = PengawasUnbanRequest::query()->sole();
        $this->assertSame('2026-11-06', $p->tanggal_kerja->toDateString());
        $this->assertSame('04DC0', $p->sid);
        $this->assertSame('NIK-04DC0', $p->nik);
        $this->assertSame('Pengawas 04DC0', $p->karyawan);
        $this->assertSame('BMO 1', $p->site_dedicated);
        $this->assertSame('BANNED SAP', $p->status_banned_ref);
        $this->assertSame('HARI BERJALAN, INS 0/1', $p->banned_reason);
        $this->assertSame(AutoBannedUnbanStatus::Pending, $p->status);
        $this->assertSame('W45', $p->week);
        $this->assertSame('2026', $p->iso_year);
        $this->assertSame('6281234567890', $p->no_hp);
        Storage::disk('local')->assertExists($p->evidence_file_path);
    }

    public function test_tanggal_yang_sudah_diajukan_ditolak_dan_hilang_dari_pilihan(): void
    {
        $this->post(self::URL, $this->isian());

        $this->post(self::URL, $this->isian())->assertSessionHasErrors('tanggal_kerja');
        $this->assertSame(1, PengawasUnbanRequest::query()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('pengawas-unban'));

        $this->getJson(self::URL.'/lookup-sid?sid=04DC0')
            ->assertJsonCount(1, 'banned_log_options')
            ->assertJsonPath('banned_log_options.0.value', '2026-11-05');
    }

    public function test_semua_tanggal_terpakai_membuat_can_submit_false(): void
    {
        $this->post(self::URL, $this->isian());
        $this->post(self::URL, $this->isian(['tanggal_kerja' => '2026-11-05']));

        $this->getJson(self::URL.'/lookup-sid?sid=04DC0')
            ->assertJsonPath('found', true)
            ->assertJsonPath('can_submit', false)
            ->assertJsonCount(0, 'banned_log_options');
    }

    public function test_pengajuan_ditolak_boleh_diajukan_ulang(): void
    {
        $this->post(self::URL, $this->isian());
        PengawasUnbanRequest::query()->update(['status' => AutoBannedUnbanStatus::Rejected]);

        $this->post(self::URL, $this->isian())->assertSessionHasNoErrors();

        $this->assertSame(2, PengawasUnbanRequest::query()->count());
    }

    public function test_tanggal_atau_sid_di_luar_master_ditolak_tanpa_menyimpan_file(): void
    {
        $this->post(self::URL, $this->isian(['tanggal_kerja' => '2026-01-01']))->assertSessionHasErrors('tanggal_kerja');
        $this->post(self::URL, $this->isian(['sid' => 'AMAN1']))->assertSessionHasErrors('tanggal_kerja');

        $this->assertSame(0, PengawasUnbanRequest::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('pengawas-unban'));
    }

    public function test_file_wajib_dan_format_berbahaya_ditolak(): void
    {
        $tanpaFile = $this->isian();
        unset($tanpaFile['evidence_file']);

        $this->post(self::URL, $tanpaFile)->assertSessionHasErrors('evidence_file');
        $this->post(self::URL, $this->isian(['evidence_file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php')]))
            ->assertSessionHasErrors('evidence_file');
        $this->assertSame(0, PengawasUnbanRequest::query()->count());
    }

    public function test_honeypot_terisi_ditolak(): void
    {
        $this->post(self::URL, $this->isian(['website' => 'http://spam.test']))->assertForbidden();
        $this->assertSame(0, PengawasUnbanRequest::query()->count());
    }

    public function test_tabel_belum_dimigrasi_cari_tetap_jalan_dan_kirim_memberi_pesan(): void
    {
        Schema::drop('pengawas_unban_requests');

        $this->getJson(self::URL.'/lookup-sid?sid=04DC0')->assertOk()->assertJsonPath('can_submit', true);
        $this->post(self::URL, $this->isian())->assertSessionHasErrors('sid');
        $this->assertSame([], Storage::disk('local')->allFiles('pengawas-unban'));
    }

    /**
     * @param  array<string, mixed>  $timpa
     * @return array<string, mixed>
     */
    private function isian(array $timpa = []): array
    {
        return $timpa + [
            'sid' => '04dc0',
            'tanggal_kerja' => '2026-11-06',
            'no_hp' => '081234567890',
            'alasan_pengajuan' => 'Target INS sudah dipenuhi.',
            'evidence_file' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ];
    }

    /** Memakai migration sungguhan supaya skemanya ikut teruji. */
    private function buatTabelPengajuan(): void
    {
        (require database_path('migrations/2026_11_07_100000_create_pengawas_unban_requests_table.php'))->up();
    }
}
