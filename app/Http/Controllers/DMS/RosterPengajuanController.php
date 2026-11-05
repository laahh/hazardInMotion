<?php

declare(strict_types=1);

namespace App\Http\Controllers\DMS;

use App\Http\Controllers\Controller;
use App\Models\DmsRosterPengajuan;
use App\Models\User;
use App\Services\Dms\Roster\DmsRosterAksesPerusahaan;
use App\Services\Dms\Roster\DmsRosterMasterEditService;
use App\Services\Dms\Roster\DmsRosterMasterService;
use App\Services\Dms\Roster\DmsRosterPengajuanService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Master Roster → Approval Pengajuan: pengajuan dari Form Edit per SID,
 * disetujui dengan tanda tangan atau ditolak. Aturan aksesnya sama dengan
 * Approval Roster: hanya perusahaan yang di-assign ke user (admin tanpa
 * assignment = semua).
 */
final class RosterPengajuanController extends Controller
{
    private const PER_HALAMAN = 20;

    public function __construct(
        private readonly DmsRosterPengajuanService $pengajuan,
        private readonly DmsRosterMasterService $master,
        private readonly DmsRosterMasterEditService $edit,
        private readonly DmsRosterAksesPerusahaan $akses,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', DmsRosterPengajuan::STATUS_PENDING);
        $cakupan = $this->cakupan();
        $siap = DmsRosterPengajuanService::adaTabel();

        $daftar = new LengthAwarePaginator([], 0, self::PER_HALAMAN);
        $ringkas = [];
        if ($siap) {
            $q = $this->batasi(DmsRosterPengajuan::query(), $cakupan);
            $ringkas = (clone $q)->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->all();
            $daftar = $q->when(
                in_array($status, [DmsRosterPengajuan::STATUS_PENDING, DmsRosterPengajuan::STATUS_APPROVED, DmsRosterPengajuan::STATUS_REJECTED], true),
                static fn (Builder $b) => $b->where('status', $status),
            )->orderByDesc('created_at')->paginate(self::PER_HALAMAN)->withQueryString();
        }

        return view('dms.roster-master.pengajuan', [
            'daftar' => $daftar,
            'ringkas' => $ringkas,
            'status' => $status,
            'cakupan' => $cakupan,
            'siap' => $siap,
        ]);
    }

    public function show(int $id): View
    {
        $pengajuan = DmsRosterPengajuan::findOrFail($id);
        abort_unless($this->bolehReview($pengajuan), 403, 'Pengajuan ini bukan perusahaan yang di-assign ke akun Anda.');

        return view('dms.roster-master.pengajuan-show', [
            'pengajuan' => $pengajuan,
            'kodeKini' => $pengajuan->isPending()
                ? $this->edit->kodeTanggal((string) $pengajuan->kode_sid, array_column($pengajuan->perubahan, 'tanggal'))
                : [],
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

    public function ttd(int $id): \Symfony\Component\HttpFoundation\Response
    {
        $pengajuan = DmsRosterPengajuan::findOrFail($id);
        abort_unless($this->bolehReview($pengajuan), 403);
        abort_unless($pengajuan->ttd_path && Storage::disk('local')->exists($pengajuan->ttd_path), 404);

        return Storage::disk('local')->response($pengajuan->ttd_path, null, ['Cache-Control' => 'private, max-age=86400']);
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
        $pengajuan = DmsRosterPengajuan::findOrFail($id);
        if (! $this->bolehReview($pengajuan)) {
            return back()->with('error', 'Anda hanya boleh '.($setuju ? 'menyetujui' : 'menolak')
                .' pengajuan perusahaan yang di-assign ke akun Anda (menu Assign Role).');
        }

        try {
            $ttd = null;
            if ($setuju) {
                if ($data['ttd_mode'] === 'upload' && ! $request->hasFile('ttd_file')) {
                    throw new RuntimeException('Pilih file gambar tanda tangan untuk di-upload.');
                }
                $ttd = $this->master->simpanTtdDi(
                    'roster-master/pengajuan/'.$pengajuan->id,
                    $data['ttd_mode'] === 'gambar' ? ($data['ttd_data'] ?? null) : null,
                    $request->file('ttd_file'),
                    $data['ttd_mode'] === 'gambar',
                );
            }
            $this->pengajuan->review($pengajuan, $setuju, $data['catatan_review'] ?? null, mb_substr((string) (Auth::user()?->name ?? 'system'), 0, 120), $ttd);
        } catch (RuntimeException $e) {
            return back()->withInput($request->except('ttd_data', 'ttd_file'))->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dms.master-roster.pengajuan.show', $pengajuan->id)
            ->with('success', 'Pengajuan '.$pengajuan->kode_sid.' '.($setuju ? 'disetujui, jadwal sudah diperbarui.' : 'ditolak.'));
    }

    private function bolehReview(DmsRosterPengajuan $pengajuan): bool
    {
        return DmsRosterAksesPerusahaan::mencakup($this->cakupan(), [self::namaPt($pengajuan->perusahaan)]);
    }

    /**
     * @param  list<string>|null  $cakupan
     */
    private function batasi(Builder $q, ?array $cakupan): Builder
    {
        if ($cakupan === null) {
            return $q;
        }
        // Tabel pengajuan kecil: ejaan perusahaannya dicocokkan satu per satu.
        $ejaan = DmsRosterPengajuan::query()->distinct()->pluck('perusahaan')
            ->filter(static fn (?string $pt): bool => DmsRosterAksesPerusahaan::mencakup($cakupan, [self::namaPt($pt)]))
            ->values()->all();

        return $ejaan === [] ? $q->whereRaw('1 = 0') : $q->whereIn('perusahaan', $ejaan);
    }

    private static function namaPt(?string $perusahaan): string
    {
        $pt = trim((string) $perusahaan);

        return $pt === '' ? DmsRosterMasterService::TANPA_PERUSAHAAN : $pt;
    }

    /**
     * @return list<string>|null
     */
    private function cakupan(): ?array
    {
        $user = Auth::user();

        return $this->akses->cakupan($user instanceof User ? $user : null);
    }
}
