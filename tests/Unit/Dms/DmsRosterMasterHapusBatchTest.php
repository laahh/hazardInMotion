<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Models\DmsRosterMasterUpload as Upload;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Hapus satu batch roster salah upload: hanya pengupload atau admin, hanya
 * selama belum disetujui.
 */
class DmsRosterMasterHapusBatchTest extends TestCase
{
    public function test_pengupload_boleh_hapus_selama_belum_disetujui(): void
    {
        $budi = $this->user('Budi Santoso');

        $this->assertTrue($this->upload(Upload::STATUS_PENDING, ' budi santoso ')->bolehDihapusOleh($budi));
        $this->assertTrue($this->upload(Upload::STATUS_REJECTED, 'Budi Santoso')->bolehDihapusOleh($budi));
        $this->assertFalse($this->upload(Upload::STATUS_APPROVED, 'Budi Santoso')->bolehDihapusOleh($budi));
    }

    public function test_user_lain_tidak_boleh_admin_boleh(): void
    {
        $roster = $this->upload(Upload::STATUS_PENDING, 'Budi Santoso');

        $this->assertFalse($roster->bolehDihapusOleh($this->user('Ani')));
        $this->assertTrue($roster->bolehDihapusOleh($this->user('Admin DMS', 'admin')));
        $this->assertFalse($this->upload(Upload::STATUS_APPROVED, 'Budi Santoso')->bolehDihapusOleh($this->user('Admin DMS', 'admin')));
        $this->assertFalse($roster->bolehDihapusOleh(null));
    }

    public function test_tombol_hapus_hanya_untuk_yang_berhak(): void
    {
        Auth::setUser($this->user('Budi Santoso'));
        $daftar = new LengthAwarePaginator([
            $this->upload(Upload::STATUS_PENDING, 'Budi Santoso', 11, 'Roster Salah'),
            $this->upload(Upload::STATUS_PENDING, 'Ani', 12, 'Roster Ani'),
            $this->upload(Upload::STATUS_APPROVED, 'Budi Santoso', 13, 'Roster Oke'),
        ], 3, 30);

        $html = view('dms.roster-master._daftar', ['daftar' => $daftar, 'hapus' => true])->render();
        $this->assertStringContainsString('action="/dms/master-roster/11"', $html);
        $this->assertStringNotContainsString('action="/dms/master-roster/12"', $html);
        $this->assertStringNotContainsString('action="/dms/master-roster/13"', $html);
        $this->assertStringContainsString('Hapus batch roster “Roster Salah”?', html_entity_decode($html));

        // Halaman Approval tidak menampilkan tombol hapus.
        $this->assertStringNotContainsString('action="/dms/master-roster/11"', view('dms.roster-master._daftar', ['daftar' => $daftar])->render());
    }

    private function user(string $nama, string $role = 'user'): User
    {
        // isAdmin() asli ikut memeriksa tabel role di DB; di sini cukup kolom role.
        $user = new class extends User
        {
            public function isAdmin(): bool
            {
                return $this->role === 'admin';
            }
        };

        return $user->forceFill(['id' => crc32($nama), 'name' => $nama, 'role' => $role]);
    }

    private function upload(string $status, string $oleh, int $id = 1, string $judul = 'Roster'): Upload
    {
        return (new Upload())->forceFill([
            'id' => $id, 'judul' => $judul, 'nama_file' => 'roster.xlsx', 'status' => $status, 'diupload_oleh' => $oleh,
            'periode_mulai' => '2026-11-01', 'periode_selesai' => '2026-11-30', 'jumlah_karyawan' => 40, 'jumlah_jadwal' => 1200,
        ]);
    }
}
