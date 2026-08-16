<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Models\PraOperasi\PraOperasiEvaluasiHarian;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Fase 3 (Pasca Operasi) — dashboard evaluasi harian. Membaca dari tabel lokal
 * pra_operasi_evaluasi_harian yang diisi oleh command terjadwal
 * `pra-operasi:evaluate-day` (lihat PraOperasiDailyEvaluationService).
 *
 * Dibungkus try/catch di setiap query supaya kalau migration belum dijalankan
 * (tabel belum ada), halaman tetap tampil dengan pesan yang jelas — bukan 500.
 */
final class PraOperasiEvaluationController extends Controller
{
    private const KATEGORI_LABEL = [
        'baik' => 'Baik',
        'perlu_pembinaan' => 'Perlu Pembinaan',
        'kritis' => 'Kritis',
    ];

    public function index(Request $request): View
    {
        $date = (string) $request->query('date', '');
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = Carbon::now(config('app.timezone'))->subDay()->toDateString();
        }
        $kategoriFilter = (string) $request->query('kategori', '');
        if (! array_key_exists($kategoriFilter, self::KATEGORI_LABEL)) {
            $kategoriFilter = '';
        }

        $tableReady = $this->tableExists();

        $kpi = ['baik' => 0, 'perlu_pembinaan' => 0, 'kritis' => 0, 'total' => 0];
        $kpiKemarin = ['baik' => 0, 'perlu_pembinaan' => 0, 'kritis' => 0, 'total' => 0];
        $rows = collect();
        $trend = ['categories' => [], 'baik' => [], 'perlu_pembinaan' => [], 'kritis' => []];

        if ($tableReady) {
            try {
                $query = PraOperasiEvaluasiHarian::query()->whereDate('tanggal', $date);
                $all = $query->get();

                foreach ($all as $row) {
                    $kpi[$row->kategori_evaluasi] = ($kpi[$row->kategori_evaluasi] ?? 0) + 1;
                    $kpi['total']++;
                }

                $filtered = $kategoriFilter !== ''
                    ? $all->where('kategori_evaluasi', $kategoriFilter)
                    : $all;

                $rows = $filtered->sortBy([
                    fn ($r) => match ($r->kategori_evaluasi) { 'kritis' => 0, 'perlu_pembinaan' => 1, default => 2 },
                    fn ($r) => $r->nama ?? '',
                ])->values();

                $trend = $this->buildTrend($date);
                $kpiKemarin = $this->kpiForDate(Carbon::parse($date)->subDay()->toDateString());
            } catch (Throwable $e) {
                report($e);
            }
        }

        return view('pra-operasi.evaluasi-harian', [
            'tableReady' => $tableReady,
            'date' => $date,
            'dateLabel' => Carbon::parse($date, config('app.timezone'))->translatedFormat('d M Y'),
            'kategoriFilter' => $kategoriFilter,
            'kategoriLabels' => self::KATEGORI_LABEL,
            'kpi' => $kpi,
            'kpiDelta' => [
                'kritis' => $kpi['kritis'] - $kpiKemarin['kritis'],
                'perlu_pembinaan' => $kpi['perlu_pembinaan'] - $kpiKemarin['perlu_pembinaan'],
            ],
            'rows' => $rows,
            'trend' => $trend,
        ]);
    }

    /**
     * @return array{baik:int, perlu_pembinaan:int, kritis:int, total:int}
     */
    private function kpiForDate(string $date): array
    {
        $kpi = ['baik' => 0, 'perlu_pembinaan' => 0, 'kritis' => 0, 'total' => 0];
        try {
            PraOperasiEvaluasiHarian::query()
                ->whereDate('tanggal', $date)
                ->get(['kategori_evaluasi'])
                ->each(function ($row) use (&$kpi): void {
                    $kpi[$row->kategori_evaluasi] = ($kpi[$row->kategori_evaluasi] ?? 0) + 1;
                    $kpi['total']++;
                });
        } catch (Throwable $e) {
            report($e);
        }

        return $kpi;
    }

    /**
     * Ekspor CSV (bukan Excel/PDF — belum ada package maatwebsite/excel atau
     * dompdf terpasang di project ini; CSV tidak butuh dependency baru dan
     * tetap terbuka rapi di Excel).
     */
    public function export(Request $request): StreamedResponse
    {
        $date = (string) $request->query('date', '');
        if ($date === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = Carbon::now(config('app.timezone'))->subDay()->toDateString();
        }
        $kategoriFilter = (string) $request->query('kategori', '');
        if (! array_key_exists($kategoriFilter, self::KATEGORI_LABEL)) {
            $kategoriFilter = '';
        }

        $query = PraOperasiEvaluasiHarian::query()->whereDate('tanggal', $date);
        if ($kategoriFilter !== '') {
            $query->where('kategori_evaluasi', $kategoriFilter);
        }
        $rows = $query->orderBy('kategori_evaluasi')->orderBy('nama')->get();

        $filename = 'evaluasi-harian-'.$date.($kategoriFilter !== '' ? '-'.$kategoriFilter : '').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Kode SID', 'Nama', 'Perusahaan', 'Shift', 'Hari Ke', 'Skor Fatigue Test', 'Status PVT', 'True Alert', 'False Alert', 'Alert Belum Diperiksa', 'Durasi Kerja (menit)', 'Z-score Baseline', 'Kategori Evaluasi', 'Alasan']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->kode_sid,
                    $row->nama,
                    $row->perusahaan,
                    $row->shift,
                    $row->hari_ke,
                    $row->fatigue_score,
                    $row->pvt_status,
                    $row->alert_nyata_count,
                    $row->alert_palsu_count,
                    $row->alert_belum_count,
                    $row->durasi_kerja_menit,
                    $row->baseline_zscore,
                    self::KATEGORI_LABEL[$row->kategori_evaluasi] ?? $row->kategori_evaluasi,
                    implode('; ', (array) ($row->alasan ?? [])),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{categories:list<string>, baik:list<int>, perlu_pembinaan:list<int>, kritis:list<int>}
     */
    private function buildTrend(string $untilDate, int $days = 14): array
    {
        $tz = (string) config('app.timezone');
        $end = Carbon::parse($untilDate, $tz)->startOfDay();
        $start = $end->copy()->subDays($days - 1);

        try {
            $rows = PraOperasiEvaluasiHarian::query()
                ->selectRaw('tanggal, kategori_evaluasi, count(*) as jumlah')
                ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
                ->groupBy('tanggal', 'kategori_evaluasi')
                ->get();
        } catch (Throwable $e) {
            report($e);

            return ['categories' => [], 'baik' => [], 'perlu_pembinaan' => [], 'kritis' => []];
        }

        $byDate = [];
        foreach ($rows as $row) {
            $tgl = Carbon::parse($row->tanggal)->toDateString();
            $byDate[$tgl][$row->kategori_evaluasi] = (int) $row->jumlah;
        }

        $out = ['categories' => [], 'baik' => [], 'perlu_pembinaan' => [], 'kritis' => []];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $out['categories'][] = $cursor->translatedFormat('d M');
            $out['baik'][] = $byDate[$key]['baik'] ?? 0;
            $out['perlu_pembinaan'][] = $byDate[$key]['perlu_pembinaan'] ?? 0;
            $out['kritis'][] = $byDate[$key]['kritis'] ?? 0;
            $cursor->addDay();
        }

        return $out;
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('pra_operasi_evaluasi_harian');
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
