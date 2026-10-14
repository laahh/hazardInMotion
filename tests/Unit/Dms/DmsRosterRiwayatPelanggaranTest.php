<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterOverviewKaryawanReader;
use App\Services\Dms\Roster\DmsRosterRuleEngine;
use Tests\TestCase;

/**
 * Riwayat pelanggaran di panel "Pola Harian" tabel scorecard. Ambang mengikuti
 * config rule engine: on-site > 71, kerja beruntun > 13, cuti < 12.
 * Indeks 0 = 1 Januari 2026.
 */
class DmsRosterRiwayatPelanggaranTest extends TestCase
{
    public function test_mencatat_cuti_pendek_dan_kerja_beruntun_urut_tanggal(): void
    {
        // Cuti 6 hari (1–6 Jan), lalu kerja 15 hari tanpa off (7–21 Jan).
        $riwayat = $this->riwayat(str_repeat('o', 6).str_repeat('P', 15).'o');

        $this->assertSame(['Cuti terlalu pendek', 'Bekerja berturut-turut'], array_column($riwayat, 'jenis'));
        $this->assertSame('cuti hanya 6 hari', $riwayat[0]['teks']);
        $this->assertSame(['1 Jan', '6 Jan'], [$riwayat[0]['mulai'], $riwayat[0]['selesai']]);
        $this->assertSame(['7 Jan', '21 Jan'], [$riwayat[1]['mulai'], $riwayat[1]['selesai']]);
    }

    public function test_periode_kerja_panjang_dan_kerja_beruntun_di_dalamnya_dicatat_terpisah(): void
    {
        // Cuti 14 hari (sah), lalu on-site 80 hari: 66 hari pola 6:1 diikuti
        // 14 hari malam tanpa off (bersambung dengan 3 hari pagi sebelumnya).
        $pola = str_repeat('o', 14).substr(str_repeat('PPPPPPo', 10), 0, 66).str_repeat('M', 14);

        $riwayat = $this->riwayat($pola);

        $this->assertSame(['Periode kerja', 'Bekerja berturut-turut'], array_column($riwayat, 'jenis'));
        $this->assertSame('80 hari on-site tanpa cuti', $riwayat[0]['teks']);
    }

    public function test_kategori_longgar_tidak_punya_riwayat(): void
    {
        $this->assertSame([], $this->riwayat(str_repeat('o', 6).str_repeat('P', 30), 'Mekanik'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function riwayat(string $pola, string $kategori = 'Operator Hauler'): array
    {
        $eval = app(DmsRosterRuleEngine::class)->evaluasi($pola, $kategori, 7, 'block');

        return app(DmsRosterOverviewKaryawanReader::class)->riwayatPelanggaran($eval, 2026);
    }
}
