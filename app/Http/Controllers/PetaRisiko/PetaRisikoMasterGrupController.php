<?php

declare(strict_types=1);

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\Drm\Master\DrmMasterDb;
use App\Services\PetaRisiko\Drm\Master\DrmMasterGrup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Master "Grup lokasi & aktivitas" (/peta-risiko-pja/master/grup): lihat dan edit M05 (grup lokasi → baris HIRA)
 * per aktivitas, serta kelompok aktivitas (M02). Berlaku pada peta-risiko:sync berikutnya.
 */
final class PetaRisikoMasterGrupController extends Controller
{
    public function __construct(private readonly DrmMasterGrup $grup)
    {
    }

    /**
     * Tabel datar satu baris = satu baris HIRA (sama konsep dengan /master/baris): kolom "Grup lokasi" berisi
     * chip grup yang bisa ditambah/dikeluarkan per baris, disaring/dicari di browser (DataTables).
     */
    public function index(Request $request): View
    {
        $data = ['siap' => DrmMasterDb::terisi(), 'bolehUbah' => $this->bolehUbah($request)];
        if (! $data['siap']) {
            return view('peta-risiko-pja.drm.master-grup', $data);
        }
        // Site & Perusahaan (lewat PJA): FILTER di tabel, bukan kolom (koreksi user 25 Nov 2026 — awalnya
        // ditampilkan sebagai kolom info, tapi yang dimaksud adalah dropdown filter seperti risiko/kelompok/grup).
        $lokasi = $this->grup->lokasiRingkasPerBaris();
        // PIC Perusahaan: ditampilkan sebagai KOLOM (bukan filter) — keputusan user 26 Nov 2026, beda perlakuan
        // dengan Site/Perusahaan di atas.
        $pic = $this->grup->picPerRi();
        $rows = array_map(fn (array $r) => $r + ['lok' => $lokasi[$r['ri']] ?? [], 'pic' => $pic[$r['ri']] ?? []], $this->grup->semuaBaris());

        return view('peta-risiko-pja.drm.master-grup', $data + [
            'daftarGrup' => $this->grup->daftarGrup(),
            'rows' => $rows,
            'dlPerGrup' => $this->grup->semuaDetailLokasi(),
            'kelompok' => $this->grup->kelompok(),
            'site' => $this->grup->daftarSite(),
            'perusahaan' => $this->grup->daftarPerusahaan(),
        ]);
    }

    /** Header unduhan grup, dipakai di unduh(). */
    private const HEADER_UNDUH = ['ID baris', 'Aktivitas HIRA', 'Sub aktivitas', 'Bahaya - risiko', 'Risiko awal', 'Kelompok aktivitas', 'Grup lokasi', 'Site', 'Detail lokasi', 'Perusahaan', 'PIC Perusahaan'];

    /**
     * Unduh CSV flat (1 baris = 1 grup × site/lokasi/detail × perusahaan, lihat DrmMasterGrup::eksporBarisLazy),
     * mengikuti filter yang sedang aktif di tabel.
     *
     * CSV streaming, BUKAN Xlsx (koreksi 2026-11-25, setelah unduhan serupa di /peta-risiko-pja/korelasi
     * kehabisan memori 2 GB): grup × lokasi × PJA bisa pecah jadi sangat banyak baris, dan PhpSpreadsheet (Xlsx)
     * selalu menyimpan semua sel di memori sebelum menulis berkas. fputcsv per baris dari generator ini membuat
     * memori tetap datar berapa pun totalnya (pola yang sama dengan RoadSummaryController::streamCsv()).
     */
    public function unduh(Request $request): StreamedResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        $v = $request->validate([
            'kb' => ['nullable', 'in:Significant,High,Medium,Low'],
            'kelompok' => ['nullable', 'string', 'max:10'],
            'grup' => ['nullable', 'string', 'max:20'],
            'site' => ['nullable', 'string', 'max:60'],
            'pja' => ['nullable', 'string', 'max:200'],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        return response()->stream(function () use ($v): void {
            set_time_limit(0);
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::HEADER_UNDUH, ';');
            $ditulis = 0;
            foreach ($this->grup->eksporBarisLazy($v) as $b) {
                fputcsv($out, [$b['ri'], $b['aktivitas'], $b['sub'], $b['bahaya'], $b['kb'], $b['kelompok'], $b['grup'], $b['site'], $b['detail'], $b['perusahaan'], $b['pic']], ';');
                if ((++$ditulis % 5000) === 0) {
                    flush();
                }
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="peta-risiko-grup-lokasi-aktivitas-'.now()->format('Ymd-His').'.csv"',
            'Cache-Control' => 'no-store, no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** Tambahkan baris terpilih (bisa lintas aktivitas) ke grup — chip "+ Tambah ke grup" per baris atau aksi borongan. */
    public function tambah(Request $request, int $gid): RedirectResponse|JsonResponse
    {
        $this->izin($request);
        $v = $request->validate([
            'ri' => ['required', 'array', 'min:1', 'max:2000'],
            'ri.*' => ['integer', 'min:0'],
        ], ['ri.required' => 'Pilih minimal satu baris.']);
        $ri = array_map('intval', $v['ri']);

        return $this->jalankan($request, fn () => $this->grup->tambahBaris($gid, $ri), fn ($n) => $n ? "{$n} baris ditambahkan ke grup." : 'Baris terpilih sudah ada di grup ini.');
    }

    /** Keluarkan baris terpilih dari grup $gid (chip hapus per baris, atau aksi borongan). */
    public function ubahBaris(Request $request, int $gid): RedirectResponse|JsonResponse
    {
        $this->izin($request);
        $v = $request->validate([
            'ri' => ['required', 'array', 'min:1', 'max:2000'],
            'ri.*' => ['integer', 'min:0'],
        ], ['ri.required' => 'Pilih minimal satu baris.']);
        $ri = array_map('intval', $v['ri']);

        return $this->jalankan($request, fn () => $this->grup->hapus($gid, $ri), fn ($n) => "{$n} baris dikeluarkan dari grup.");
    }

    /** Ubah kelompok aktivitas (M02). */
    public function kelompok(Request $request, int $aid): RedirectResponse|JsonResponse
    {
        $this->izin($request);
        $v = $request->validate(['kelompok' => ['required', 'string', 'max:10']]);

        return $this->jalankan($request, fn () => $this->grup->ubahKelompok($aid, $v['kelompok']), fn ($ubah) => $ubah ? 'Kelompok aktivitas diperbarui.' : 'Kelompok aktivitas tidak berubah.');
    }

    /**
     * Jalankan aksi; AJAX (Accept: application/json, dipakai halaman ini supaya tidak reload) dapat JSON,
     * request biasa tetap dapat redirect + flash (fallback kalau JS mati).
     */
    private function jalankan(Request $request, callable $aksi, callable $pesan): RedirectResponse|JsonResponse
    {
        abort_unless(DrmMasterDb::terisi(), 404);
        try {
            $hasil = $aksi();
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['pesan' => $e->getMessage()], 422);
            }

            return redirect()->route('peta-risiko-pja.master.grup.index')->withErrors(['grup' => $e->getMessage()]);
        }
        $teks = $pesan($hasil).' Berlaku setelah peta-risiko:sync berikutnya.';
        if ($request->wantsJson()) {
            return response()->json(['pesan' => $teks]);
        }

        return redirect()->route('peta-risiko-pja.master.grup.index')->with('pesan', $teks);
    }

    private function izin(Request $request): void
    {
        abort_unless($this->bolehUbah($request), 403, 'Anda tidak termasuk editor master mapping.');
    }

    private function bolehUbah(Request $request): bool
    {
        $editor = (array) config('peta_risiko_drm.master.editor', []);
        $user = $request->user();

        return $user !== null && ($editor === [] || in_array((string) $user->getAuthIdentifier(), $editor, true)
            || in_array((string) ($user->email ?? ''), $editor, true));
    }
}
