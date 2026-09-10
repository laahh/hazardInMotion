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
                    'tools_observasi' => 'Post Event - Mining Eyes',
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
        $this->assertNull($cards[0]['photo_url']);
        $this->assertSame('9304931', $cards[0]['photo_page_id']);
        $this->assertSame('photocar', $cards[0]['photo_page_kind']);
        $this->assertSame('inspeksi', $cards[1]['type']);
        $this->assertSame('INSPEKSI - Post Event - Mining Eyes', $cards[1]['headline']);
        $this->assertNull($cards[1]['geotag']);
        $this->assertNull($cards[1]['photo_url']);
        $this->assertSame('9340899', $cards[1]['photo_page_id']);
        $this->assertSame('photocar', $cards[1]['photo_page_kind']);
    }

    public function test_hazard_inspeksi_selain_tools_ocr_tidak_tampil(): void
    {
        $cards = $this->reader()->cardsFromRows(
            [
                (object) [
                    'id_laporan' => 1,
                    'tanggal_laporan' => '2026-08-31 09:00:00',
                    'jenis_laporan' => 'HAZARD',
                    'tools_observasi' => 'Pengawasan Langsung',
                    'url_foto' => null,
                ],
                (object) [
                    'id_laporan' => 2,
                    'tanggal_laporan' => '2026-08-31 10:00:00',
                    'jenis_laporan' => 'INSPEKSI',
                    'tools_observasi' => 'Real Time - CCTV Support',
                    'url_foto' => null,
                ],
            ],
            [],
            [],
        );

        $this->assertCount(1, $cards);
        $this->assertSame('inspeksi', $cards[0]['type']);
        $this->assertSame('INSPEKSI - Real Time - CCTV Support', $cards[0]['headline']);
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
                'tools_observasi' => 'Real Time - CCTV Support',
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
                'tools_observasi' => 'Real Time - CCTV Support',
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
        $this->assertSame('—', $cards[0]['pic']);
    }

    public function test_pic_observasi_dari_karyawan_dan_perusahaan_observee(): void
    {
        $cards = $this->reader()->cardsFromRows([], [
            (object) [
                'id_observasi' => 88,
                'tanggal_observasi' => '2026-08-31 11:00:00',
                'jenis_kegiatan' => 'Patroli',
                'tools_observasi' => 'Real Time - CCTV Support',
                'catatan_observasi' => 'Aman',
                'nama_pelapor' => 'AGUNG NUGROHO',
                'jabatan_fungsional_pelapor' => 'Pengawas',
                'perusahaan_pelapor' => 'PT Berau Coal',
                'nama_personil_diobservasi' => 'BUDI SANTOSO',
                'perusahaan_personil_diobservasi' => 'PT Pamapersada Nusantara',
                'jabatan_fungsional_personil_diobservasi' => 'Operator',
                'lokasi' => 'Pit A',
                'detil_lokasi' => 'Front',
                'url_foto' => 'https://hseautomation.beraucoal.co.id/beats2/file/document/8266618',
            ],
        ], []);

        $this->assertSame('BUDI SANTOSO', $cards[0]['pic']);
        $this->assertSame('Operator — PT Pamapersada Nusantara', $cards[0]['pic_meta']);
        $this->assertSame('AGUNG NUGROHO', $cards[0]['reporter']);
        $this->assertNull($cards[0]['photo_url']);
        $this->assertSame('8266618', $cards[0]['photo_page_id']);
        $this->assertSame('document', $cards[0]['photo_page_kind']);
    }

    public function test_pic_oak_mengutamakan_observee_bukan_observer(): void
    {
        $cards = $this->reader()->cardsFromRows([], [], [
            (object) [
                'id_oak' => 22,
                'tanggal_submit' => '2026-08-31 19:10:00',
                'aktivitas' => 'Observasi Alat',
                'sub_aktivitas' => 'Dump truck',
                'kesimpulan' => 'Aman',
                'peran_dalam_tim' => 'OBSERVER',
                'tools_observasi' => 'Post Event - DMS',
                'nama_team' => 'SALSABIELA FIRDAUSI',
                'jabatan_fungsional_team' => 'Pengawas',
                'perusahaan_observee' => null,
                'nama_pelapor' => 'SALSABIELA FIRDAUSI',
                'perusahaan_pelapor' => 'PT Pamapersada Nusantara',
                'lokasi' => 'Pit A',
                'detil_lokasi' => 'Front',
                'url_foto' => null,
            ],
            (object) [
                'id_oak' => 22,
                'tanggal_submit' => '2026-08-31 19:10:00',
                'aktivitas' => 'Observasi Alat',
                'sub_aktivitas' => 'Dump truck',
                'kesimpulan' => 'Aman',
                'peran_dalam_tim' => 'OBSERVEE',
                'tools_observasi' => 'Post Event - DMS',
                'nama_team' => 'ROFIUDIN',
                'jabatan_fungsional_team' => 'Operator',
                'perusahaan_observee' => 'PT Pamapersada Nusantara',
                'nama_pelapor' => 'SALSABIELA FIRDAUSI',
                'perusahaan_pelapor' => 'PT Pamapersada Nusantara',
                'lokasi' => 'Pit A',
                'detil_lokasi' => 'Front',
                'url_foto' => null,
            ],
        ]);

        $this->assertCount(1, $cards);
        $this->assertSame('ROFIUDIN', $cards[0]['pic']);
        $this->assertSame('Operator — PT Pamapersada Nusantara', $cards[0]['pic_meta']);
        $this->assertSame('SALSABIELA FIRDAUSI', $cards[0]['reporter']);
    }

    public function test_observasi_oak_selain_tools_ocr_tidak_tampil(): void
    {
        $cards = $this->reader()->cardsFromRows(
            [],
            [
                (object) [
                    'id_observasi' => 1,
                    'tanggal_observasi' => '2026-08-31 11:00:00',
                    'tools_observasi' => 'Pengawasan Langsung',
                    'url_foto' => null,
                ],
                (object) [
                    'id_observasi' => 2,
                    'tanggal_observasi' => '2026-08-31 12:00:00',
                    'jenis_kegiatan' => 'Patroli',
                    'tools_observasi' => 'Real Time - Mining Eyes',
                    'url_foto' => null,
                ],
            ],
            [
                (object) [
                    'id_oak' => 3,
                    'tanggal_submit' => '2026-08-31 19:00:00',
                    'tools_observasi' => 'Real Time - Teropong',
                    'url_foto' => null,
                ],
                (object) [
                    'id_oak' => 4,
                    'tanggal_submit' => '2026-08-31 19:10:00',
                    'aktivitas' => 'Observasi Alat',
                    'tools_observasi' => 'Post Event - CCTV Portable',
                    'url_foto' => null,
                ],
            ],
        );

        $this->assertCount(2, $cards);
        $this->assertSame('observasi', $cards[0]['type']);
        $this->assertSame('2', $cards[0]['id']);
        $this->assertSame('oak', $cards[1]['type']);
        $this->assertSame('4', $cards[1]['id']);
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
