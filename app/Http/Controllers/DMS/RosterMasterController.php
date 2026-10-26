<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Models\DmsRosterMasterUpload;
use App\Services\Dms\Roster\DmsRosterKaryawanAktif;
use App\Services\Dms\Roster\DmsRosterMasterService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * /dms/master-roster — Master Roster: upload jadwal kerja per karyawan
 * (Excel) dan approval-nya oleh admin. Logika baca & simpan file di
 * DmsRosterMasterService.
 */
final class RosterMasterController extends Controller
{
    private const PER_HALAMAN = 20;

    public function __construct(
        private readonly DmsRosterMasterService $service,
        private readonly DmsRosterKaryawanAktif $karyawanAktif,
    ) {}

    /**
     * Halaman Upload Roster: form upload + riwayat upload.
     */
    public function upload(Request $request): View
    {
        return view('dms.roster-master.upload', [
            'daftar' => $this->daftar($request, ''),
            'ringkas' => $this->ringkas(),
            'bulanTemplate' => CarbonImmutable::now()->addMonthNoOverflow()->format('Y-m'),
            'tahunTemplate' => CarbonImmutable::now()->addMonthsNoOverflow(1)->year,
            // Daftar perusahaan langsung dari bep_vw_safety_karyawan_aktif (cache 1 jam);
            // ?segarkan_pt=1 memaksa ambil ulang. null = view tidak terjangkau & belum ada salinan.
            'perusahaan' => $this->karyawanAktif->perusahaan($request->boolean('segarkan_pt')),
            'perusahaanGalat' => $this->karyawanAktif->galatTerakhir(),
        ]);
    }

    /**
     * Halaman Approval Roster: daftar upload, bawaan yang menunggu approval.
     */
    public function approval(Request $request): View
    {
        $status = (string) $request->query('status', DmsRosterMasterUpload::STATUS_PENDING);

        return view('dms.roster-master.approval', [
            'daftar' => $this->daftar($request, $status),
            'ringkas' => $this->ringkas(),
            'status' => $status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:150'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [
            'judul.required' => 'Judul roster wajib diisi.',
            'file.required' => 'File Excel roster wajib diupload.',
            'file.mimes' => 'File harus Excel (.xlsx / .xls).',
        ]);

        $hasil = $this->service->simpan($request->file('file'), $data['judul'], $data['catatan'] ?? null, $this->pengguna());

        if ($hasil['upload'] === null) {
            return back()->withInput()->with('galat_roster', $hasil['galat']);
        }

        $u = $hasil['upload'];

        return redirect()
            ->route('dms.master-roster.show', $u->id)
            ->with('success', 'Roster "'.$u->judul.'" terupload: '.number_format($u->jumlah_karyawan, 0, ',', '.').' karyawan, '
                .number_format($u->jumlah_jadwal, 0, ',', '.').' jadwal. Menunggu approval.');
    }

    public function show(Request $request, int $id): View
    {
        $upload = DmsRosterMasterUpload::findOrFail($id);
        $halaman = max(1, (int) $request->query('hal', 1));
        $cari = mb_substr(trim((string) $request->query('q', '')), 0, 50);
        $grid = $this->service->grid($upload, $halaman, 25, $cari);

        $tanggal = [];
        for ($t = CarbonImmutable::parse($upload->periode_mulai); $t->lte($upload->periode_selesai); $t = $t->addDay()) {
            $tanggal[] = $t;
        }

        return view('dms.roster-master.show', [
            'upload' => $upload,
            'grid' => $grid,
            'tanggal' => $tanggal,
            'halaman' => $halaman,
            'totalHalaman' => max(1, (int) ceil($grid['total'] / 25)),
            'cari' => $cari,
            'tumpang' => $upload->isPending() ? $this->service->jumlahTumpang($upload) : 0,
        ]);
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        return $this->review($request, $id, true);
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        return $this->review($request, $id, false);
    }

    public function destroy(int $id): RedirectResponse
    {
        $upload = DmsRosterMasterUpload::findOrFail($id);

        try {
            $this->service->hapus($upload);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dms.master-roster.upload')->with('success', 'Roster "'.$upload->judul.'" dihapus.');
    }

    /**
     * Template untuk rentang bulan ?dari=YYYY-MM&sampai=YYYY-MM (maks 12 bulan,
     * mis. Januari–Desember). ?bulan=YYYY-MM lama tetap diterima (satu bulan).
     * ?perusahaan=<nama> mengisi baris karyawan perusahaan itu (SID, nama,
     * jabatan, site), ?site=<site> membatasi ke satu site; jadwalnya tinggal diisi.
     */
    public function template(Request $request): StreamedResponse|RedirectResponse
    {
        $bulan = static fn (string $v): ?CarbonImmutable => preg_match('/^\d{4}-\d{2}$/', $v) === 1
            ? CarbonImmutable::createFromFormat('!Y-m', $v)
            : null;
        $dari = $bulan((string) $request->query('dari', $request->query('bulan', '')))
            ?? CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();
        $sampai = $bulan((string) $request->query('sampai', '')) ?? $dari;

        // Filter perusahaan: baris karyawan terisi dari bep_vw_safety_karyawan_aktif.
        $pt = mb_substr(trim((string) $request->query('perusahaan', '')), 0, 255);
        $site = mb_substr(trim((string) $request->query('site', '')), 0, 60);
        $karyawan = [];
        if ($pt !== '') {
            $karyawan = $this->karyawanAktif->untukPerusahaan($pt, $site);
            if ($karyawan === null) {
                return redirect()->route('dms.master-roster.upload')
                    ->with('error', 'Daftar karyawan aktif belum tersedia, coba lagi setelah sinkronisasi berikutnya.');
            }
            if ($karyawan === []) {
                return redirect()->route('dms.master-roster.upload')
                    ->with('error', 'Tidak ada karyawan aktif untuk perusahaan "'.$pt.'"'.($site !== '' ? ' di site "'.$site.'"' : '').'.');
            }
        }

        try {
            $writer = new Xlsx($this->service->template($dari, $sampai, $karyawan));
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('dms.master-roster.upload')->with('error', 'Template tidak dibuat: '.$e->getMessage());
        }

        $nama = 'template_master_roster_'
            .($pt !== '' ? \Illuminate\Support\Str::slug(\App\Services\Dms\Roster\DmsRosterSyncService::kodePt($pt)).'_' : '')
            .($pt !== '' && $site !== '' ? \Illuminate\Support\Str::slug($site).'_' : '')
            .$dari->format('Y-m').($sampai->format('Y-m') !== $dari->format('Y-m') ? '_sd_'.$sampai->format('Y-m') : '').'.xlsx';

        return response()->streamDownload(static function () use ($writer): void {
            $writer->save('php://output');
        }, $nama, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * File asli yang di-upload.
     */
    public function file(int $id): StreamedResponse
    {
        $upload = DmsRosterMasterUpload::findOrFail($id);
        abort_unless($upload->path_file !== '' && Storage::disk('local')->exists($upload->path_file), 404);

        return Storage::disk('local')->download($upload->path_file, $upload->nama_file);
    }

    /**
     * Gambar tanda tangan approver (disimpan di disk local, tidak publik).
     */
    public function ttd(int $id): \Symfony\Component\HttpFoundation\Response
    {
        $upload = DmsRosterMasterUpload::findOrFail($id);
        abort_unless($upload->ttd_path && Storage::disk('local')->exists($upload->ttd_path), 404);

        return Storage::disk('local')->response($upload->ttd_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    private function review(Request $request, int $id, bool $setuju): RedirectResponse
    {
        $data = $request->validate([
            'catatan_review' => [$setuju ? 'nullable' : 'required', 'string', 'max:1000'],
            // Persetujuan: tanda tangan digambar (data URL PNG) ATAU file gambar.
            'ttd_mode' => [$setuju ? 'required' : 'nullable', 'in:gambar,upload'],
            'ttd_data' => ['nullable', 'string', 'max:3000000'],
            'ttd_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
        ], [
            'catatan_review.required' => 'Alasan penolakan wajib diisi.',
            'ttd_mode.required' => 'Persetujuan wajib disertai tanda tangan.',
            'ttd_file.mimes' => 'File tanda tangan harus PNG atau JPG.',
            'ttd_file.max' => 'File tanda tangan maksimal 2 MB.',
        ]);
        $upload = DmsRosterMasterUpload::findOrFail($id);

        try {
            $ttd = null;
            if ($setuju) {
                if ($data['ttd_mode'] === 'upload' && ! $request->hasFile('ttd_file')) {
                    throw new RuntimeException('Pilih file gambar tanda tangan untuk di-upload.');
                }
                $ttd = $this->service->simpanTtd(
                    $upload,
                    $data['ttd_mode'] === 'gambar' ? ($data['ttd_data'] ?? null) : null,
                    $data['ttd_mode'] === 'upload' ? $request->file('ttd_file') : null,
                );
            }
            $this->service->review($upload, $setuju, $data['catatan_review'] ?? null, $this->pengguna(), $ttd);
        } catch (RuntimeException $e) {
            return back()->withInput($request->except('ttd_data', 'ttd_file'))->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dms.master-roster.show', $upload->id)
            ->with('success', 'Roster "'.$upload->judul.'" '.($setuju ? 'disetujui.' : 'ditolak.'));
    }

    private function daftar(Request $request, string $status): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $q = DmsRosterMasterUpload::query()->orderByDesc('created_at');
        if (in_array($status, [DmsRosterMasterUpload::STATUS_PENDING, DmsRosterMasterUpload::STATUS_APPROVED, DmsRosterMasterUpload::STATUS_REJECTED], true)) {
            $q->where('status', $status);
        }

        return $q->paginate(self::PER_HALAMAN)->withQueryString();
    }

    /**
     * @return array<string, int>
     */
    private function ringkas(): array
    {
        $n = DmsRosterMasterUpload::query()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return [
            DmsRosterMasterUpload::STATUS_PENDING => (int) ($n[DmsRosterMasterUpload::STATUS_PENDING] ?? 0),
            DmsRosterMasterUpload::STATUS_APPROVED => (int) ($n[DmsRosterMasterUpload::STATUS_APPROVED] ?? 0),
            DmsRosterMasterUpload::STATUS_REJECTED => (int) ($n[DmsRosterMasterUpload::STATUS_REJECTED] ?? 0),
        ];
    }

    private function pengguna(): string
    {
        return mb_substr((string) (Auth::user()?->name ?? 'system'), 0, 150);
    }
}
