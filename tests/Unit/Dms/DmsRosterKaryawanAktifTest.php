<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterKaryawanAktif as Aktif;
use Tests\TestCase;

/**
 * Bagian murni DmsRosterKaryawanAktif: jabatan VISITOR tidak dihitung sebagai
 * karyawan aktif (Overview cakupan & template roster).
 */
class DmsRosterKaryawanAktifTest extends TestCase
{
    public function test_visitor_dibuang_tanpa_membedakan_huruf(): void
    {
        $hasil = Aktif::rapikan([
            (object) ['sid' => 'a1', 'nama' => 'Ali', 'perusahaan' => 'PT X', 'jabatan' => 'Operator HD', 'site' => 'GMO'],
            (object) ['sid' => 'B2', 'nama' => 'Budi', 'perusahaan' => 'PT X', 'jabatan' => 'VISITOR', 'site' => 'GMO'],
            (object) ['sid' => 'C3', 'nama' => 'Cici', 'perusahaan' => 'PT X', 'jabatan' => ' Visitor ', 'site' => ''],
            (object) ['sid' => 'D4', 'nama' => 'Dedi', 'perusahaan' => 'PT X', 'jabatan' => 'visitor', 'site' => ''],
        ]);

        $this->assertSame(['A1'], array_column($hasil, 'sid'));
    }

    public function test_jabatan_lain_yang_mengandung_kata_visitor_tidak_ikut_dibuang(): void
    {
        $this->assertTrue(Aktif::dikecualikan('VISITOR'));
        $this->assertFalse(Aktif::dikecualikan('Visitor Coordinator'));
        $this->assertFalse(Aktif::dikecualikan(''));
    }

    public function test_halaman_memakai_salinan_lengkap_tanpa_query_view(): void
    {
        // PembatasanLVOlapQuery final (tak bisa di-mock): bukti tidak ada query
        // view = hasil sama persis dengan isi salinan & selesai cepat.
        $k =static fn (string $sid, string $pt, string $site, string $jab = 'Operator'): array => ['sid' => $sid, 'nama' => $sid, 'perusahaan' => $pt, 'jabatan' => $jab, 'site' => $site];
        $kunci = ['dms_roster:karyawan_aktif:v1', 'dms_roster:karyawan_aktif:pt:v3'];
        $simpan = array_map(static fn (string $c): mixed => \Illuminate\Support\Facades\Cache::get($c), $kunci);
        \Illuminate\Support\Facades\Cache::forget($kunci[1]);
        \Illuminate\Support\Facades\Cache::put($kunci[0], ['diambil' => 'uji', 'karyawan' => [
            $k('A1', 'PT X', 'GMO'), $k('B2', 'PT X', 'GMO'), $k('C3', 'PT X', ''), $k('D4', 'PT X', 'GMO', 'VISITOR'),
        ]], 60);

        try {
            $aktif = app(Aktif::class);
            $mulai = microtime(true);
            $pt = $aktif->perusahaan();
            $this->assertLessThan(1.0, microtime(true) - $mulai);
            $this->assertSame('PT X', $pt[0]['nama']);
            $this->assertSame(3, $pt[0]['jumlah']);
            $this->assertSame(['A1', 'B2'], array_column($aktif->untukPerusahaan('PT X', 'GMO'), 'sid'));
        } finally {
            foreach ($kunci as $i => $c) {
                $simpan[$i] === null ? \Illuminate\Support\Facades\Cache::forget($c) : \Illuminate\Support\Facades\Cache::put($c, $simpan[$i], 60);
            }
        }
    }

    public function test_daftar_jabatan_dikecualikan_dari_config(): void
    {
        config(['dms_roster.master.jabatan_dikecualikan' => ['visitor', 'Magang']]);

        $this->assertSame(['VISITOR', 'MAGANG'], Aktif::jabatanDikecualikan());
        $this->assertTrue(Aktif::dikecualikan('magang'));
    }
}
