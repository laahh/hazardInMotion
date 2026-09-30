<?php

declare(strict_types=1);

namespace Tests\Unit\Dms;

use App\Services\Dms\Roster\DmsRosterJabatanKategori;
use Tests\TestCase;

/**
 * Kategori jabatan menentukan pengecualian "regulasi longgar", jadi salah
 * klasifikasi langsung berdampak ke angka pelanggaran. Kasus di bawah diambil
 * dari jabatan yang benar-benar ada di populasi live.
 */
class DmsRosterJabatanKategoriTest extends TestCase
{
    private DmsRosterJabatanKategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kategori = new DmsRosterJabatanKategori();
    }

    /**
     * @dataProvider kasusJabatan
     */
    public function test_memetakan_jabatan_ke_kategori(string $jabatan, string $harapan): void
    {
        $this->assertSame($harapan, $this->kategori->kategoriDari($jabatan));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function kasusJabatan(): array
    {
        return [
            // Cocok persis dari tabel jabcat.json
            'operator tp huruf besar' => ['OPERATOR TP', DmsRosterJabatanKategori::HAULER],
            'excavator besar' => ['OPERATOR EXCAVATOR 200T', DmsRosterJabatanKategori::A2B],
            'driver lv' => ['DRIVER LV', DmsRosterJabatanKategori::TRANSPORTASI],
            'manhaul' => ['DRIVER MANHAUL', DmsRosterJabatanKategori::TRANSPORTASI],

            // Beda kapitalisasi — di referensi jatuh ke Lainnya karena lookup
            // case-sensitive; di sini harus tetap tertangkap.
            'operator tp huruf campur' => ['Operator Tp', DmsRosterJabatanKategori::HAULER],
            'mekanik huruf campur' => ['Mekanik', DmsRosterJabatanKategori::MEKANIK],

            // Fallback kata kunci untuk jabatan di luar tabel
            'mechanical supervisor' => ['MECHANICAL SUPERVISOR', DmsRosterJabatanKategori::MEKANIK],
            'helper mekanik' => ['HELPER MEKANIK', DmsRosterJabatanKategori::MEKANIK],
            'pra mechanic' => ['PRA MECHANIC', DmsRosterJabatanKategori::MEKANIK],
            'technician' => ['TECHNICIAN', DmsRosterJabatanKategori::MEKANIK],
            'welder' => ['WELDER', DmsRosterJabatanKategori::MEKANIK],
            'slurry pump masuk a2b' => ['OPERATOR SLURRY PUMP', DmsRosterJabatanKategori::A2B],
            'dozer tanpa entri tabel' => ['Operator Dozer', DmsRosterJabatanKategori::A2B],
            'dump truck' => ['OP DUMP TRUCK', DmsRosterJabatanKategori::HAULER],
            'hd 465' => ['OPERATOR HD 465', DmsRosterJabatanKategori::HAULER],
            'operator generik' => ['BASIC OPERATOR', DmsRosterJabatanKategori::HAULER],
            'driver generik' => ['DRIVER', DmsRosterJabatanKategori::HAULER],

            // Tidak terklasifikasi → Lainnya (dan Lainnya TIDAK longgar)
            'kosong' => ['', DmsRosterJabatanKategori::LAINNYA],
            'spasi saja' => ['   ', DmsRosterJabatanKategori::LAINNYA],
            'administrasi' => ['STAFF ADMINISTRASI', DmsRosterJabatanKategori::LAINNYA],
        ];
    }

    public function test_jabatan_null_tidak_melempar_error(): void
    {
        $this->assertSame(DmsRosterJabatanKategori::LAINNYA, $this->kategori->kategoriDari(null));
    }

    public function test_hanya_dua_kategori_yang_longgar(): void
    {
        $engine = new \App\Services\Dms\Roster\DmsRosterRuleEngine();

        $this->assertTrue($engine->isLonggar(DmsRosterJabatanKategori::MEKANIK));
        $this->assertTrue($engine->isLonggar(DmsRosterJabatanKategori::TRANSPORTASI));
        $this->assertFalse($engine->isLonggar(DmsRosterJabatanKategori::HAULER));
        $this->assertFalse($engine->isLonggar(DmsRosterJabatanKategori::A2B));
        $this->assertFalse(
            $engine->isLonggar(DmsRosterJabatanKategori::LAINNYA),
            'Jabatan tak terklasifikasi harus tetap kena rule penuh, bukan dikecualikan.',
        );
    }
}
