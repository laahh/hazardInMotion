<?php

declare(strict_types=1);

namespace App\Http\Controllers\Isc;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class IscEvaluationController extends Controller
{
    public function index(): View
    {
        $heatmap = $this->heatmap();

        return view('isc.evaluation', [
            'homeUrl' => route('isc.index'),
            'overviewUrl' => route('isc.overview'),
            'mapsUrl' => route('isc.maps.index'),
            'watermark' => asset('isc-assets/isc-home-hero.png'),
            'heroImage' => asset('isc-assets/isc-home-hero.png'),
            'pitImage' => asset('isc-assets/home.png'),
            'roomImage' => asset('isc-assets/isc-home-control-room.png'),
            'trend' => $this->trendChart(),
            'siteShare' => $this->siteShare(),
            'totals' => [
                'records' => 1507,
                'groupings' => 38,
            ],
            'heatmap' => $heatmap,
            'hazards' => $this->hazards(),
            'signals' => $this->signals(),
        ]);
    }

    /**
     * @return array{width: int, height: int, polyline: string, points: list<array{x: float, y: float, v: int, label: string}>}
     */
    private function trendChart(): array
    {
        $weeks = ['W28', 'W29', 'W30', 'W31', 'W32', 'W33', 'W34', 'W35'];
        $values = [442, 469, 473, 404, 456, 428, 451, 438];
        $width = 540;
        $height = 168;
        $padL = 8;
        $padR = 18;
        $padT = 22;
        $padB = 26;
        $min = 390;
        $max = 490;
        $count = count($values);
        $spanX = $width - $padL - $padR;
        $spanY = $height - $padT - $padB;
        $points = [];

        foreach ($values as $index => $value) {
            $x = $padL + ($count === 1 ? 0 : $index / ($count - 1) * $spanX);
            $y = $padT + (1 - (($value - $min) / ($max - $min))) * $spanY;
            $points[] = [
                'x' => round($x, 1),
                'y' => round($y, 1),
                'v' => $value,
                'label' => $weeks[$index],
            ];
        }

        $polyline = implode(' ', array_map(
            static fn (array $point): string => $point['x'].','.$point['y'],
            $points
        ));

        return [
            'width' => $width,
            'height' => $height,
            'polyline' => $polyline,
            'points' => $points,
        ];
    }

    /**
     * @return list<array{site: string, pct: float}>
     */
    private function siteShare(): array
    {
        return [
            ['site' => 'BMO 1', 'pct' => 38.6],
            ['site' => 'GMO', 'pct' => 21.8],
            ['site' => 'BMO 2', 'pct' => 21.6],
            ['site' => 'LMO', 'pct' => 10.6],
            ['site' => 'SMO', 'pct' => 6.4],
        ];
    }

    /**
     * @return array{sites: list<string>, max: int, rows: list<array{name: string, total: int, cells: list<array{value: int, color: string, ink: string}>}>}
     */
    private function heatmap(): array
    {
        $sites = ['BMO 1', 'BMO 2', 'BMO 3', 'GMO', 'LMO', 'SMO', 'EKS'];
        $raw = [
            ['Maintenance Unit', [95, 48, 12, 32, 208, 14, 3]],
            ['Survey', [88, 40, 8, 55, 22, 18, 2]],
            ['Peledakan', [70, 35, 5, 28, 18, 12, 1]],
            ['Loading Point', [62, 41, 6, 36, 15, 9, 2]],
            ['Haul Road', [54, 38, 4, 22, 11, 8, 1]],
            ['Refueling', [36, 24, 3, 18, 9, 6, 0]],
            ['Water Management', [28, 18, 2, 14, 8, 5, 1]],
            ['Housekeeping', [22, 16, 2, 11, 7, 4, 0]],
            ['Inspection / P2H', [19, 14, 1, 9, 6, 3, 0]],
            ['Access / Walking', [16, 12, 1, 8, 5, 3, 0]],
        ];

        $max = 0;
        foreach ($raw as $row) {
            $max = max($max, ...$row[1]);
        }

        $rows = [];
        foreach ($raw as $row) {
            $cells = [];
            foreach ($row[1] as $value) {
                $color = $this->heatColor($value, $max);
                $cells[] = [
                    'value' => $value,
                    'color' => $color,
                    'ink' => $value >= (int) round($max * 0.62) ? '#fff' : '#4a4336',
                ];
            }

            $rows[] = [
                'name' => $row[0],
                'total' => array_sum($row[1]),
                'cells' => $cells,
            ];
        }

        return [
            'sites' => $sites,
            'max' => $max,
            'rows' => $rows,
        ];
    }

    /**
     * @return list<array{image: string, crop: string, text: string, tag: string}>
     */
    private function hazards(): array
    {
        return [
            [
                'image' => 'hero',
                'crop' => 'left',
                'text' => 'Crew refueling tidak lapor ke pengawas sebelum isi BBM di area pit.',
                'tag' => 'BAR GMO',
            ],
            [
                'image' => 'pit',
                'crop' => 'center',
                'text' => 'Pekerja survey berdiri di bawah crest tanpa batas aman.',
                'tag' => 'FAD LMO',
            ],
            [
                'image' => 'hero',
                'crop' => 'right',
                'text' => 'Mekanik memperbaiki unit di bahu haul road aktif.',
                'tag' => 'BAR BMO 1',
            ],
            [
                'image' => 'room',
                'crop' => 'center',
                'text' => 'Charging crew masih di zona blasting saat countdown.',
                'tag' => 'FAD SMO',
            ],
        ];
    }

    /**
     * @return list<array{label: string, pct: float, color: string}>
     */
    private function signals(): array
    {
        return [
            ['label' => 'Front / loading / dumping', 'pct' => 48.3, 'color' => '#c0392b'],
            ['label' => 'Blasting / charging', 'pct' => 13.0, 'color' => '#e67e22'],
            ['label' => 'Maintenance / service / P2H', 'pct' => 12.6, 'color' => '#3d7ea6'],
            ['label' => 'Active haul road', 'pct' => 10.9, 'color' => '#7b241c'],
            ['label' => 'Survey / pengukuran', 'pct' => 5.7, 'color' => '#2e7d32'],
            ['label' => 'Sump / water edge', 'pct' => 2.2, 'color' => '#e74c3c'],
        ];
    }

    private function heatColor(int $value, int $max): string
    {
        if ($value <= 0) {
            return '#f7f3ea';
        }

        $t = min(1, $value / max(1, $max));
        $stops = [
            [247, 236, 210],
            [242, 201, 122],
            [232, 148, 64],
            [196, 78, 28],
        ];
        $pos = $t * (count($stops) - 1);
        $index = (int) floor($pos);
        $frac = $pos - $index;

        if ($index >= count($stops) - 1) {
            [$r, $g, $b] = $stops[count($stops) - 1];
        } else {
            $from = $stops[$index];
            $to = $stops[$index + 1];
            $r = (int) round($from[0] + ($to[0] - $from[0]) * $frac);
            $g = (int) round($from[1] + ($to[1] - $from[1]) * $frac);
            $b = (int) round($from[2] + ($to[2] - $from[2]) * $frac);
        }

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
