<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class IscMoiController extends Controller
{
    public function index(int|string|null $page = 1): View
    {
        $page = max(1, min(6, (int) ($page ?: 1)));

        return view('isc.moi', [
            'page' => $page,
            'pages' => $this->pages(),
            'logoUrl' => asset('build/images/logo-removebg.png'),
            'heroImage' => asset('isc-assets/isc-home-hero.png'),
            'controlRoomImage' => asset('isc-assets/isc-home-control-room.png'),
            'safetyImage' => asset('isc-assets/isc-slide2-safety.png'),
            'highRiskImage' => asset('isc-assets/isc-slide2-highrisk.png'),
            'ricImage' => asset('isc-assets/isc-slide2-ric.png'),
            'techImage' => asset('isc-assets/isc-slide2-tech.png'),
            'footerImage' => asset('isc-assets/isc-slide2-footer.png'),
            'pillars' => $this->pillars(),
            'capabilities' => $this->capabilities(),
            'backgrounds' => $this->backgrounds(),
            'objectives' => $this->objectives(),
            'timeline' => $this->timeline(),
            'tools' => $this->tools(),
            'locations' => $this->locations(),
            'exceptions' => $this->exceptions(),
            'definitions' => $this->definitions(),
            'layers' => $this->layers(),
            'liveSteps' => $this->liveSteps(),
            'postSteps' => $this->postSteps(),
            'liveTools' => $this->liveTools(),
            'postTools' => $this->postTools(),
        ]);
    }

    /**
     * @return list<array{n: string, slug: string, label: string, kicker: string}>
     */
    private function pages(): array
    {
        return [
            ['n' => '01', 'slug' => 'overview', 'label' => 'Overview', 'kicker' => 'Assessment RIC'],
            ['n' => '02', 'slug' => 'latar', 'label' => 'Latar', 'kicker' => 'Mengapa proyek ini'],
            ['n' => '03', 'slug' => 'tujuan', 'label' => 'Tujuan', 'kicker' => 'Sasaran MOI 2026'],
            ['n' => '04', 'slug' => 'lingkup', 'label' => 'Lingkup', 'kicker' => 'Ruang lingkup & definisi'],
            ['n' => '05', 'slug' => 'live', 'label' => 'Live', 'kicker' => 'Proses pengawasan live'],
            ['n' => '06', 'slug' => 'post', 'label' => 'Post Event', 'kicker' => 'Review & pembelajaran'],
        ];
    }

    /**
     * @return list<array{title: string, icon: string}>
     */
    private function pillars(): array
    {
        return [
            ['title' => 'Cegah Paparan Bahaya', 'icon' => 'shield'],
            ['title' => 'Perluas Jangkauan Pengawasan', 'icon' => 'target'],
            ['title' => 'Tangkap KTA/TTA Optimal', 'icon' => 'chart'],
            ['title' => 'Respons & Intervensi Cepat', 'icon' => 'clock'],
            ['title' => 'Operasi Selamat & Berkelanjutan', 'icon' => 'people'],
        ];
    }

    /**
     * @return list<array{title: string, icon: string}>
     */
    private function capabilities(): array
    {
        return [
            ['title' => 'Teknologi Terintegrasi', 'icon' => 'binoculars'],
            ['title' => 'Data Real-time & Analytics', 'icon' => 'gear'],
            ['title' => 'Intervensi Efektif', 'icon' => 'team'],
            ['title' => 'Kontrol Risiko', 'icon' => 'check'],
            ['title' => 'Kinerja Berkelanjutan', 'icon' => 'bars'],
        ];
    }

    /**
     * @return list<array{title: string, body: string, icon: string, image: string}>
     */
    private function backgrounds(): array
    {
        return [
            [
                'title' => 'Siaga 1 Keselamatan',
                'body' => 'Penguatan keselamatan menuntut pengendalian risiko yang lebih cepat, konsisten, dan terdokumentasi.',
                'icon' => 'shield',
                'image' => 'safety',
            ],
            [
                'title' => 'Operasi Tambang High Risk',
                'body' => 'Hauling, loading, pit, ROM, workshop, dan area pendukung butuh pengawasan berkelanjutan terhadap KTA dan TTA.',
                'icon' => 'truck',
                'image' => 'highrisk',
            ],
            [
                'title' => 'Peran Risk Intervention Center',
                'body' => 'RIC adalah pusat deteksi dini, verifikasi, intervensi, eskalasi, dan pemantauan tindak lanjut.',
                'icon' => 'room',
                'image' => 'ric',
            ],
            [
                'title' => 'Pemanfaatan Teknologi',
                'body' => 'Pengawasan didukung CCTV, radio, monitoring visual, alarm, dan data operasional untuk pengawasan berjarak.',
                'icon' => 'tech',
                'image' => 'tech',
            ],
        ];
    }

    /**
     * @return list<array{n: string, text: string, icon: string}>
     */
    private function objectives(): array
    {
        return [
            ['n' => '01', 'icon' => 'map', 'text' => 'Memetakan proses existing pengawasan langsung berjarak dan post event.'],
            ['n' => '02', 'icon' => 'people', 'text' => 'Menilai efektivitas people, process, technology, dan governance di control room.'],
            ['n' => '03', 'icon' => 'gap', 'text' => 'Mengidentifikasi gap pada deteksi, respon, eskalasi, dokumentasi, dan tindak lanjut.'],
            ['n' => '04', 'icon' => 'chart', 'text' => 'Menyusun rekomendasi perbaikan yang aplikatif untuk Risk Intervention Center.'],
            ['n' => '05', 'icon' => 'safety', 'text' => 'Mendukung peningkatan keselamatan, kecepatan intervensi, dan disiplin operasional.'],
        ];
    }

    /**
     * @return list<array{date: string, title: string, icon: string}>
     */
    private function timeline(): array
    {
        return [
            [
                'date' => '3–17 September 2026',
                'title' => 'Current State Mapping & Gap Analysis',
                'icon' => 'tablet',
            ],
            [
                'date' => 'Jumat, 18 September 2026',
                'title' => 'Focus Group Discussion Hasil Assessment dan Ide Perkuatan',
                'icon' => 'star',
            ],
        ];
    }

    /**
     * @return list<array{n: string, name: string, live: bool, post: bool}>
     */
    private function tools(): array
    {
        return [
            ['n' => '1', 'name' => 'CCTV (Mining Eyes)', 'live' => true, 'post' => true],
            ['n' => '2', 'name' => 'CCTV (Plant & Support)', 'live' => true, 'post' => true],
            ['n' => '3', 'name' => 'Driving Monitoring System (DMS)', 'live' => true, 'post' => true],
            ['n' => '4', 'name' => 'Teropong', 'live' => true, 'post' => false],
            ['n' => '5', 'name' => 'Kamera DSLR', 'live' => true, 'post' => false],
            ['n' => '6', 'name' => 'Drone', 'live' => true, 'post' => false],
            ['n' => '7', 'name' => 'CCTV (Kantor, Mess & Kantin)', 'live' => false, 'post' => true],
            ['n' => '8', 'name' => 'In Cabin Camera (ICC)', 'live' => false, 'post' => true],
            ['n' => '9', 'name' => 'Mining Eyes Analytics (MEA)', 'live' => true, 'post' => true],
        ];
    }

    /**
     * @return list<array{place: string, tools: string}>
     */
    private function locations(): array
    {
        return [
            ['place' => 'Risk Intervention Center / monitor laptop', 'tools' => 'CCTV Mining Eyes, CCTV Plant & Support, DMS'],
            ['place' => 'Pos pengawasan', 'tools' => 'Drone, teropong, kamera DSLR'],
            ['place' => 'Site dengan Mining Eyes Analytics', 'tools' => 'Deteksi otomatis berbasis AI (MEA)'],
        ];
    }

    /**
     * @return list<string>
     */
    private function exceptions(): array
    {
        return [
            'Aktivitas safety & quality yang memerlukan pengawasan langsung di lapangan',
            'Force majeure: listrik, sinyal, jaringan, atau genset',
            'Blind spot atau keterbatasan teknologi',
            'Faktor lingkungan: kabut, debu, pencahayaan buruk',
        ];
    }

    /**
     * @return array<string, list<array{term: string, body: string}>>
     */
    private function definitions(): array
    {
        return [
            'Aktivitas & Area' => [
                ['term' => 'Aktivitas Kritis', 'body' => 'Aktivitas berisiko tinggi yang berpotensi menyebabkan kecelakaan major/fatal.'],
                ['term' => 'Area Kritis', 'body' => 'Area dengan riwayat atau potensi kecelakaan major/fatal.'],
                ['term' => 'OAK', 'body' => 'Observasi aktivitas atau area kritis.'],
                ['term' => 'Post Event', 'body' => 'Observasi dari rekaman CCTV setelah kejadian berlangsung.'],
            ],
            'Sistem & Teknologi' => [
                ['term' => 'Risk Intervention Center', 'body' => 'Ruang monitor untuk CCTV dan DMS.'],
                ['term' => 'DMS', 'body' => 'Sistem memantau pengemudi dan memberi peringatan.'],
                ['term' => 'Mining Eyes', 'body' => 'Pengawasan langsung berbasis CCTV.'],
                ['term' => 'MEA', 'body' => 'AI untuk deteksi dan alert penyimpangan.'],
            ],
            'Intervensi' => [
                ['term' => 'Intervensi Langsung', 'body' => 'Teguran atau instruksi melalui radio, telepon, atau speaker.'],
                ['term' => 'Kepengawasan Berjenjang', 'body' => 'Pengawasan oleh Layer 1 sampai Layer 4.'],
                ['term' => 'Pengawasan Langsung Berjarak', 'body' => 'Pengawasan dari pos, kabin, atau RIC dengan CCTV, DMS, drone, teropong, dan DSLR.'],
            ],
            'Peran Pengawas' => [
                ['term' => 'PJA', 'body' => 'Memastikan operasi aman dan efektif di area tanggung jawabnya.'],
                ['term' => 'Pengawas RIC', 'body' => 'Memantau melalui CCTV dan DMS dari Risk Intervention Center.'],
                ['term' => 'Pengawas Lapangan', 'body' => 'Pengawasan dengan teropong, DSLR, atau drone dari pos pengawasan.'],
            ],
        ];
    }

    /**
     * @return array{bc: list<string>, mitra: list<string>}
     */
    private function layers(): array
    {
        return [
            'bc' => [
                'Pengawas Lapangan',
                'Pengawas RIC / Inspektor / Supervisor',
                'PJA atau setara',
                'Superintendent / Superior / Manager',
            ],
            'mitra' => [
                'Pengawas Lapangan & Pengawas RIC',
                'Supervisor / PJA atau setara',
                'Superintendent atau setara',
                'PJO / Manager atau setara',
            ],
        ];
    }

    /**
     * @return list<array{n: string, title: string, owner: string, items: list<string>}>
     */
    private function liveSteps(): array
    {
        return [
            [
                'n' => '01',
                'title' => 'Persiapan Pengawasan',
                'owner' => 'Pengawas Layer 1',
                'items' => [
                    'Susun DOP: nama pengawas, CCTV, dan lokasi',
                    'Handover temuan terbuka antar shift',
                    'P5M di awal shift untuk area dan aktivitas kritis',
                    'P2H alat bantu dan setup RIC',
                    'Laporkan gangguan IT dan siapkan alternatif',
                    'Inspeksi awal shift',
                ],
            ],
            [
                'n' => '02',
                'title' => 'Pelaksanaan Pengawasan Berjarak',
                'owner' => 'Pengawas Layer 1–4',
                'items' => [
                    'Pengawasan kontinu oleh RIC dan pengawas lapangan',
                    'Acuan: DOP, temuan terbuka, dan aktivitas kritis',
                    'Gunakan checklist sesuai alat bantu',
                    'Fokus pada KTA dan TTA',
                ],
            ],
            [
                'n' => '03',
                'title' => 'Intervensi KTA/TTA',
                'owner' => 'Pengawas Layer 1–4',
                'items' => [
                    'Hentikan pekerjaan pada situasi berisiko tinggi',
                    'Lakukan perbaikan segera',
                    'Teguran, coaching, pelatihan, atau sanksi',
                    'Validasi alert DMS/MEA sebelum intervensi',
                ],
            ],
            [
                'n' => '04',
                'title' => 'Pelaporan di BEATS',
                'owner' => 'Pengawas Layer 1–4',
                'items' => [
                    'Laporkan paling lambat akhir shift',
                    'Modul: BeHazard, BeInspeksi, BeObservasi, Critical Area BeObservasi',
                    'Sertakan bukti: kamera, lokasi, tanggal, dan waktu',
                    'Jika tidak ada KTA/TTA, laporkan observasi umum',
                ],
            ],
            [
                'n' => '05',
                'title' => 'Pemantauan Tindak Perbaikan',
                'owner' => 'Pengawas Lapangan Layer 1 & Layer 2',
                'items' => [
                    'Pengawas lapangan menindaklanjuti temuan terbuka',
                    'Jika belum selesai, informasikan Layer 2 dan shift berikutnya',
                    'Layer 2 memasukkan temuan terbuka ke DOP',
                    'Perbarui status closing di BEATS',
                ],
            ],
            [
                'n' => '06',
                'title' => 'Evaluasi Data Hasil Pengawasan',
                'owner' => 'Safety Evaluator PT Berau Coal / Mitra',
                'items' => [
                    'Analisis tren KTA/TTA dan insiden terkait',
                    'Identifikasi akar masalah',
                    'Pantau status Open vs Closed',
                    'Rekomendasi perbaikan, evaluasi mingguan dan bulanan',
                ],
            ],
        ];
    }

    /**
     * @return list<array{n: string, title: string, owner: string, items: list<string>}>
     */
    private function postSteps(): array
    {
        return [
            [
                'n' => '01',
                'title' => 'Persiapan Review',
                'owner' => 'Pengawas Layer 1',
                'items' => [
                    'Tentukan area, aktivitas, dan periode rekaman',
                    'Buka temuan terbuka DOP dan checklist',
                    'Pastikan akses CCTV, DMS, ICC, dan MEA siap',
                    'Siapkan tim klarifikasi: RIC, pengawas lapangan',
                    'Prioritaskan area dan aktivitas kritis',
                ],
            ],
            [
                'n' => '02',
                'title' => 'Pelaksanaan Review Rekaman',
                'owner' => 'Pengawas Layer 1–4',
                'items' => [
                    'Review rekaman CCTV / DMS / ICC',
                    'Fokus pada KTA dan TTA',
                    'Telusuri urutan kejadian',
                    'Tandai bukti: screenshot, klip, timestamp',
                    'Catat fakta secara objektif',
                ],
            ],
            [
                'n' => '03',
                'title' => 'Validasi Temuan',
                'owner' => 'Pengawas Layer 1–4',
                'items' => [
                    'Verifikasi konteks dengan pengawas lapangan',
                    'Validasi alasan DMS/MEA terlebih dahulu',
                    'Klasifikasikan sebagai KTA/TTA atau observasi',
                    'Tentukan tingkat risiko dan tindak lanjut',
                ],
            ],
            [
                'n' => '04',
                'title' => 'Pelaporan di BEATS',
                'owner' => 'Pengawas Layer 1–4',
                'items' => [
                    'Laporkan paling lambat akhir shift',
                    'Gunakan modul BeHazard, BeInspeksi, BeObservasi',
                    'Lampirkan bukti: lokasi, kamera, tanggal, waktu',
                    'Laporkan observasi meski tidak ada KTA/TTA',
                ],
            ],
            [
                'n' => '05',
                'title' => 'Tindak Lanjut Perbaikan',
                'owner' => 'Pengawas Lapangan Layer 1 & Layer 2',
                'items' => [
                    'Komunikasikan temuan ke pemilik area',
                    'Masukkan temuan ke DOP shift berikutnya',
                    'Lakukan coaching atau perbaikan yang diperlukan',
                    'Perbarui status closed di BEATS',
                ],
            ],
            [
                'n' => '06',
                'title' => 'Evaluasi Hasil Pengawasan',
                'owner' => 'Safety Evaluator PT Berau Coal / Mitra',
                'items' => [
                    'Analisis tren KTA/TTA',
                    'Pantau status open vs closed',
                    'Identifikasi akar masalah',
                    'Susun rekomendasi perbaikan, evaluasi mingguan dan bulanan',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function liveTools(): array
    {
        return ['CCTV Mining Eyes', 'CCTV Plant & Support', 'DMS', 'Drone', 'MEA'];
    }

    /**
     * @return list<string>
     */
    private function postTools(): array
    {
        return ['CCTV Mining Eyes', 'CCTV Plant & Support', 'DMS', 'ICC', 'MEA'];
    }
}
