<?php

namespace Tests\Unit\PetaRisiko;

use App\Services\PetaRisiko\PetaRisikoSnapshotBuilder;
use App\Services\PetaRisiko\PetaRisikoStatus;
use App\Services\PetaRisiko\XlsxStreamReader;
use PHPUnit\Framework\TestCase;

class PetaRisikoStatusTest extends TestCase
{
    public function test_temuan_open_dan_berulang_jadi_gap(): void
    {
        $this->assertSame(['gap', 'open'], PetaRisikoStatus::dari('Terbuka', 'ya', true));
        $this->assertSame(['gap', 'berulang'], PetaRisikoStatus::dari('Berulang', 'tidak (baseline proses)', false));
    }

    public function test_terverifikasi_jadi_ok(): void
    {
        $this->assertSame(['ok', ''], PetaRisikoStatus::dari('Terverifikasi', 'ya', false));
    }

    public function test_belum_dilaporkan_dengan_kontrol_hira_hanya_fyi(): void
    {
        $this->assertSame(['fyi', ''], PetaRisikoStatus::dari('Tidak terpantau', 'ya', true));
    }

    public function test_belum_dilaporkan_tanpa_kontrol_hira_harus_dikendalikan(): void
    {
        $this->assertSame(['tindak', 'tanpa-kontrol'], PetaRisikoStatus::dari('Tidak terpantau', 'ya', false));
        $this->assertSame(['tindak', 'tanpa-padanan'], PetaRisikoStatus::dari('Tidak terpantau', 'tidak (baseline proses)', true));
    }

    public function test_status_terburuk_mengutamakan_gap(): void
    {
        $this->assertSame('gap', PetaRisikoStatus::terburuk('ok', 'fyi', 'gap', 'tindak'));
        $this->assertSame('tindak', PetaRisikoStatus::terburuk('ok', 'tindak', 'fyi'));
        $this->assertSame('ok', PetaRisikoStatus::terburuk('ok'));
    }

    public function test_level_risiko(): void
    {
        $this->assertSame([1, 2, 3, 4, 0], array_map([PetaRisikoStatus::class, 'level'], ['Low', 'medium', ' High ', 'Significant', '']));
    }

    public function test_ringkas_menghitung_tema_unik_per_proses_dengan_status_terburuk(): void
    {
        $baris = fn (string $proses, string $tema, string $status) => array_replace(
            array_fill(0, count(PetaRisikoSnapshotBuilder::KOLOM), ''),
            [0 => $proses, 4 => $tema, 8 => $status]
        );
        $rows = [
            $baris('OB', 'JLN', 'ok'),
            $baris('OB', 'JLN', 'gap'),   // lokasi lain, tema sama → gap
            $baris('OB', 'APD', 'fyi'),
            $baris('CG', 'JLN', 'tindak'), // proses lain = unit lain
        ];
        $open = [array_fill(0, 12, '') + [12 => 1], array_fill(0, 12, '') + [12 => 0]];

        $r = PetaRisikoSnapshotBuilder::ringkas($rows, $open);

        $this->assertSame(['gap' => 1, 'tindak' => 1, 'fyi' => 1, 'ok' => 0, 'unit' => 3, 'proses' => 2, 'open' => 2, 'lewat' => 1], $r);
    }

    public function test_label_periode_dari_sheet_info(): void
    {
        $this->assertSame(
            ['wk' => 'Minggu 1-7 Nov 2026', 'ytd' => 'YTD Jan-29 Sep 2026', 'status' => 'status per 8 Nov 2026'],
            PetaRisikoSnapshotBuilder::labelPeriode('Minggu 1-7 Nov 2026 (status per 8 Nov 2026). Sheet berakhiran YTD = Jan-29 Sep 2026')
        );
    }

    public function test_indeks_kolom_excel(): void
    {
        $this->assertSame([0, 25, 26, 27, 701], array_map([XlsxStreamReader::class, 'columnIndex'], ['A1', 'Z9', 'AA2', 'AB10', 'ZZ3']));
    }
}
