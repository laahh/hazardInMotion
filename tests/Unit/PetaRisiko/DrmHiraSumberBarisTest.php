<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmHiraSumber;
use App\Services\SportEvaluation\RfidClickHouseClient;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Departemen asal HIRA untuk semua baris halaman Master per Baris (dokumen dari cache, tanpa ClickHouse). */
class DrmHiraSumberBarisTest extends TestCase
{
    public function test_semua_baris_mendapat_dokumen_hira_dan_departemennya(): void
    {
        // Kunci cache sama dengan DrmHiraSumber::dokumen() untuk gabungan id_hira_master terurut.
        Cache::put('peta-risiko:hira-sumber:v3:'.md5('8332,8333,9999'), [
            8332 => ['rev' => 2, 'dept' => 'BMO 2 Dept.', 'aktif' => true, 'deptAktif' => true, 'kosong' => false, 'bahaya' => ['Debu']],
            8333 => ['rev' => 1, 'dept' => 'Mining Technology Dept.', 'aktif' => true, 'deptAktif' => true, 'kosong' => true, 'bahaya' => []],
        ], 60);
        // Klien asli (final, tidak bisa di-mock): dokumen sudah di cache, jadi ClickHouse tidak dipanggil.
        $h = (new DrmHiraSumber(new RfidClickHouseClient()))->untukBaris([
            (object) ['ri' => 1, 'idh' => '8333,8332', 'bahaya' => 'Debu'],
            (object) ['ri' => 2, 'idh' => '9999', 'bahaya' => 'Lain'],
            (object) ['ri' => 3, 'idh' => null, 'bahaya' => 'Tanpa ID'],
        ]);

        // Dokumen yang memuat teks bahaya (8332) didahulukan.
        $this->assertSame([8332, 8333], array_column($h[1], 'idh'));
        $this->assertSame(['BMO 2 Dept.', 'Mining Technology Dept.'], array_column($h[1], 'dept'));
        $this->assertSame([true, false], array_column($h[1], 'cocok'));
        $this->assertTrue($h[1][1]['kosong']);
        $this->assertFalse($h[2][0]['ada']);
        $this->assertSame([], $h[3]);
    }
}
