<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Models\DmsRosterMasterUpload;
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

    public function template(Request $request): StreamedResponse
    {
        $bulan = (string) $request->query('bulan', '');
        $awal = preg_match('/^\d{4}-\d{2}$/', $bulan) === 1
            ? CarbonImmutable::createFromFormat('!Y-m', $bulan)
            : CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();

        $writer = new Xlsx($this->service->template($awal));

        return response()->streamDownload(static function () use ($writer): void {
            $writer->save('php://output');
        }, 'template_master_roster_'.$awal->format('Y-m').'.xlsx', [
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

    private function review(Request $request, int $id, bool $setuju): RedirectResponse
    {
        $data = $request->validate([
            'catatan_review' => [$setuju ? 'nullable' : 'required', 'string', 'max:1000'],
        ], [
            'catatan_review.required' => 'Alasan penolakan wajib diisi.',
        ]);
        $upload = DmsRosterMasterUpload::findOrFail($id);

        try {
            $this->service->review($upload, $setuju, $data['catatan_review'] ?? null, $this->pengguna());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
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
