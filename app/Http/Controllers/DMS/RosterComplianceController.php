<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Services\Dms\Roster\DmsRosterComplianceService;
use App\Services\Dms\Roster\DmsRosterRegLiveService;
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
        return view('dms.roster-compliance', $this->service->dashboard(self::filters($request)));
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

        $detail = $this->service->detailKaryawan($sid, self::tahun($request));

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
        $filters = self::filters($request);
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
     * Isi tabel scorecard sesuai filter yang sedang aktif (tanpa paginasi) —
     * tombol unduh di toolbar tabel Ringkasan Roster.
     */
    public function unduhScorecard(Request $request): StreamedResponse
    {
        $baris = $this->service->daftarTersaring(self::filters($request));
        $nama = 'scorecard-roster-'.CarbonImmutable::now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () use ($baris): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'SID', 'Nama', 'Jabatan', 'Kategori', 'Perusahaan', 'Site', 'Roster ke-',
                'On-site', 'Pagi', 'Malam', 'Off', 'Cuti',
                'Shift kerja maks (hari)', 'Rentang shift maks',
                'On-site maks (hari)', 'Rentang on-site maks',
                'Cuti min (hari)', 'Rentang cuti min',
                'Status', 'Pelanggaran YTD', 'Wajib cuti',
            ], ';');

            foreach ($baris as $r) {
                fputcsv($out, [
                    $r['sid'], $r['nama'], $r['jabatan'], $r['kategori'], $r['perusahaan'], $r['site'], $r['roster'],
                    $r['hadir'], $r['pagi'], $r['malam'], $r['off'], $r['cutiCur'],
                    $r['onNoOff'], $r['rentang_onNoOff'],
                    $r['onAll'], $r['rentang_onAll'],
                    $r['cutiMin'], $r['rentang_cutiMin'],
                    $r['status'], $r['everRed'] ? 'Ya' : 'Tidak', $r['wajib'] ? 'Ya' : 'Tidak',
                ], ';');
            }

            fclose($out);
        }, $nama, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Judul kolom nilai, mulai, selesai per kode aturan — dipakai CSV. */
    private const KOLOM_TERKINI = [
        'onsite' => ['Periode kerja', 'Mulai on-site', 'Hari terakhir'],
        'beruntun' => ['Bekerja berturut-turut', 'Mulai', 'Hari terakhir'],
        'jam12' => ['Durasi shift terakhir', 'Check-in', 'Check-out'],
        'map1' => ['Panjang blok kerja', 'Mulai blok', 'Akhir blok'],
        'map2' => ['Panjang blok kerja', 'Mulai blok', 'Akhir blok'],
        'map3' => ['Panjang blok kerja', 'Mulai blok', 'Akhir blok'],
    ];

    /**
     * Detail satu aturan banned per hari ini (langsung dari RFID) — dimuat modal
     * kartu aturan di Ringkasan Roster saat tab dibuka, supaya
     * ribuan baris tidak ikut di-render bersama halaman. $kode dibatasi
     * constraint route.
     */
    public function detailTerkini(DmsRosterRegLiveService $live, string $kode): JsonResponse
    {
        $data = $live->ambil();
        if ($data === null) {
            return response()->json(['up' => false, 'pesan' => 'RFID tidak terjangkau, coba beberapa saat lagi.'], 503);
        }

        return response()->json([
            'up' => true,
            'per' => $data['per'],
            'diambil' => $data['diambil'],
            'baris' => $data['daftar'][$kode],
        ]);
    }

    /**
     * Timeline harian satu karyawan langsung dari RFID (kode shift, flag
     * aturan, gate & jam check-in/out, durasi) — dibuka dari baris tabel
     * modal temuan.
     */
    public function timelineTerkini(DmsRosterRegLiveService $live, string $sid): JsonResponse
    {
        $data = $live->timeline(mb_substr(trim($sid), 0, 20));

        if ($data === null) {
            return response()->json(['up' => false, 'pesan' => 'RFID tidak terjangkau, coba beberapa saat lagi.'], 503);
        }

        if (! $data['ditemukan']) {
            return response()->json(['up' => false, 'pesan' => 'Karyawan tidak ditemukan.'], 404);
        }

        return response()->json(['up' => true] + $data);
    }

    /** Jendela "Cari manual": hari ini + 74 hari ke belakang. */
    private const CARI_MANUAL_HARI = 75;

    /**
     * "Cari manual" di tabel scorecard Ringkasan Roster: SATU karyawan, pola
     * harian dirakit langsung dari seluruh tap RFID 75 hari terakhir.
     *
     * ?sid= langsung memuat timeline. ?q= mencari dulu: bila cocok tepat satu
     * orang (atau SID/NIK persis) timeline-nya dimuat, bila lebih dari satu
     * dikembalikan daftar pilihan supaya pengguna memilih satu.
     */
    public function cariManual(Request $request, DmsRosterRegLiveService $live): JsonResponse
    {
        $sid = mb_strtoupper(mb_substr(trim((string) $request->query('sid', '')), 0, 20));

        if ($sid === '') {
            $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
            if (mb_strlen($q) < 3) {
                return response()->json(['up' => false, 'pesan' => 'Ketik minimal 3 karakter nama, SID, atau NIK.'], 422);
            }

            $calon = $live->cariKaryawan($q);
            if ($calon === []) {
                return response()->json(['up' => false, 'pesan' => 'Karyawan "'.$q.'" tidak ditemukan di master karyawan roster.'], 404);
            }

            $persis = array_values(array_filter($calon, static fn (array $c): bool => $c['sid'] === mb_strtoupper($q)));
            if (count($calon) > 1 && $persis === []) {
                return response()->json([
                    'up' => true,
                    'pilihan' => array_slice($calon, 0, 10),
                    'terpotong' => count($calon) > 10,
                ]);
            }

            $sid = ($persis[0] ?? $calon[0])['sid'];
        }

        if (! preg_match('/^[A-Z0-9]{1,20}$/', $sid)) {
            return response()->json(['up' => false, 'pesan' => 'Kode SID tidak valid.'], 422);
        }

        $data = $live->timeline($sid, self::CARI_MANUAL_HARI);
        if ($data === null) {
            return response()->json(['up' => false, 'pesan' => 'RFID tidak terjangkau, coba beberapa saat lagi.'], 503);
        }
        if (! $data['ditemukan']) {
            return response()->json(['up' => false, 'pesan' => 'Karyawan tidak ditemukan.'], 404);
        }

        return response()->json(['up' => true] + $data);
    }

    /**
     * Daftar lengkap satu aturan banned per hari ini sebagai CSV.
     */
    public function unduhTerkini(DmsRosterRegLiveService $live, string $kode): StreamedResponse
    {
        $data = $live->ambil();
        abort_if($data === null, 503, 'RFID tidak terjangkau, coba beberapa saat lagi.');

        $baris = $data['daftar'][$kode];
        [$kolomNilai, $kolomMulai, $kolomSelesai] = self::KOLOM_TERKINI[$kode];
        $nama = 'aturan-'.$kode.'-rfid-'.CarbonImmutable::now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () use ($baris, $kolomNilai, $kolomMulai, $kolomSelesai): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'SID', 'Nama', 'Jabatan', 'Kategori', 'Perusahaan', 'Site',
                $kolomNilai, $kolomMulai, $kolomSelesai, 'Keterangan', 'Scan terakhir',
            ], ';');

            foreach ($baris as $r) {
                fputcsv($out, [
                    $r['sid'], $r['nama'], $r['jabatan'], $r['kategori'], $r['perusahaan'], $r['site'],
                    $r['nilai'], $r['mulai'], $r['selesai'],
                    $r['keterangan'], $r['scan_terakhir'],
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
    public static function filters(Request $request): array
    {
        $periode = (string) $request->query('periode', '');
        $periode = in_array($periode, ['kuartal', 'bulan', 'minggu', 'custom'], true) ? $periode : '';

        $dir = strtolower((string) $request->query('dir', 'asc'));

        return [
            'tahun' => self::tahun($request),
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
            'dari' => self::tanggal($request->query('dari')),
            'sampai' => self::tanggal($request->query('sampai')),
            'sort' => mb_substr(trim((string) $request->query('sort', 'nama')), 0, 20),
            'dir' => $dir === 'desc' ? 'desc' : 'asc',
            'page' => max(1, (int) $request->query('page', 1)),
        ];
    }

    private static function tahun(Request $request): int
    {
        $tahun = (int) $request->query('tahun', (string) CarbonImmutable::now()->year);
        $sekarang = CarbonImmutable::now()->year;

        return $tahun >= 2020 && $tahun <= $sekarang ? $tahun : $sekarang;
    }

    private static function tanggal(mixed $nilai): string
    {
        $s = trim((string) $nilai);

        // Format cocok belum tentu tanggal yang ada (mis. 2026-13-99) —
        // tanggal mustahil akan menggagalkan parsing periode di service.
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            ? $s
            : '';
    }
}
