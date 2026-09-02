<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class IscOverviewController extends Controller
{
    public function index(): View
    {
        return view('isc.overview', [
            'homeUrl' => route('isc.index'),
            'mapsUrl' => route('isc.maps.index'),
            'evaluationUrl' => route('isc.evaluation'),
            'interventionsUrl' => route('isc.interventions.index'),
            'heroImage' => asset('isc-assets/isc-home-hero.png'),
            'incidents' => $this->incidents(),
            'pathways' => $this->pathways(),
            'phases' => $this->phases(),
        ]);
    }

    /**
     * @return list<array{year: int, severity: string, severityLabel: string, site: string, summary: string, tags: list<string>}>
     */
    private function incidents(): array
    {
        return [
            [
                'year' => 2014,
                'severity' => 'fatal',
                'severityLabel' => 'FATAL',
                'site' => 'BMO 1',
                'summary' => 'Crew survey di bawah tebing tertimbun material bergerak.',
                'tags' => ['GROUND', 'MOVING MATERIAL'],
            ],
            [
                'year' => 2019,
                'severity' => 'fatal',
                'severityLabel' => 'FATAL',
                'site' => 'LMO',
                'summary' => 'Crest material lunak longsor.',
                'tags' => ['GROUND', 'MOVING MATERIAL'],
            ],
            [
                'year' => 2020,
                'severity' => 'first-aid',
                'severityLabel' => 'FIRST AID',
                'site' => 'GMO',
                'summary' => 'Pekerja terjatuh saat menuju pompa waterfill.',
                'tags' => ['SLIP'],
            ],
            [
                'year' => 2021,
                'severity' => 'mixed',
                'severityLabel' => 'FATAL / FIRST AID',
                'site' => 'BMO 1 & LMO',
                'summary' => 'Soft-subsoil collapse; pekerja kejatuhan material.',
                'tags' => ['GROUND', 'MOVING MATERIAL'],
            ],
            [
                'year' => 2022,
                'severity' => 'first-aid',
                'severityLabel' => 'FIRST AID',
                'site' => 'BMO 2',
                'summary' => 'Pekerja terpeleset setelah memasang patok batas survey.',
                'tags' => ['ACCESS'],
            ],
            [
                'year' => 2024,
                'severity' => 'first-aid',
                'severityLabel' => 'FIRST AID',
                'site' => 'BMO 2',
                'summary' => 'Pekerja kejatuhan material dari atas tebing.',
                'tags' => ['MATERIAL FALL'],
            ],
        ];
    }

    /**
     * @return list<array{label: string, tone: string}>
     */
    private function pathways(): array
    {
        return [
            ['label' => 'UNSAFE SUPERVISORY / ACTIVITY POSITION', 'tone' => 'green'],
            ['label' => 'GROUND / UNSAFE WORK ZONE', 'tone' => 'red'],
        ];
    }

    /**
     * @return list<array{code: string, title: string, subtitle: string, tone: string, points: list<string>}>
     */
    private function phases(): array
    {
        return [
            [
                'code' => '01',
                'title' => 'PRA OPERASI',
                'subtitle' => 'CONTROL DESIGN & READINESS',
                'tone' => 'ready',
                'points' => [
                    'HIRA / IBPR / JSA belum secara spesifik mengidentifikasi paparan pekerjaan di luar kabin pada area berisiko.',
                    'SOP / DOP penetapan batas kerja belum memastikan zona aman vs zona terlarang sebelum aktivitas dimulai.',
                    'Supervisor dan pekerja belum memiliki pemahaman yang sama tentang posisi kerja yang aman.',
                ],
            ],
            [
                'code' => '02',
                'title' => 'SAAT OPERASI',
                'subtitle' => 'EXPOSURE & INTERVENTION',
                'tone' => 'expose',
                'points' => [
                    'Pekerja berada di ground yang tidak stabil / zona terlarang saat aktivitas berjalan.',
                    'Safe work positioning dan stop-work intervention gagal dijalankan di lapangan.',
                    'Belum ada interlock teknologi yang memutus paparan secara real-time.',
                ],
            ],
            [
                'code' => '03',
                'title' => 'PASCA OPERASI',
                'subtitle' => 'RESPONSE, DATA & LEARNING',
                'tone' => 'learn',
                'points' => [
                    'Deviasi berulang tidak tertangkap sebagai pola yang harus diintervensi.',
                    'Post-event belum diimplementasikan untuk mengidentifikasi blindspot pengendalian.',
                    'Evaluasi pelanggaran historis belum menjadi umpan balik ke desain kontrol.',
                ],
            ],
        ];
    }
}
