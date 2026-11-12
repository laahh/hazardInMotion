<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterOtomatisService as Otomatis;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Jadwal otomatis PT Berau Coal / PT Berau Coal Energy: Senin–Jumat D1–D5
 * jam 07:30–16:30, Sabtu/Minggu OFF, hanya untuk tanggal yang belum tertutup roster upload.
 */
class DmsRosterOtomatisServiceTest extends TestCase
{
    private const ATURAN = [
        'jam_awal' => '07:30',
        'jam_akhir' => '16:30',
        'kode_hari' => [1 => 'D1', 2 => 'D2', 3 => 'D3', 4 => 'D4', 5 => 'D5'],
    ];

    /** @return array<string, array<string, mixed>> tanggal => baris */
    private function perTanggal(array $baris, string $sid): array
    {
        $out = [];
        foreach ($baris as $b) {
            if ($b['kode_sid'] === $sid) {
                $out[$b['tanggal']] = $b;
            }
        }

        return $out;
    }

    public function test_senin_sampai_jumat_d1_d5_jam_kantor_dan_akhir_pekan_off(): void
    {
        $baris = Otomatis::rencana(
            [['sid' => 'AB12C', 'nama' => 'Uji', 'perusahaan' => 'PT Berau Coal', 'jabatan' => 'Staff', 'site' => 'HO']],
            CarbonImmutable::parse('2026-11-01'),
            [],
            self::ATURAN,
        );
        $hari = $this->perTanggal($baris, 'AB12C');

        $this->assertCount(30, $hari);
        // 1 Nov 2026 = Minggu, 2 Nov = Senin … 7 Nov = Sabtu.
        $this->assertSame('OFF', $hari['2026-11-01']['kode']);
        $this->assertSame(['D1', 'D2', 'D3', 'D4', 'D5'], array_map(
            static fn (string $t): string => $hari[$t]['kode'],
            ['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06']
        ));
        $this->assertSame('OFF', $hari['2026-11-07']['kode']);
        $this->assertSame(['07:30', '16:30', 540, 540], [
            $hari['2026-11-02']['jam_masuk'], $hari['2026-11-02']['jam_keluar'],
            $hari['2026-11-02']['menit_kerja'], $hari['2026-11-02']['menit_total_kerja'],
        ]);
        $this->assertNull($hari['2026-11-07']['jam_masuk']);
        $this->assertNull($hari['2026-11-07']['menit_kerja']);
        $this->assertSame(['PT Berau Coal', 'Staff', 'HO'], [$hari['2026-11-02']['perusahaan'], $hari['2026-11-02']['jabatan'], $hari['2026-11-02']['site']]);
    }

    public function test_bulan_mulai_tengah_minggu_tetap_senin_d1(): void
    {
        // 1 Okt 2026 = Kamis → D4, Jumat D5, Senin 5 Okt D1.
        $hari = $this->perTanggal(Otomatis::rencana([['sid' => 'X1']], CarbonImmutable::parse('2026-10-15'), [], self::ATURAN), 'X1');

        $this->assertCount(31, $hari);
        $this->assertSame('D4', $hari['2026-10-01']['kode']);
        $this->assertSame('D5', $hari['2026-10-02']['kode']);
        $this->assertSame('D1', $hari['2026-10-05']['kode']);
    }

    public function test_tanggal_yang_sudah_punya_roster_dilewati_dan_sid_tanpa_sisa_tidak_dibuat(): void
    {
        $sudah = ['AB12C|2026-11-02' => true, 'AB12C|2026-11-03' => true];
        foreach (range(1, 30) as $d) {
            $sudah[sprintf('PENUH|2026-11-%02d', $d)] = true;
        }

        $baris = Otomatis::rencana(
            [['sid' => 'ab12c '], ['sid' => 'penuh'], ['sid' => 'AB12C'], ['sid' => '']],
            CarbonImmutable::parse('2026-11-01'),
            $sudah,
            self::ATURAN,
        );

        $sids = array_values(array_unique(array_column($baris, 'kode_sid')));
        $this->assertSame(['ab12c'], $sids, 'SID dobel/kosong dibuang, SID yang semua tanggalnya tertutup tidak dibuat');
        $hari = $this->perTanggal($baris, 'ab12c');
        $this->assertCount(28, $hari);
        $this->assertArrayNotHasKey('2026-11-02', $hari);
        $this->assertArrayNotHasKey('2026-11-03', $hari);
        $this->assertSame('D3', $hari['2026-11-04']['kode']);
    }

    public function test_judul_unik_per_perusahaan_dan_bulan(): void
    {
        $this->assertSame('Jadwal otomatis PT Berau Coal November 2026', Otomatis::judul('PT Berau Coal', CarbonImmutable::parse('2026-11-12')));
        $this->assertNotSame(
            Otomatis::judul('PT Berau Coal', CarbonImmutable::parse('2026-11-01')),
            Otomatis::judul('PT Berau Coal Energy', CarbonImmutable::parse('2026-11-01')),
        );
    }
}
