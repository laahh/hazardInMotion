<?php

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Models\PetaRisikoFlagAktivitas as Flag;
use App\Services\PetaRisiko\Drm\DrmRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Flag status aktivitas (AKTIF/NONAKTIF) per detail lokasi PJA.
 * Pengajuan berstatus PENDING; berlaku setelah disetujui orang lain (bukan pengaju).
 */
class PetaRisikoFlagController extends Controller
{
    public function __construct(private readonly DrmRepository $repo)
    {
    }

    public function index(string $pja): JsonResponse
    {
        if (! $this->tabelAda()) {
            return response()->json(['tersedia' => false, 'flag' => []]);
        }

        return response()->json([
            'tersedia' => true,
            'saya' => auth()->id(),
            'flag' => Flag::where('pja_id', $pja)->orderByDesc('id')->limit(1000)->get()->map(fn (Flag $f) => $this->bentuk($f)),
        ]);
    }

    public function store(Request $request, string $pja): JsonResponse
    {
        abort_unless($this->tabelAda(), 503, 'Tabel flag belum dibuat (migrasi belum dijalankan).');
        $data = $this->repo->pja($pja);
        abort_if($data === null, 404, 'PJA tidak ditemukan di snapshot.');

        $mundur = (int) config('peta_risiko_drm.flag_maks_mundur_hari', 14);
        $v = $request->validate([
            'dl' => ['required', 'string', 'max:32'],
            'aktivitas' => ['required', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Flag::AKTIF, Flag::NONAKTIF])],
            'mulai' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now()->subDays($mundur)->toDateString()],
            'selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:mulai'],
            'alasan' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'mulai.after_or_equal' => "Tanggal mulai paling jauh {$mundur} hari ke belakang.",
            'alasan.min' => 'Alasan minimal 10 karakter.',
        ]);

        $lokasi = collect($data['lokasi'])->firstWhere('dl', $v['dl']);
        // Beranda PJA: flag per kelompok aktivitas (proses bisnis) di satu detail lokasi, kunci "kel:KODE".
        $kelompok = str_starts_with($v['aktivitas'], 'kel:') ? substr($v['aktivitas'], 4) : null;
        $namaProses = array_map(fn ($p) => $p[0], (array) config('peta_risiko_drm.proses_bisnis')) + ['X' => 'Belum terpetakan'];
        $sah = $kelompok !== null
            ? isset($namaProses[$kelompok])
            : collect($data['akt'])->contains(fn ($r) => $r[0] === $v['dl'] && $r[1] === $v['aktivitas']);
        if ($lokasi === null || ! $sah) {
            throw ValidationException::withMessages(['aktivitas' => 'Kelompok aktivitas / detail lokasi ini bukan bagian dari PJA tersebut.']);
        }
        $adaPending = Flag::where('detail_lokasi_id', $v['dl'])->where('aktivitas_hash', Flag::hash($v['aktivitas']))
            ->where('status_review', Flag::PENDING)->exists();
        if ($adaPending) {
            throw ValidationException::withMessages(['aktivitas' => 'Masih ada pengajuan PENDING untuk aktivitas ini. Tunggu direview dulu.']);
        }

        $user = $request->user();
        $flag = Flag::create([
            'pja_id' => $pja,
            'pja_nama' => $data['info']['nama'] ?? null,
            'detail_lokasi_id' => $v['dl'],
            'detail_lokasi_nama' => $lokasi['detlok'] ?? null,
            'aktivitas_kunci' => $v['aktivitas'],
            'aktivitas_hash' => Flag::hash($v['aktivitas']),
            'aktivitas_nama' => mb_substr((string) ($kelompok !== null ? $namaProses[$kelompok] : ($data['namaAkt'][$v['aktivitas']] ?? $v['aktivitas'])), 0, 500),
            'status_aktivitas' => $v['status'],
            'mulai' => $v['mulai'],
            'selesai' => $v['selesai'] ?? null,
            'alasan' => trim($v['alasan']),
            'status_review' => Flag::PENDING,
            'diajukan_oleh_id' => $user?->id,
            'diajukan_oleh_nama' => $user?->name,
        ]);

        return response()->json(['flag' => $this->bentuk($flag)], 201);
    }

    public function review(Request $request, Flag $flag): JsonResponse
    {
        $v = $request->validate([
            'keputusan' => ['required', Rule::in([Flag::APPROVED, Flag::REJECTED])],
            'catatan' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('keputusan') === Flag::REJECTED)],
        ], ['catatan.required' => 'Catatan wajib diisi bila menolak.']);

        abort_if($flag->status_review !== Flag::PENDING, 422, 'Pengajuan ini sudah direview.');
        abort_if($flag->diajukan_oleh_id !== null && (int) $flag->diajukan_oleh_id === (int) $request->user()?->id, 403, 'Pengaju tidak boleh menyetujui pengajuannya sendiri.');

        $flag->update([
            'status_review' => $v['keputusan'],
            'direview_oleh_id' => $request->user()?->id,
            'direview_oleh_nama' => $request->user()?->name,
            'direview_pada' => CarbonImmutable::now(),
            'catatan_review' => $v['catatan'] ?? null,
        ]);

        return response()->json(['flag' => $this->bentuk($flag->fresh())]);
    }

    /** @return array<string, mixed> */
    private function bentuk(Flag $f): array
    {
        return [
            'id' => $f->id,
            'dl' => $f->detail_lokasi_id,
            'aktivitas' => $f->aktivitas_kunci,
            'status' => $f->status_aktivitas,
            'mulai' => $f->mulai?->toDateString(),
            'selesai' => $f->selesai?->toDateString(),
            'alasan' => $f->alasan,
            'review' => $f->status_review,
            'pengaju' => $f->diajukan_oleh_nama,
            'pengajuId' => $f->diajukan_oleh_id,
            'reviewer' => $f->direview_oleh_nama,
            'direview' => $f->direview_pada?->format('Y-m-d H:i'),
            'catatan' => $f->catatan_review,
            'dibuat' => $f->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function tabelAda(): bool
    {
        return Schema::hasTable((new Flag())->getTable());
    }
}
