<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomSapDutyReader;
use App\Services\PembatasanLV\PembatasanLVOlapQuery;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class ControlRoomSapDutyReaderTest extends TestCase
{
    public function test_memisahkan_hazard_dan_inspeksi_serta_geotag(): void
    {
        $cards = $this->reader()->cardsFromRows(
            [
                (object) [
                    'id_laporan' => 9304931,
                    'tanggal_laporan' => '2026-08-31 09:23:00',
                    'jenis_laporan' => 'HAZARD',
                    'status_laporan' => 'CLOSED',
                    'deskripsi_temuan' => 'Tidak memakai APD',
                    'ketidaksesuaian' => 'APD',
                    'subketidaksesuaian' => 'Tidak menggunakan APD',
                    'tools_observasi' => 'Real Time - DMS',
                    'lokasi' => 'Lokasi by DMS',
                    'detil_lokasi' => 'Berau, Kalimantan Timur',
                    'latitude' => '2.1',
                    'longitude' => '117.5',
                    'nama_pelapor' => 'RAY VANDIRGA',
                    'jabatan_fungsional_pelapor' => 'Foreman/Group Leader',
                    'perusahaan_pelapor' => 'PT Apex Mitra Prima',
                    'nama_pic' => 'ANDRIANSYAH',
                    'jabatan_fungsional_pic' => 'Foreman/Group Leader',
                    'perusahaan_pic' => 'PT Serasi Autoraya',
                    'url_foto' => 'https://hseautomation.beraucoal.co.id/report/photoCar/9304931',
                ],
                (object) [
                    'id_laporan' => 9340899,
                    'tanggal_laporan' => '2026-08-31 10:00:00',
                    'jenis_laporan' => 'INSPEKSI',
                    'status_laporan' => 'CLOSED',
                    'deskripsi_temuan' => 'Tidak ada pengawas',
                    'subketidaksesuaian' => 'Tidak ada pengawas',
                    'tools_observasi' => 'Pengawasan Langsung',
                    'lokasi' => '(B PMO) Pit Q1',
                    'detil_lokasi' => 'View Point KDC',
                    'latitude' => null,
                    'longitude' => null,
                    'nama_pelapor' => 'AGUNG NUGROHO',
                    'jabatan_fungsional_pelapor' => 'Pengawas',
                    'perusahaan_pelapor' => 'PT Berau Coal',
                    'nama_pic' => null,
                    'jabatan_fungsional_pic' => null,
                    'perusahaan_pic' => null,
                    'url_foto' => 'not-a-url',
                ],
            ],
            [],
            [],
        );

        $this->assertCount(2, $cards);
        $this->assertSame('hazard', $cards[0]['type']);
        $this->assertSame('HAZARD - Real Time - DMS', $cards[0]['headline']);
        $this->assertSame('09:23:00', $cards[0]['geotag']);
        $this->assertSame('Closed', $cards[0]['status']);
        $this->assertSame('https://hseautomation.beraucoal.co.id/report/photoCar/9304931', $cards[0]['photo_url']);
        $this->assertSame('inspeksi', $cards[1]['type']);
        $this->assertNull($cards[1]['geotag']);
        $this->assertNull($cards[1]['photo_url']);
    }

    public function test_oak_diduplikasi_per_id(): void
    {
        $cards = $this->reader()->cardsFromRows([], [], [
            (object) [
                'id_oak' => 11,
                'tanggal_submit' => '2026-08-31 19:10:00',
                'aktivitas' => 'Observasi Alat',
                'sub_aktivitas' => 'Dump truck',
                'kesimpulan' => 'Aman',
                'lokasi' => 'Pit A',
                'detil_lokasi' => 'Front',
                'latitude' => null,
                'longitude' => null,
                'url_foto' => null,
                'nama_pelapor' => 'ALI',
                'jabatan_fungsional_pelapor' => null,
                'perusahaan_pelapor' => null,
            ],
            (object) [
                'id_oak' => 11,
                'tanggal_submit' => '2026-08-31 19:10:00',
                'aktivitas' => 'Observasi Alat',
                'sub_aktivitas' => 'Dump truck',
                'kesimpulan' => 'Aman duplikat tim',
                'lokasi' => 'Pit A',
                'detil_lokasi' => 'Front',
                'latitude' => null,
                'longitude' => null,
                'url_foto' => null,
                'nama_pelapor' => 'ALI',
                'jabatan_fungsional_pelapor' => null,
                'perusahaan_pelapor' => null,
            ],
        ]);

        $this->assertCount(1, $cards);
        $this->assertSame('oak', $cards[0]['type']);
        $this->assertSame('11', $cards[0]['id']);
    }

    public function test_jendela_laporan_hari_h_sampai_akhir_h_plus_satu(): void
    {
        $window = $this->reader()->reportingWindow(CarbonImmutable::parse('2026-08-31'));

        $this->assertSame('2026-08-31 00:00:00', $window['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-02 00:00:00', $window['end']->format('Y-m-d H:i:s'));
    }

    private function reader(): ControlRoomSapDutyReader
    {
        return new ControlRoomSapDutyReader(new PembatasanLVOlapQuery());
    }
}
