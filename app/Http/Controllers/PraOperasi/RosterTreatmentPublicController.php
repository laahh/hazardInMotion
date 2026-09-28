<?php

declare(strict_types=1);

namespace App\Http\Controllers\PraOperasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PraOperasi\RosterTreatmentEvidenceStoreRequest;
use App\Models\SidRosterBannedMaster;
use App\Models\SidRosterTreatmentEvidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Form publik (tanpa login) untuk pengajuan bukti treatment atas pelanggaran
 * yang tercatat di sid_roster_banned_master. Hasil pengajuan berstatus
 * PENDING sampai direview oleh admin di halaman /pra-operasi/roster-banned.
 */
class RosterTreatmentPublicController extends Controller
{
    public function show(Request $request): View
    {
        $week = strtoupper(trim((string) $request->query('week', '')));
        $year = trim((string) $request->query('year', ''));

        return view('pra-operasi.public.roster-treatment-form', [
            'periodLabel' => $week !== '' && $year !== '' ? $week.' · '.$year : null,
            'prefillQuery' => trim((string) $request->query('nik', $request->query('sid', ''))),
        ]);
    }

    public function success(Request $request): View
    {
        return view('pra-operasi.public.roster-treatment-success', [
            'nama' => trim((string) $request->query('nama', '')),
            'submittedAt' => trim((string) $request->query('at', '')),
        ]);
    }

    /**
     * AJAX: cari data pelanggaran berdasarkan NIK atau SID.
     */
    public function lookup(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(['found' => false, 'message' => 'Ketik NIK atau SID Anda, lalu tekan Cari.']);
        }

        $rows = SidRosterBannedMaster::query()
            ->where('nik', $q)
            ->orWhere('sid', $q)
            ->orderByDesc('tanggal_pelanggaran')
            ->limit(20)
            ->get(['id', 'nik', 'sid', 'nama', 'perusahaan', 'site_dedicated', 'alasan_pelanggaran', 'tanggal_pelanggaran']);

        if ($rows->isEmpty()) {
            return response()->json([
                'found' => false,
                'message' => 'NIK/SID tidak ditemukan di daftar roster banned. Periksa kembali ejaannya atau hubungi admin.',
            ]);
        }

        $options = $rows->map(static fn (SidRosterBannedMaster $row): array => [
            'id' => $row->id,
            'nik' => $row->nik,
            'sid' => $row->sid,
            'nama' => $row->nama,
            'perusahaan' => $row->perusahaan,
            'site_dedicated' => $row->site_dedicated,
            'alasan_pelanggaran' => $row->alasan_pelanggaran,
            'tanggal_pelanggaran' => $row->tanggal_pelanggaran?->format('d M Y'),
        ])->values();

        return response()->json([
            'found' => true,
            'message' => 'Data ditemukan. Pilih pelanggaran yang ingin diajukan treatment-nya.',
            'options' => $options,
        ]);
    }

    public function store(RosterTreatmentEvidenceStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $master = SidRosterBannedMaster::query()->findOrFail((int) $validated['master_id']);

        $directory = 'roster-banned/treatment-evidence/'.$master->id;
        Storage::disk('local')->makeDirectory($directory);

        $file = $request->file('evidence_file');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin';
        $safeIdentifier = preg_replace('/[^A-Za-z0-9]+/', '', (string) ($master->sid ?: $master->nik)) ?: 'evidence';
        $filename = $safeIdentifier.'_'.time().'.'.$extension;

        $storedPath = $file->storeAs($directory, $filename, 'local');

        if ($storedPath === false || ! Storage::disk('local')->exists($storedPath)) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['evidence_file' => 'Gagal menyimpan file ke server. Coba lagi atau hubungi admin.']);
        }

        $submittedBy = trim((string) $validated['submitted_by']);

        SidRosterTreatmentEvidence::query()->create([
            'master_id' => $master->id,
            'nik' => $master->nik,
            'sid' => $master->sid,
            'evidence_file_path' => $storedPath,
            'tanggal_treatment' => $validated['tanggal_treatment'] ?? null,
            'catatan' => $validated['catatan'] ?? null,
            'submitted_by' => $submittedBy,
            'approval_status' => SidRosterTreatmentEvidence::STATUS_PENDING,
        ]);

        return redirect()->route('roster-treatment.public.success', [
            'nama' => $submittedBy,
            'at' => now()->format('d M Y, H:i'),
        ]);
    }
}
