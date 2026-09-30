<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Services\Dms\Roster\DmsRosterComplianceService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * /dms/roster-compliance — Kepatuhan Roster Karyawan versi LIVE: pola roster
 * dirakit dari scan RFID (bcsid.mv_checkinout_rfid) yang disinkronkan berkala
 * ke tabel lokal oleh `php artisan dms:sync-roster-rfid`.
 *
 * Bedanya dengan /dms/roster-compliance-static: halaman itu membaca snapshot
 * JSON 14 MB hasil ekstraksi sekali jalan; halaman ini mengikuti scan terbaru
 * dan seluruh agregasi dihitung di server, jadi payload halaman tetap kecil.
 */
final class RosterComplianceController extends Controller
{
    public function __construct(
        private readonly DmsRosterComplianceService $service,
    ) {}

    public function index(Request $request): View
    {
        return view('dms.roster-compliance', $this->service->dashboard($this->filters($request)));
    }

    /**
     * JSON rincian harian satu karyawan (gate, jam, durasi, flag per hari).
     */
    public function detail(Request $request, string $sid): JsonResponse
    {
        $sid = mb_strtoupper(mb_substr(trim($sid), 0, 50));
        if ($sid === '') {
            return response()->json(['message' => 'Kode SID tidak valid.'], 422);
        }

        $detail = $this->service->detailKaryawan($sid, $this->tahun($request));

        if (($detail['found'] ?? false) === false) {
            return response()->json(['message' => 'Karyawan tidak ada di populasi roster tahun ini.'], 404);
        }

        return response()->json($detail);
    }

    /**
     * Unduh daftar karyawan yang on-site berjalannya sudah melewati ambang —
     * yang perlu segera dicutikan.
     */
    public function unduhWajibCuti(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $baris = $this->service->daftarWajibCuti($filters);
        $nama = 'wajib-cuti-'.CarbonImmutable::now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () use ($baris): void {
            $out = fopen('php://output', 'wb');
            // BOM supaya Excel membaca UTF-8 dengan benar.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'SID', 'Nama', 'Jabatan', 'Kategori', 'Perusahaan', 'Site',
                'Roster ke-', 'On-site berjalan (hari)', 'On-site maks YTD (hari)',
                'Cuti min YTD (hari)', 'Status',
            ], ';');

            foreach ($baris as $r) {
                fputcsv($out, [
                    $r['sid'], $r['nama'], $r['jabatan'], $r['kategori'], $r['perusahaan'],
                    $r['site'], $r['roster'], $r['onCur'], $r['onAll'], $r['cutiMin'], $r['status'],
                ], ';');
            }

            fclose($out);
        }, $nama, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $periode = (string) $request->query('periode', '');
        $periode = in_array($periode, ['kuartal', 'bulan', 'minggu', 'custom'], true) ? $periode : '';

        $dir = strtolower((string) $request->query('dir', 'asc'));

        return [
            'tahun' => $this->tahun($request),
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'pt' => mb_substr(trim((string) $request->query('pt', '')), 0, 20),
            'site' => mb_substr(trim((string) $request->query('site', '')), 0, 60),
            'kategori' => mb_substr(trim((string) $request->query('kategori', '')), 0, 60),
            'status' => mb_substr(trim((string) $request->query('status', '')), 0, 20),
            'note' => in_array((string) $request->query('note', ''), ['pel', 'wajib', 'map'], true)
                ? (string) $request->query('note')
                : '',
            'roster' => preg_match('/^\d{1,3}$/', (string) $request->query('roster', '')) === 1
                ? (string) $request->query('roster')
                : '',
            'periode' => $periode,
            'nilai' => mb_substr(trim((string) $request->query('nilai', '')), 0, 4),
            'dari' => $this->tanggal($request->query('dari')),
            'sampai' => $this->tanggal($request->query('sampai')),
            'sort' => mb_substr(trim((string) $request->query('sort', 'nama')), 0, 20),
            'dir' => $dir === 'desc' ? 'desc' : 'asc',
            'page' => max(1, (int) $request->query('page', 1)),
        ];
    }

    private function tahun(Request $request): int
    {
        $tahun = (int) $request->query('tahun', (string) CarbonImmutable::now()->year);
        $sekarang = CarbonImmutable::now()->year;

        return $tahun >= 2020 && $tahun <= $sekarang ? $tahun : $sekarang;
    }

    private function tanggal(mixed $nilai): string
    {
        $s = trim((string) $nilai);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1 ? $s : '';
    }
}
