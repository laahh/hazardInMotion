<?php

declare(strict_types=1);

namespace Tests\Feature\PraOperasi;

use App\Enums\AutoBannedUnbanStatus;
use App\Models\PengawasUnbanRequest;
use Carbon\CarbonImmutable;
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
        $this->buatTabelMingguan();
    }

    // ------------------------------------------------------------ mingguan

    public function test_cari_sid_menggabungkan_riwayat_harian_dan_mingguan(): void
    {
        $this->getJson(self::URL.'/lookup-sid?sid=04DC0')
            ->assertJsonCount(3, 'banned_log_options')
            ->assertJsonPath('banned_log_options.0.value', '2026-11-06')
            ->assertJsonPath('banned_log_options.1.value', '2026-11-05')
            ->assertJsonPath('banned_log_options.2.value', 'mingguan|2026-11-02')
            ->assertJsonPath('banned_log_options.2.label', 'W45 2026 (02 Nov – 08 Nov 2026) — Mingguan — PERIODE MINGGUAN, OBS 1/3 (BMO 1)');
    }

    public function test_sid_hanya_ada_di_mingguan_tetap_ditemukan(): void
    {
        $this->getJson(self::URL.'/lookup-sid?sid=MGG01')
            ->assertJsonPath('found', true)
            ->assertJsonPath('data.karyawan', 'Pengawas Mingguan')
            ->assertJsonCount(1, 'banned_log_options')
            ->assertJsonPath('banned_log_options.0.value', 'mingguan|2026-11-09');
    }

    public function test_kirim_mingguan_disimpan_dengan_sumber_dan_senin_awal_minggu(): void
    {
        $this->post(self::URL, $this->isian(['tanggal_kerja' => 'mingguan|2026-11-02']))->assertRedirectContains('/sukses');

        $p = PengawasUnbanRequest::query()->sole();
        $this->assertSame('mingguan', $p->sumber);
        $this->assertSame('2026-11-02', $p->tanggal_kerja->toDateString());
        $this->assertSame('PERIODE MINGGUAN, OBS 1/3', $p->banned_reason);
        $this->assertSame('W45', $p->week);

        // Minggu itu hilang dari pilihan, tanggal harian tetap ada.
        $this->getJson(self::URL.'/lookup-sid?sid=04DC0')
            ->assertJsonCount(2, 'banned_log_options')
            ->assertJsonPath('banned_log_options.1.value', '2026-11-05');
        $this->post(self::URL, $this->isian(['tanggal_kerja' => 'mingguan|2026-11-02']))->assertSessionHasErrors('tanggal_kerja');
    }

    public function test_harian_dan_mingguan_pada_tanggal_sama_tidak_saling_mengunci(): void
    {
        DB::table('validasi_status_banned_pengawas')->insert([
            'tanggal_kerja' => '2026-11-02', 'sid' => '04DC0', 'nama' => 'Pengawas 04DC0', 'status_banned' => 'BANNED SAP', 'alasan_banned' => 'X',
        ]);
        $this->post(self::URL, $this->isian(['tanggal_kerja' => '2026-11-02']))->assertSessionHasNoErrors();
        $this->post(self::URL, $this->isian(['tanggal_kerja' => 'mingguan|2026-11-02']))->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['harian', 'mingguan'], PengawasUnbanRequest::query()->pluck('sumber')->all());
    }

    public function test_tabel_mingguan_dengan_nomor_minggu_dan_tahun(): void
    {
        Schema::drop('validasi_status_banned_pengawas_weekly');
        Schema::create('validasi_status_banned_pengawas_weekly', function (Blueprint $t) {
            $t->string('sid', 20);
            $t->string('minggu_ke', 5);
            $t->integer('tahun');
            $t->text('nama')->nullable();
            $t->string('status_banned', 60)->nullable();
            $t->text('alasan_banned')->nullable();
        });
        DB::table('validasi_status_banned_pengawas_weekly')->insert(['sid' => '04DC0', 'minggu_ke' => 'W44', 'tahun' => 2026, 'nama' => 'X', 'status_banned' => 'BANNED SAP']);

        $this->getJson(self::URL.'/lookup-sid?sid=04DC0')->assertJsonPath('banned_log_options.2.value', 'mingguan|2026-10-26');
        $this->post(self::URL, $this->isian(['tanggal_kerja' => 'mingguan|2026-10-26']))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-26', PengawasUnbanRequest::query()->sole()->tanggal_kerja->toDateString());
    }

    public function test_tanpa_kolom_sumber_mingguan_disembunyikan_dan_ditolak(): void
    {
        Schema::drop('pengawas_unban_requests');
        $this->buatTabelPengajuan(false);

        $this->getJson(self::URL.'/lookup-sid?sid=04DC0')->assertJsonCount(2, 'banned_log_options');
        $this->post(self::URL, $this->isian(['tanggal_kerja' => 'mingguan|2026-11-02']))->assertSessionHasErrors('tanggal_kerja');
        $this->post(self::URL, $this->isian())->assertSessionHasNoErrors();
    }

    public function test_tabel_mingguan_tidak_ada_form_tetap_jalan_harian_saja(): void
    {
        Schema::drop('validasi_status_banned_pengawas_weekly');

        $this->getJson(self::URL.'/lookup-sid?sid=04DC0')->assertJsonCount(2, 'banned_log_options');
        $this->getJson(self::URL.'/lookup-sid?sid=MGG01')->assertJsonPath('found', false);
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
            ->assertJsonCount(2, 'banned_log_options')
            ->assertJsonPath('banned_log_options.0.value', '2026-11-05');
    }

    public function test_semua_tanggal_terpakai_membuat_can_submit_false(): void
    {
        $this->post(self::URL, $this->isian());
        $this->post(self::URL, $this->isian(['tanggal_kerja' => '2026-11-05']));
        $this->post(self::URL, $this->isian(['tanggal_kerja' => 'mingguan|2026-11-02']));

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
    private function buatTabelPengajuan(bool $denganSumber = true): void
    {
        (require database_path('migrations/2026_11_07_100000_create_pengawas_unban_requests_table.php'))->up();
        if ($denganSumber) {
            (require database_path('migrations/2026_11_15_110000_add_sumber_to_pengawas_unban_requests_table.php'))->up();
        }
    }

    /** Struktur tabel mingguan contoh (asumsi): tanggal awal & akhir minggu. */
    private function buatTabelMingguan(): void
    {
        Schema::create('validasi_status_banned_pengawas_weekly', function (Blueprint $t) {
            $t->date('minggu_mulai');
            $t->date('minggu_selesai');
            $t->string('sid', 20);
            $t->string('nik', 50)->nullable();
            $t->text('nama')->nullable();
            $t->string('site', 30)->nullable();
            $t->text('perusahaan')->nullable();
            $t->string('status_banned', 60)->nullable();
            $t->text('alasan_banned')->nullable();
        });
        $baris = static fn (string $mulai, string $sid, string $nama, string $status): array => [
            'minggu_mulai' => $mulai, 'minggu_selesai' => CarbonImmutable::parse($mulai)->addDays(6)->toDateString(), 'sid' => $sid,
            'nik' => 'NIK-'.$sid, 'nama' => $nama, 'site' => 'BMO 1', 'perusahaan' => 'PT Contoh', 'status_banned' => $status,
            'alasan_banned' => 'PERIODE MINGGUAN, OBS 1/3',
        ];
        DB::table('validasi_status_banned_pengawas_weekly')->insert([
            $baris('2026-11-02', '04DC0', 'Pengawas 04DC0', 'BANNED SAP'),
            $baris('2026-11-09', 'MGG01', 'Pengawas Mingguan', 'BANNED SAP'),
            $baris('2026-11-02', 'AMAN1', 'Aman', 'NOT BANNED'),
        ]);
    }
}
