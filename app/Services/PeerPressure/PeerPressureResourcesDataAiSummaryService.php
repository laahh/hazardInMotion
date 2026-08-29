<?php

namespace App\Services\PeerPressure;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PeerPressureResourcesDataAiSummaryService
{
    /**
     * @param  array<string, mixed>  $kpi  Output GetPeerPressureDashboardKpiStatsAction
     * @return array{text: string, source: 'openai'|'heuristic'}
     */
    public function generate(array $kpi): array
    {
        $cacheKey = 'peer_pressure.resources_ai_summary.'.md5((string) json_encode([
            'total_cases' => $kpi['total_cases'] ?? 0,
            'completion_rate' => $kpi['completion_rate'] ?? 0,
            'comply' => $kpi['peer_pressure_compliance_comply'] ?? 0,
            'total' => $kpi['peer_pressure_compliance_total'] ?? 0,
            'trend' => $kpi['total_cases_trend_pct'] ?? null,
        ]));

        return Cache::remember($cacheKey, 1800, function () use ($kpi): array {
            $raw = $this->loadAllJsonFiles();
            $digest = $this->buildDigest($raw, $kpi);

            $key = config('services.openai.key');
            if (is_string($key) && $key !== '') {
                $text = $this->tryOpenAiSummary($digest, $kpi);
                if (is_string($text) && trim($text) !== '') {
                    return ['text' => trim($text), 'source' => 'openai'];
                }
            }

            return ['text' => $this->buildHeuristicSummary($digest, $kpi), 'source' => 'heuristic'];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function loadAllJsonFiles(): array
    {
        $dir = resource_path('data');
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir.'/*.json') ?: [] as $path) {
            $name = basename($path);
            $raw = @file_get_contents($path);
            if ($raw === false) {
                continue;
            }
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            $out[$name] = $decoded;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $kpi
     * @return array<string, mixed>
     */
    private function buildDigest(array $raw, array $kpi): array
    {
        $digest = [
            'files_loaded' => array_keys($raw),
            'kpi_snapshot' => [
                'total_cases' => (int) ($kpi['total_cases'] ?? 0),
                'completion_rate' => (float) ($kpi['completion_rate'] ?? 0),
                'peer_pressure_compliance_comply' => (int) ($kpi['peer_pressure_compliance_comply'] ?? 0),
                'peer_pressure_compliance_total' => (int) ($kpi['peer_pressure_compliance_total'] ?? 0),
                'total_cases_trend_pct' => $kpi['total_cases_trend_pct'] ?? null,
            ],
        ];

        if (isset($raw['peer_pressure_hazard_reporting_by_site.json']) && is_array($raw['peer_pressure_hazard_reporting_by_site.json'])) {
            $h = $raw['peer_pressure_hazard_reporting_by_site.json'];
            $digest['hazard_reporting'] = [
                'parameter' => $h['parameter'] ?? null,
                'weeks' => $h['weeks'] ?? null,
                'w15_total_all_sites' => $this->sumBySiteWeek($h['bySite'] ?? null, 'W15'),
            ];
        }

        if (isset($raw['peer_pressure_tbc_high_by_site.json']) && is_array($raw['peer_pressure_tbc_high_by_site.json'])) {
            $t = $raw['peer_pressure_tbc_high_by_site.json'];
            $digest['tbc_high'] = [
                'parameter' => $t['parameter'] ?? null,
                'w15_total_all_sites' => $this->sumBySiteWeek($t['bySite'] ?? null, 'W15'),
            ];
        }

        if (isset($raw['peer_pressure_tbc_blindspot_by_site.json']) && is_array($raw['peer_pressure_tbc_blindspot_by_site.json'])) {
            $b = $raw['peer_pressure_tbc_blindspot_by_site.json'];
            $digest['tbc_blindspot'] = [
                'parameter' => $b['parameter'] ?? null,
                'w15_total_all_sites' => $this->sumBySiteWeek($b['bySite'] ?? null, 'W15'),
            ];
        }

        if (isset($raw['peer_pressure_golden_rules_by_site.json']) && is_array($raw['peer_pressure_golden_rules_by_site.json'])) {
            $g = $raw['peer_pressure_golden_rules_by_site.json'];
            $digest['golden_rules'] = [
                'parameter' => $g['parameter'] ?? null,
                'w15_total_all_sites' => $this->sumBySiteWeek($g['bySite'] ?? null, 'W15'),
            ];
        }

        if (isset($raw['peer_pressure_tbc_category_trend.json']) && is_array($raw['peer_pressure_tbc_category_trend.json'])) {
            $ct = $raw['peer_pressure_tbc_category_trend.json'];
            $weeks = $ct['weeks'] ?? ['W12', 'W13', 'W14', 'W15'];
            $lastW = $weeks[count($weeks) - 1] ?? 'W15';
            $idx = array_search($lastW, $weeks, true);
            $idx = $idx === false ? 3 : (int) $idx;
            $top = [];
            $cats = $ct['all_sites']['categories'] ?? [];
            foreach ($cats as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $vals = $row['values'] ?? [];
                $v = isset($vals[$idx]) && is_numeric($vals[$idx]) ? (float) $vals[$idx] : null;
                if ($v === null) {
                    continue;
                }
                $lab = (string) ($row['label'] ?? '');
                $rk = $row['rank'] ?? '';
                $top[] = ['rank' => $rk, 'label' => $lab, 'last_week' => $v];
            }
            usort($top, fn ($a, $b) => $b['last_week'] <=> $a['last_week']);
            $digest['tbc_category_trend'] = [
                'weeks' => $weeks,
                'top_categories_by_last_week' => array_slice($top, 0, 5),
            ];
        }

        if (isset($raw['peer_pressure_area_kritis_by_site.json'])) {
            $digest['area_kritis'] = $this->summarizeSiteWeekJson($raw['peer_pressure_area_kritis_by_site.json']);
        }
        if (isset($raw['peer_pressure_area_non_kritis_by_site.json'])) {
            $digest['area_non_kritis'] = $this->summarizeSiteWeekJson($raw['peer_pressure_area_non_kritis_by_site.json']);
        }

        if (isset($raw['operational_performance_matrix.json']) && is_array($raw['operational_performance_matrix.json'])) {
            $m = $raw['operational_performance_matrix.json'];
            $digest['op_matrix'] = [
                'row_count' => count($m),
                'sites' => array_values(array_unique(array_filter(array_map(
                    fn ($r) => is_array($r) ? ($r['Site'] ?? null) : null,
                    $m
                )))),
                'incident_sum' => array_sum(array_map(
                    fn ($r) => is_array($r) ? (int) ($r['Incident'] ?? 0) : 0,
                    $m
                )),
                'accident_sum' => array_sum(array_map(
                    fn ($r) => is_array($r) ? (int) ($r['Accident'] ?? 0) : 0,
                    $m
                )),
            ];
        }

        if (isset($raw['operational_performance_matrix_issues.json']) && is_array($raw['operational_performance_matrix_issues.json'])) {
            $iss = $raw['operational_performance_matrix_issues.json'];
            $byStatus = [];
            foreach ($iss as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $st = (string) ($row['Status'] ?? '—');
                $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
            }
            $digest['matrix_issues'] = [
                'count' => count($iss),
                'by_status' => $byStatus,
            ];
        }

        if (isset($raw['peer_pressure_thematic_alignment_program.json']) && is_array($raw['peer_pressure_thematic_alignment_program.json'])) {
            $th = $raw['peer_pressure_thematic_alignment_program.json'];
            $rows = $th['rows'] ?? [];
            $digest['thematic_alignment'] = [
                'title' => $th['title'] ?? null,
                'row_count' => is_array($rows) ? count($rows) : 0,
            ];
        }

        return $digest;
    }

    /**
     * @param  mixed  $bySite
     */
    private function sumBySiteWeek($bySite, string $week): int
    {
        if (! is_array($bySite)) {
            return 0;
        }
        $s = 0;
        foreach ($bySite as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (isset($row[$week]) && is_numeric($row[$week])) {
                $s += (int) $row[$week];
            }
        }

        return $s;
    }

    /**
     * @param  mixed  $json
     * @return array<string, mixed>
     */
    private function summarizeSiteWeekJson($json): array
    {
        if (! is_array($json)) {
            return [];
        }

        return [
            'parameter' => $json['parameter'] ?? null,
            'w15_total_all_sites' => $this->sumBySiteWeek($json['bySite'] ?? null, 'W15'),
        ];
    }

    /**
     * @param  array<string, mixed>  $digest
     * @param  array<string, mixed>  $kpi
     */
    private function tryOpenAiSummary(array $digest, array $kpi): ?string
    {
        $model = (string) config('services.openai.model', 'gpt-4o-mini');
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Anda analis K3 (Keselamatan dan Kesehatan Kerja). Tugas: tulis ringkasan eksekutif singkat dalam Bahasa Indonesia berdasarkan data JSON yang diberikan. Gaya profesional, netral, padat. Maksimal 4 paragraf pendek. Tanpa markdown, tanpa bullet, tanpa nomor. Jangan mengada-ngada angka yang tidak ada di data.',
                ],
                [
                    'role' => 'user',
                    'content' => "Ringkas kinerja Peer Pressure & indikator dari file JSON di resources/data (ikhtisar terstruktur di bawah) serta KPI kejadian edukasi dari database.\n\n".
                        json_encode(['digest' => $digest, 'kpi' => [
                            'total_cases' => (int) ($kpi['total_cases'] ?? 0),
                            'completion_rate' => (float) ($kpi['completion_rate'] ?? 0),
                            'peer_pressure_compliance_comply' => (int) ($kpi['peer_pressure_compliance_comply'] ?? 0),
                            'peer_pressure_compliance_total' => (int) ($kpi['peer_pressure_compliance_total'] ?? 0),
                            'total_cases_trend_pct' => $kpi['total_cases_trend_pct'] ?? null,
                        ]], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                ],
            ],
            'max_tokens' => 650,
            'temperature' => 0.35,
        ];

        try {
            $res = Http::timeout(8)
                ->connectTimeout(3)
                ->withToken((string) config('services.openai.key'))
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if (! $res->successful()) {
                Log::warning('PeerPressureResourcesDataAiSummaryService OpenAI HTTP error', [
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);

                return null;
            }
            $data = $res->json();
            $text = $data['choices'][0]['message']['content'] ?? null;

            return is_string($text) ? $text : null;
        } catch (\Throwable $e) {
            Log::warning('PeerPressureResourcesDataAiSummaryService OpenAI exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $digest
     * @param  array<string, mixed>  $kpi
     */
    private function buildHeuristicSummary(array $digest, array $kpi): string
    {
        $total = (int) ($kpi['total_cases'] ?? 0);
        $comp = (float) ($kpi['completion_rate'] ?? 0);
        $gr = (int) ($kpi['peer_pressure_compliance_comply'] ?? 0);
        $trend = $kpi['total_cases_trend_pct'] ?? null;

        $s = sprintf(
            'Ringkasan otomatis dari seluruh file JSON di resources/data dan KPI kejadian edukasi: total kasus %s, tingkat penyelesaian sekitar %s%%, serta %s entri peer pressure comply (Golden Rules).',
            number_format($total, 0, ',', '.'),
            number_format($comp, 1, ',', '.'),
            number_format($gr, 0, ',', '.')
        );

        if ($trend !== null && is_numeric($trend)) {
            $s .= sprintf(' Tren volume kasus dibanding periode sebelumnya sekitar %s%%.', number_format((float) $trend, 1, ',', '.'));
        }

        if (! empty($digest['hazard_reporting']['w15_total_all_sites'])) {
            $s .= sprintf(
                ' Agregat laporan hazard (W15) dari data JSON mencapai %s.',
                number_format((int) $digest['hazard_reporting']['w15_total_all_sites'], 0, ',', '.')
            );
        }

        if (! empty($digest['tbc_high']['w15_total_all_sites'])) {
            $s .= sprintf(
                ' Jumlah TBC (W15) teragregasi dari seluruh site pada data JSON: %s.',
                number_format((int) $digest['tbc_high']['w15_total_all_sites'], 0, ',', '.')
            );
        }

        if (! empty($digest['tbc_category_trend']['top_categories_by_last_week'])) {
            $bits = [];
            foreach (array_slice($digest['tbc_category_trend']['top_categories_by_last_week'], 0, 3) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $bits[] = trim((string) ($row['label'] ?? ''), " \t\n\r\0\x0B.");
            }
            $bits = array_filter($bits);
            if ($bits !== []) {
                $s .= ' Kategori TBC dengan nilai tertinggi pada minggu terakhir dalam data tren meliputi: '.implode('; ', $bits).'.';
            }
        }

        if (! empty($digest['op_matrix']['row_count'])) {
            $s .= sprintf(
                ' Matriks kinerja operasional memuat %s baris data; insiden terkumpul %s dan kecelakaan %s (penjumlahan baris JSON).',
                number_format((int) $digest['op_matrix']['row_count'], 0, ',', '.'),
                number_format((int) ($digest['op_matrix']['incident_sum'] ?? 0), 0, ',', '.'),
                number_format((int) ($digest['op_matrix']['accident_sum'] ?? 0), 0, ',', '.')
            );
        }

        if (! empty($digest['matrix_issues']['count'])) {
            $s .= sprintf(' Daftar isu matriks berisi %s catatan.', number_format((int) $digest['matrix_issues']['count'], 0, ',', '.'));
        }

        return $s;
    }
}
