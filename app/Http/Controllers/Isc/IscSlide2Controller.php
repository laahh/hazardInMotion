<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class IscSlide2Controller extends Controller
{
    public function index(): View
    {
        return view('isc.slide2', [
            'heroImage' => asset('isc-assets/isc-home-hero.png'),
            'controlRoomImage' => asset('isc-assets/isc-home-control-room.png'),
            'homeUrl' => route('isc.index'),
            'backgrounds' => $this->backgrounds(),
            'objectives' => $this->objectives(),
            'timeline' => $this->timeline(),
            'tech' => $this->tech(),
        ]);
    }

    /**
     * @return list<array{title: string, body: string, icon: string}>
     */
    private function backgrounds(): array
    {
        return [
            [
                'title' => 'Siaga 1 Keselamatan',
                'body' => 'PT Berau Coal sedang dalam fokus penguatan keselamatan sehingga pengendalian risiko harus lebih cepat, konsisten, dan terdokumentasi.',
                'icon' => 'shield',
            ],
            [
                'title' => 'Operasi Tambang High Risk',
                'body' => 'Aktivitas hauling, loading, workshop, pit, ROM, dan area pendukung memerlukan pengawasan berkelanjutan terhadap kondisi tidak aman dan perilaku berisiko.',
                'icon' => 'truck',
            ],
            [
                'title' => 'Peran Risk Intervention Center',
                'body' => 'Risk Intervention Center berfungsi sebagai pusat deteksi dini, verifikasi, intervensi, eskalasi, dan pemantauan tindak lanjut.',
                'icon' => 'room',
            ],
            [
                'title' => 'Pemanfaatan Teknologi',
                'body' => 'Pengawasan didukung CCTV, komunikasi radio, monitoring visual, alarm, dan data operasional untuk membantu pengawasan jarak jauh.',
                'icon' => 'tech',
            ],
        ];
    }

    /**
     * @return list<array{n: string, text: string, icon: string}>
     */
    private function objectives(): array
    {
        return [
            [
                'n' => '01',
                'icon' => 'map',
                'text' => 'Memetakan proses existing pengawasan langsung berjarak dan post event.',
            ],
            [
                'n' => '02',
                'icon' => 'people',
                'text' => 'Menilai efektivitas people, process, technology, dan governance di control room.',
            ],
            [
                'n' => '03',
                'icon' => 'gap',
                'text' => 'Mengidentifikasi gap pada deteksi, respon, eskalasi, dokumentasi, dan tindak lanjut.',
            ],
            [
                'n' => '04',
                'icon' => 'chart',
                'text' => 'Menyusun rekomendasi perbaikan yang aplikatif untuk Risk Intervention Center.',
            ],
            [
                'n' => '05',
                'icon' => 'safety',
                'text' => 'Mendukung peningkatan keselamatan, kecepatan intervensi, dan disiplin operasional.',
            ],
        ];
    }

    /**
     * @return list<array{title: string, date: string, icon: string}>
     */
    private function timeline(): array
    {
        return [
            [
                'title' => 'Current State Mapping & Gap Analysis',
                'date' => '3–17 September 2026',
                'icon' => 'tablet',
            ],
            [
                'title' => 'Focus Group Discussion Hasil Assessment dan Ide Perkuatan',
                'date' => 'Rabu, 18 September 2026',
                'icon' => 'star',
            ],
        ];
    }

    /**
     * @return list<array{label: string, icon: string}>
     */
    private function tech(): array
    {
        return [
            ['label' => 'CCTV', 'icon' => 'cctv'],
            ['label' => 'Komunikasi Radio', 'icon' => 'radio'],
            ['label' => 'Alarm', 'icon' => 'alarm'],
            ['label' => 'Monitoring Visual', 'icon' => 'monitor'],
            ['label' => 'Data Operasional', 'icon' => 'data'],
        ];
    }
}
