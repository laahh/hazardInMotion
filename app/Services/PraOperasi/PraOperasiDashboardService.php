<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Orkestrator dashboard Pra Operasi: gabungan check-in RFID (Operator) ⋈ status
 * Fatigue Test (Fit to Work) ⋈ status PVT ⋈ alert fatigue DMS. Sumber utama
 * (checkin, fatigue check, DMS alert) dari hse_automation/Postgres; PVT dari
 * BeWell/MySQL (degradasi anggun bila tunnel tidak aktif).
 */
final class PraOperasiDashboardService
{
    private const ROW_LIMIT = 500;

    public function __construct(
        private readonly PraOperasiCheckinReader $checkinReader,
        private readonly PraOperasiFatigueCheckReader $fatigueReader,
        private readonly PraOperasiDmsAlertReader $dmsAlertReader,
        private readonly PraOperasiPvtStatusReader $pvtReader,
        private readonly PraOperasiFatigueTrendReader $trendReader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Request $request): array
    {
        $filters = $this->readFilters($request);
        $tz = (string) config('app.timezone');

        $rfidUp = $this->checkinReader->isUp();
        $pvtUp = $this->pvtReader->isUp();

        if (! $rfidUp) {
            return $this->emptyPayload($filters, $rfidUp, $pvtUp);
        }

        try {
            $checkins = $this->checkinReader->operatorCheckinsForDate($filters['date']);

            if ($filters['company'] !== '') {
                $needle = mb_strtoupper($filters['company']);
                $checkins = array_values(array_filter(
                    $checkins,
                    static fn (array $row): bool => mb_strtoupper($row['perusahaan']) === $needle
                ));
            }

            if ($checkins === []) {
                return $this->emptyPayload($filters, $rfidUp, $pvtUp);
            }

            $sids = array_map(static fn (array $r): string => $r['kode_sid'], $checkins);

            $fatigueBySid = $this->fatigueReader->statusForSidsOnDate($sids, $filters['date']);
            $dmsAlertBySid = $this->dmsAlertReader->fatigueAlertCountsForSids($sids, $filters['date'], $filters['date']);

            $checkinAtBySid = [];
            foreach ($checkins as $row) {
                $checkinAtBySid[mb_strtoupper($row['kode_sid'])] = $row['checked_in_at'];
            }
            $pvtBySid = $this->pvtReader->statusForCheckins($checkinAtBySid, $filters['date']);

            $rows = [];
            foreach ($checkins as $row) {
                $upper = mb_strtoupper($row['kode_sid']);
                $fatigue = $fatigueBySid[$upper] ?? null;
                $pvt = $pvtBySid[$upper] ?? ['status' => 'belum', 'mean_rt_ms' => null, 'median_rt_ms' => null, 'lapses' => null, 'false_starts' => null, 'evaluation_label' => '', 'tested_at' => ''];
                $dmsAlertCount = $dmsAlertBySid[$upper] ?? 0;

                $fatigueTier = $fatigue['tier'] ?? null;
                $fatigueDone = $fatigue !== null;

                if ($filters['fatigue_status'] !== '') {
                    $matchStatus = $fatigueDone ? ($fatigueTier ?? 'hijau') : 'belum';
                    if ($matchStatus !== $filters['fatigue_status']) {
                        continue;
                    }
                }
                if ($filters['pvt_status'] !== '' && $pvt['status'] !== $filters['pvt_status']) {
                    continue;
                }

                $rows[] = [
                    'kode_sid' => $row['kode_sid'],
                    'nama' => $row['nama'] !== '' ? $row['nama'] : '-',
                    'jabatan' => $row['jabatan'],
                    'perusahaan' => $row['perusahaan'] !== '' ? $row['perusahaan'] : 'Tidak diketahui',
                    'gate' => $row['gate'],
                    'checked_in_at' => $row['checked_in_at'],
                    'checked_out_at' => $row['checked_out_at'] ?? null,
                    'fatigue_done' => $fatigueDone,
                    'fatigue_tier' => $fatigueTier,
                    'fatigue_score' => $fatigue['kesiapan_score'] ?? null,
                    'fatigue_sobriety' => $fatigue['hasil_sobriety_test'] ?? '',
                    'fatigue_kondisi' => $fatigue['kondisi_karyawan'] ?? '',
                    'fatigue_tindakan_unfit' => $fatigue['tindakan_unfit'] ?? '',
                    'fatigue_jam_tidur' => $fatigue['jumlah_jam_tidur'] ?? '',
                    'pvt_status' => $pvt['status'],
                    'pvt_mean_rt' => $pvt['mean_rt_ms'],
                    'pvt_lapses' => $pvt['lapses'],
                    'dms_alert_count' => $dmsAlertCount,
                ];
            }

            $rows = $this->sortByPriority($rows);
            $totalRows = count($rows);
            $truncated = $totalRows > self::ROW_LIMIT;
            $tableRows = array_slice($rows, 0, self::ROW_LIMIT);

            return [
                'rfidUp' => true,
                'pvtUp' => $pvtUp,
                'filters' => $filters,
                'dateLabel' => $this->dateLabel($filters['date']),
                'kpi' => $this->buildKpi($rows),
                'rows' => $tableRows,
                'totalRows' => $totalRows,
                'truncated' => $truncated,
                'matrix' => $this->buildFatiguePvtMatrix($rows),
                'aggregatorVsDms' => $this->buildAggregatorVsDmsBreakdown($rows),
                'companyOptions' => $this->buildCompanyOptions($checkins),
                'checklistParams' => $this->checklistParameters(),
                'insights' => $this->buildInsights($filters['date']),
            ];
        } catch (Throwable $e) {
            report($e);

            return $this->emptyPayload($filters, $rfidUp, $pvtUp);
        }
    }

    /**
     * 8 panel wawasan fatigue (trend, breakdown, ranking) — company-wide,
     * window 14 hari berakhir di tanggal filter. Terpisah dari try/catch utama
     * supaya kalau salah satu panel gagal, watchlist harian tetap tampil.
     *
     * @return array<string, mixed>
     */
    private function buildInsights(string $untilDate): array
    {
        $empty = [
            'up' => false,
            'alertTrend' => ['categories' => [], 'true_count' => [], 'false_count' => [], 'null_count' => [], 'operator_count' => []],
            'ftwTrend' => ['categories' => [], 'hijau' => [], 'kuning' => [], 'merah' => []],
            'deviation' => ['sobriety_unfit' => 0, 'kurang_tidur' => 0, 'sakit' => 0, 'ada_tindakan_unfit' => 0, 'total' => 0],
            'criticalIllness' => ['total_penyakit_kritis' => 0, 'ada_alert_fatigue' => 0],
            'topRepeat' => [],
        ];

        if (! $this->trendReader->isUp()) {
            return $empty;
        }

        try {
            return [
                'up' => true,
                'alertTrend' => $this->trendReader->alertTrend($untilDate),
                'ftwTrend' => $this->trendReader->fitToWorkTrend($untilDate),
                'deviation' => $this->trendReader->deviationBreakdown($untilDate),
                'criticalIllness' => $this->trendReader->criticalIllnessVsAlert($untilDate),
                'topRepeat' => $this->trendReader->topRepeatOperators($untilDate, 10),
            ];
        } catch (Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return array{date:string,company:string,fatigue_status:string,pvt_status:string}
     */
    private function readFilters(Request $request): array
    {
        $read = static fn (mixed $v): string => is_string($v) ? mb_substr(trim($v), 0, 180) : '';

        $date = $read($request->query('date', ''));
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = Carbon::now(config('app.timezone'))->toDateString();
        }

        $fatigueStatus = strtolower($read($request->query('fatigue_status', '')));
        if (! in_array($fatigueStatus, ['belum', 'hijau', 'kuning', 'merah'], true)) {
            $fatigueStatus = '';
        }

        $pvtStatus = strtolower($read($request->query('pvt_status', '')));
        if (! in_array($pvtStatus, ['belum', 'lulus', 'tidak_lulus'], true)) {
            $pvtStatus = '';
        }

        return [
            'date' => $date,
            'company' => $read($request->query('company', '')),
            'fatigue_status' => $fatigueStatus,
            'pvt_status' => $pvtStatus,
        ];
    }

    /**
     * Urutan prioritas tampilan: Merah dulu, lalu Belum Fatigue, lalu Kuning,
     * lalu berdasar ada-tidaknya alert DMS, baru Hijau.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortByPriority(array $rows): array
    {
        $rank = ['merah' => 0, null => 1, 'kuning' => 2, 'hijau' => 3];
        usort($rows, static function (array $a, array $b) use ($rank): int {
            $ra = $rank[$a['fatigue_tier']] ?? 1;
            $rb = $rank[$b['fatigue_tier']] ?? 1;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            $cmp = ($b['dms_alert_count'] <=> $a['dms_alert_count']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['nama'], (string) $b['nama']);
        });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     checkin:int, fatigue_hijau:int, fatigue_kuning:int, fatigue_merah:int, fatigue_belum:int,
     *     pvt_lulus:int, pvt_tidak_lulus:int, pvt_belum:int, ada_alert_dms:int
     * }
     */
    private function buildKpi(array $rows): array
    {
        $kpi = [
            'checkin' => count($rows),
            'fatigue_hijau' => 0, 'fatigue_kuning' => 0, 'fatigue_merah' => 0, 'fatigue_belum' => 0,
            'pvt_lulus' => 0, 'pvt_tidak_lulus' => 0, 'pvt_belum' => 0,
            'ada_alert_dms' => 0,
            'masih_di_site' => 0, 'sudah_checkout' => 0,
        ];

        foreach ($rows as $row) {
            if (! $row['fatigue_done']) {
                $kpi['fatigue_belum']++;
            } else {
                $kpi['fatigue_'.($row['fatigue_tier'] ?? 'hijau')]++;
            }

            match ($row['pvt_status']) {
                'lulus' => $kpi['pvt_lulus']++,
                'tidak_lulus' => $kpi['pvt_tidak_lulus']++,
                default => $kpi['pvt_belum']++,
            };

            if ((int) $row['dms_alert_count'] > 0) {
                $kpi['ada_alert_dms']++;
            }

            if (empty($row['checked_out_at'])) {
                $kpi['masih_di_site']++;
            } else {
                $kpi['sudah_checkout']++;
            }
        }

        return $kpi;
    }

    /**
     * Matriks silang status Fatigue Test x status PVT — menggambarkan kondisi
     * operator yang belum lengkap kedua pengecekannya.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{fatigue:string,pvt:string,count:int}>
     */
    private function buildFatiguePvtMatrix(array $rows): array
    {
        $fatigueKeys = ['belum', 'merah', 'kuning', 'hijau'];
        $pvtKeys = ['belum', 'tidak_lulus', 'lulus'];

        $grid = [];
        foreach ($fatigueKeys as $f) {
            foreach ($pvtKeys as $p) {
                $grid[$f.'|'.$p] = 0;
            }
        }

        foreach ($rows as $row) {
            $f = $row['fatigue_done'] ? ($row['fatigue_tier'] ?? 'hijau') : 'belum';
            $p = $row['pvt_status'];
            $key = $f.'|'.$p;
            if (isset($grid[$key])) {
                $grid[$key]++;
            }
        }

        $out = [];
        foreach ($grid as $key => $count) {
            [$f, $p] = explode('|', $key);
            $out[] = ['fatigue' => $f, 'pvt' => $p, 'count' => $count];
        }

        return $out;
    }

    /**
     * Item #2: Pencapaian Pengisian Aggregator Fatigue berdasarkan Pekerja dengan Alert DMS.
     * Cross-tab: operator dengan/tanpa alert DMS fatigue vs sudah/belum isi Fatigue Check.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     ada_alert: array{sudah_isi:int, belum_isi:int, merah:int},
     *     tidak_ada_alert: array{sudah_isi:int, belum_isi:int, merah:int}
     * }
     */
    private function buildAggregatorVsDmsBreakdown(array $rows): array
    {
        $out = [
            'ada_alert' => ['sudah_isi' => 0, 'belum_isi' => 0, 'merah' => 0],
            'tidak_ada_alert' => ['sudah_isi' => 0, 'belum_isi' => 0, 'merah' => 0],
        ];

        foreach ($rows as $row) {
            $bucket = ((int) $row['dms_alert_count'] > 0) ? 'ada_alert' : 'tidak_ada_alert';
            if (! $row['fatigue_done']) {
                $out[$bucket]['belum_isi']++;
            } else {
                $out[$bucket]['sudah_isi']++;
                if ($row['fatigue_tier'] === 'merah') {
                    $out[$bucket]['merah']++;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $checkins
     * @return list<string>
     */
    private function buildCompanyOptions(array $checkins): array
    {
        $companies = [];
        foreach ($checkins as $row) {
            $name = trim((string) $row['perusahaan']);
            if ($name !== '') {
                $companies[$name] = true;
            }
        }
        $list = array_keys($companies);
        sort($list, SORT_STRING);

        return $list;
    }

    /**
     * @return list<array{label:string, group:string}>
     */
    private function checklistParameters(): array
    {
        return [
            ['label' => 'Sobriety Test', 'group' => 'Fatigue Test'],
            ['label' => 'Jam Tidur & Jam Bangun', 'group' => 'Fatigue Test'],
            ['label' => 'Tekanan Darah (Sistole/Diastole)', 'group' => 'Fatigue Test'],
            ['label' => 'Kondisi Kesehatan Terkini', 'group' => 'Fatigue Test'],
            ['label' => 'Riwayat Penyakit Kritis (Epilepsi, Diabetes, Jantung, Hipertensi, Sindrom Metabolik)', 'group' => 'Fatigue Test'],
            ['label' => 'Keluhan Sakit & Konsumsi Obat Sebelum Bekerja', 'group' => 'Fatigue Test'],
            ['label' => 'Kesiapan Bekerja Fisik & Mental (skala 1-10)', 'group' => 'Fatigue Test'],
            ['label' => 'Pengecekan Parental Control', 'group' => 'Fatigue Test'],
            ['label' => 'Kepemilikan SIMPER', 'group' => 'Fatigue Test'],
            ['label' => 'Psychomotor Vigilance Test (18 trial)', 'group' => 'PVT'],
            ['label' => 'Working Memory Test', 'group' => 'PVT'],
        ];
    }

    private function dateLabel(string $date): string
    {
        try {
            return Carbon::parse($date, config('app.timezone'))->translatedFormat('d M Y');
        } catch (Throwable) {
            return $date;
        }
    }

    /**
     * @param  array{date:string,company:string,fatigue_status:string,pvt_status:string}  $filters
     * @return array<string, mixed>
     */
    private function emptyPayload(array $filters, bool $rfidUp, bool $pvtUp): array
    {
        return [
            'rfidUp' => $rfidUp,
            'pvtUp' => $pvtUp,
            'filters' => $filters,
            'dateLabel' => $this->dateLabel($filters['date']),
            'kpi' => [
                'checkin' => 0, 'fatigue_hijau' => 0, 'fatigue_kuning' => 0, 'fatigue_merah' => 0, 'fatigue_belum' => 0,
                'pvt_lulus' => 0, 'pvt_tidak_lulus' => 0, 'pvt_belum' => 0, 'ada_alert_dms' => 0,
                'masih_di_site' => 0, 'sudah_checkout' => 0,
            ],
            'rows' => [],
            'totalRows' => 0,
            'truncated' => false,
            'matrix' => [],
            'aggregatorVsDms' => [
                'ada_alert' => ['sudah_isi' => 0, 'belum_isi' => 0, 'merah' => 0],
                'tidak_ada_alert' => ['sudah_isi' => 0, 'belum_isi' => 0, 'merah' => 0],
            ],
            'companyOptions' => [],
            'checklistParams' => $this->checklistParameters(),
            'insights' => $this->buildInsights($filters['date']),
        ];
    }
}
