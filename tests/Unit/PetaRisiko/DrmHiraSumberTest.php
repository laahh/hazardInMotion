<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\Drm\DrmHiraSumber;
use PHPUnit\Framework\TestCase;

/** Asal HIRA per baris baseline: dokumen yang memuat teks bahaya didahulukan (tanpa ClickHouse / DB). */
class DrmHiraSumberTest extends TestCase
{
    public function test_dokumen_yang_memuat_bahaya_didahulukan(): void
    {
        $dok = [
            8332 => ['rev' => 1, 'dept' => 'BMO 2 Dept.', 'aktif' => true, 'bahaya' => ['Menggunakan telepon/melakukan aktifitas lain']],
            8333 => ['rev' => 1, 'dept' => 'BMO 2 Dept.', 'aktif' => true, 'bahaya' => ["Keperibadian yang Kurang dari Pekerja\n(terburu-buru, bercanda)"]],
        ];
        $baris = [
            ['ri' => 1, 'bahaya' => 'Keperibadian yang Kurang dari Pekerja (terburu-buru, bercanda)', 'idh' => [8332, 8333]],
            ['ri' => 2, 'bahaya' => 'Alat Angkat', 'idh' => [9999]],
            ['ri' => 3, 'bahaya' => 'x', 'idh' => []],
        ];

        $h = DrmHiraSumber::cocokkan($baris, $dok);

        // Teks dengan baris baru / spasi berbeda tetap cocok.
        $this->assertSame([8333, 8332], array_column($h[1], 'idh'));
        $this->assertSame([true, false], array_column($h[1], 'cocok'));
        $this->assertArrayNotHasKey('bahaya', $h[1][0]);
        $this->assertSame([['idh' => 9999, 'ada' => false, 'cocok' => false]], $h[2]);
        $this->assertSame([], $h[3]);
    }

    public function test_pisah_idh(): void
    {
        $this->assertSame([8333, 7523], DrmHiraSumber::pisahIdh('8333, 7523;8333'));
        $this->assertSame([], DrmHiraSumber::pisahIdh(''));
    }
}
