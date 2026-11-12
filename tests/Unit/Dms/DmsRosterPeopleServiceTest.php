<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterPeopleService as People;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/** Tab People / Org chart / Time off di Overview Roster. */
class DmsRosterPeopleServiceTest extends TestCase
{
    private function k(string $sid, string $nama, string $pt, string $site, string $dept, string $fungsional, string $jabatan = ''): array
    {
        return ['sid' => $sid, 'nama' => $nama, 'perusahaan' => $pt, 'site' => $site, 'departemen' => $dept, 'divisi' => '',
            'jabatan' => $jabatan, 'fungsional' => $fungsional, 'level' => '', 'permit' => 'PASSED', 'mulai' => null];
    }

    /** @return list<array<string, mixed>> */
    private function data(): array
    {
        return [
            $this->k('A1', 'Budi', 'PT Alfa', 'BMO 1', 'Mining', 'Operator'),
            $this->k('A2', 'Ani', 'PT Alfa', 'BMO 1', 'Mining', 'Manager'),
            $this->k('A3', 'Cici', 'PT Alfa', 'BMO 1', 'Mining', 'Foreman/Group Leader'),
            $this->k('A4', 'Dodi', 'PT Alfa', 'LMO', '', '', 'Staff Gudang'),
            $this->k('B1', 'Eko', 'PT Beta', '', 'HR', 'Superintendent'),
        ];
    }

    public function test_org_chart_pt_site_departemen_jabatan_urut_tingkat(): void
    {
        $pohon = People::org($this->data());

        $this->assertSame(['PT Alfa', 'PT Beta'], array_column($pohon, 'nama'));
        $this->assertSame([4, 1], array_column($pohon, 'jumlah'));
        $alfa = $pohon[0];
        $this->assertSame(['BMO 1', 'LMO'], array_column($alfa['site'], 'nama'));
        $mining = $alfa['site'][0]['departemen'][0];
        $this->assertSame('Mining', $mining['nama']);
        // Manager di atas Foreman di atas Operator.
        $this->assertSame(['Manager', 'Foreman/Group Leader', 'Operator'], array_column($mining['jabatan'], 'nama'));
        // Tanpa departemen & tanpa fungsional: pakai label & jabatan struktural.
        $this->assertSame('Tanpa departemen', $alfa['site'][1]['departemen'][0]['nama']);
        $this->assertSame('Staff Gudang', $alfa['site'][1]['departemen'][0]['jabatan'][0]['nama']);
        $this->assertSame('-', $pohon[1]['site'][0]['nama']);
    }

    public function test_saring_dan_opsi(): void
    {
        $data = $this->data();

        $this->assertSame(['A1', 'A2', 'A3'], array_column(People::saring($data, 'PT Alfa', 'BMO 1'), 'sid'));
        $this->assertSame(['B1'], array_column(People::saring($data, '', '-'), 'sid'));
        $this->assertSame(['A2'], array_column(People::saring($data, '', '', 'manager'), 'sid'));
        $this->assertSame(['PT Alfa' => ['BMO 1', 'LMO'], 'PT Beta' => ['-']], People::opsi($data));
    }

    public function test_time_off_bulan_ini_hanya_karyawan_terpilih(): void
    {
        $tidakMasuk = [
            ['PT Alfa', 'BMO 1', 'A1', 'Budi', '2026-11-03', 'CT'],
            ['PT Alfa', 'BMO 1', 'A1', 'Budi', '2026-11-04', 'CT'],
            ['PT Alfa', 'BMO 1', 'A2', 'Ani', '2026-11-04', 'S'],
            ['PT Alfa', 'BMO 1', 'A3', 'Cici', '2026-10-31', 'I'],   // bulan lain
            ['PT Gamma', 'X', 'Z9', 'Luar', '2026-11-05', 'I'],       // bukan karyawan terpilih
        ];
        $hasil = People::timeOff($tidakMasuk, People::saring($this->data(), 'PT Alfa'), CarbonImmutable::parse('2026-11-01'));

        $this->assertSame(['A1', 'A2'], array_column($hasil['orang'], 'sid'));
        $this->assertSame(['2026-11-03' => 'CT', '2026-11-04' => 'CT'], $hasil['orang'][0]['hari']);
        $this->assertSame(['CT' => 2, 'S' => 1, 'I' => 0], $hasil['total']);
        $this->assertSame(['CT' => 1, 'S' => 1], $hasil['per_hari']['2026-11-04']);
        $this->assertSame([['nama' => 'Mining', 'karyawan' => 3, 'tidak_masuk' => 2, 'hari' => 3]], $hasil['per_departemen']);
    }

    public function test_tanggal_mulai(): void
    {
        $this->assertSame('2026-06-17', People::tanggalMulai('17-06-2026'));
        $this->assertSame('2026-06-17', People::tanggalMulai('2026-06-17 00:00:00'));
        $this->assertNull(People::tanggalMulai('31-02-2026'));
        $this->assertNull(People::tanggalMulai(''));
    }
}
