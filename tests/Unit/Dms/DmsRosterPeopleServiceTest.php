<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterPeopleService as People;
use Carbon\CarbonImmutable;
use Tests\TestCase;

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
        $pohon = People::org(People::grupDariOrang($this->data()));

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

    public function test_org_dari_grup_jumlah_clickhouse_tanpa_nama(): void
    {
        $pohon = People::org([
            ['perusahaan' => 'PT Alfa', 'site' => 'BMO 1', 'departemen' => 'Mining', 'jabatan' => 'Operator', 'jumlah' => 120, 'orang' => []],
            ['perusahaan' => 'PT Alfa', 'site' => 'BMO 1', 'departemen' => 'Mining', 'jabatan' => 'Manager', 'jumlah' => 2, 'orang' => []],
            ['perusahaan' => 'PT Alfa', 'site' => '', 'departemen' => '', 'jabatan' => '', 'jumlah' => 3, 'orang' => []],
        ]);

        $this->assertSame(125, $pohon[0]['jumlah']);
        $this->assertSame(['-', 'BMO 1'], array_column($pohon[0]['site'], 'nama'));
        $this->assertSame(['Manager', 'Operator'], array_column($pohon[0]['site'][1]['departemen'][0]['jabatan'], 'nama'));
        $this->assertSame('Tanpa departemen', $pohon[0]['site'][0]['departemen'][0]['nama']);
    }

    public function test_saringan_clickhouse_meng_escape_input(): void
    {
        $opsi = ['PT Alfa' => ['BMO 1' => 3], "PT O'Neil" => ['-' => 1]];

        $w = People::saringan($opsi, "PT O'Neil", '-', "50% o'k");
        $this->assertStringContainsString("= 'PT O\'Neil'", $w);
        $this->assertStringContainsString("trimBoth(ifNull(site_dedicated, '')) = ''", $w);
        // % dari input di-escape jadi \% untuk ILIKE, lalu backslash-nya di-escape lagi oleh literal string ClickHouse.
        $this->assertStringContainsString('ILIKE \'%50\\\\% o\\\'k%\'', $w);

        $semua = People::saringan($opsi, '', '', '');
        $this->assertStringContainsString("IN ('PT Alfa','PT O\'Neil')", $semua);
        $this->assertStringContainsString("NOT IN ('VISITOR')", $semua);
    }

    public function test_time_off_bulan_ini_hanya_karyawan_terpilih(): void
    {
        $tidakMasuk = [
            ['A1', '2026-11-03', 'CT'],
            ['A1', '2026-11-04', 'CT'],
            ['A2', '2026-11-04', 'S'],
            ['A3', '2026-10-31', 'I'],   // bulan lain
            ['Z9', '2026-11-05', 'I'],   // bukan karyawan terpilih
        ];
        $alfa = array_values(array_filter($this->data(), static fn (array $k): bool => $k['perusahaan'] === 'PT Alfa'));
        $hasil = People::timeOff($tidakMasuk, $alfa, CarbonImmutable::parse('2026-11-01'));

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
