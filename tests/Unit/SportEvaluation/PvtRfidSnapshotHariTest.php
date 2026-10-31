<?php

declare(strict_types=1);

namespace Tests\Unit\SportEvaluation;

use App\Services\SportEvaluation\SportEvaluationPvtRfidCheckinReader as Rfid;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Check-in RFID per SID dibaca dari snapshot harian ter-cache (semua SID,
 * satu query per tanggal), bukan query view per daftar SID (±100 dtk).
 */
class PvtRfidSnapshotHariTest extends TestCase
{
    private const KUNCI = 'evaluasi_well:rfid_hari:v1:';

    /** @var list<string> */
    private array $kunci = [];

    protected function tearDown(): void
    {
        foreach ($this->kunci as $k) {
            Cache::forget($k);
        }
        parent::tearDown();
    }

    public function test_check_in_dan_check_out_disaring_dari_snapshot_tanpa_membedakan_huruf(): void
    {
        $this->isi('in', '2026-10-30', ['AB12C' => $this->baris('ab12C', '2026-10-30 05:40:00'), 'ZZ999' => $this->baris('ZZ999', '2026-10-30 06:00:00')]);
        $this->isi('out', '2026-10-30', ['AB12C' => $this->baris('ab12C', '2026-10-30 18:10:00')]);
        $rfid = app(Rfid::class);

        $masuk = $rfid->firstPassedCheckinsForSids('2026-10-30', [' ab12c ', 'TIDAK-ADA']);
        $this->assertSame(['AB12C'], array_keys($masuk));
        $this->assertSame('2026-10-30 05:40:00', $masuk['AB12C']['checked_in_at']);
        $this->assertSame('2026-10-30 18:10:00', $rfid->lastPassedCheckoutsForSids('2026-10-30', ['AB12C'])['AB12C']['checked_in_at']);
        $this->assertSame([], $rfid->firstPassedCheckinsForSids('2026-10-30', []));
    }

    public function test_rentang_hari_dari_snapshot_bila_semua_tanggal_ter_cache(): void
    {
        $this->isi('in', '2026-10-28', ['AB12C' => $this->baris('AB12C', '2026-10-28 05:00:00')]);
        $this->isi('in', '2026-10-29', []);
        $this->isi('in', '2026-10-30', ['AB12C' => $this->baris('AB12C', '2026-10-30 05:40:00'), 'CD34E' => $this->baris('CD34E', '2026-10-30 06:00:00')]);

        $hasil = app(Rfid::class)->firstPassedCheckinsByDayForSids('2026-10-28', '2026-10-30', ['ab12c', 'CD34E']);

        $this->assertSame(['2026-10-28', '2026-10-30'], array_keys($hasil));
        $this->assertSame(['AB12C', 'CD34E'], array_keys($hasil['2026-10-30']));
    }

    /**
     * @param  array<string, array<string, string>>  $data
     */
    private function isi(string $jenis, string $tanggal, array $data): void
    {
        $k = self::KUNCI.$jenis.':'.$tanggal;
        $this->kunci[] = $k;
        Cache::put($k, $data, 60);
    }

    /**
     * @return array<string, string>
     */
    private function baris(string $sid, string $waktu): array
    {
        return [
            'kode_sid' => $sid, 'checked_in_at' => $waktu, 'nama_karyawan' => 'Uji', 'perusahaan' => 'PT Uji',
            'gate' => 'Gate A', 'jenis_checkinout' => 'CHECK IN', 'status_lolos' => 'PASSED',
        ];
    }
}
