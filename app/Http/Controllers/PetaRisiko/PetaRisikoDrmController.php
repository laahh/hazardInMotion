<?php

namespace App\Http\Controllers\PetaRisiko;

use App\Http\Controllers\Controller;
use App\Services\PetaRisiko\Drm\DrmRepository;
use App\Services\PetaRisiko\Drm\DrmSapDetailLokasi;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Peta Risiko PJA (DRM 2.1): identifikasi bahaya-risiko HIRA oleh SAP per Site / Group Lokasi / PJA.
 * Data dari snapshot `php artisan peta-risiko:sync` (ClickHouse OBDS).
 */
class PetaRisikoDrmController extends Controller
{
    public function __construct(private readonly DrmRepository $repo)
    {
    }

    public function index(): View
    {
        return view('peta-risiko-pja.drm.index', ['meta' => $this->repo->meta(), 'adaExcel' => $this->repo->adaEkspor()]);
    }

    public function pja(string $pja): View
    {
        $meta = $this->repo->meta();
        abort_if($meta === null, 404, 'Snapshot Peta Risiko belum dibuat.');
        $ringkas = collect($meta['pja'])->firstWhere('id', $pja);
        abort_if($ringkas === null, 404, 'PJA tidak ditemukan di snapshot.');

        return view('peta-risiko-pja.drm.pja', ['meta' => $meta, 'ringkas' => $ringkas]);
    }

    /** Isi lengkap satu PJA (dimuat JS setelah halaman tampil). */
    public function pjaData(string $pja): JsonResponse
    {
        $data = $this->repo->pja($pja);
        abort_if($data === null, 404);

        return response()->json($data);
    }

    /** Excel mapping bahaya-risiko (ditulis oleh peta-risiko:sync). */
    public function unduhMapping(): BinaryFileResponse
    {
        abort_unless($this->repo->adaEkspor(), 404, 'Excel mapping belum dibuat. Jalankan php artisan peta-risiko:sync.');
        $sampai = $this->repo->meta()['sampai'] ?? now()->toDateString();

        return response()->download($this->repo->pathEkspor(), "mapping-bahaya-risiko-{$sampai}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Laporan SAP satu detail lokasi untuk minggu ke-w snapshot (0 = minggu terbaru), dengan keterangan
     * pemetaan master DRM 2.1. Dipanggil saat baris detail lokasi di tab Group Lokasi dibuka.
     */
    public function sapDetailLokasi(Request $request, string $dl, DrmSapDetailLokasi $sap): JsonResponse
    {
        $meta = $this->repo->meta();
        abort_if($meta === null, 404, 'Snapshot Peta Risiko belum dibuat.');
        $info = ((array) ($meta['beranda']['dl'] ?? []))[$dl] ?? null;
        abort_if($info === null, 404, 'Detail lokasi tidak ada di snapshot.');

        $minggu = (array) ($meta['beranda']['minggu'] ?? []);
        $w = max(0, min(count($minggu) - 1, (int) $request->query('w', 0)));
        abort_if($minggu === [], 404);
        $dari = CarbonImmutable::parse((string) $minggu[$w]);
        $akhirData = CarbonImmutable::parse((string) $meta['sampai']);
        $sampai = $dari->addDays(6)->min($akhirData);

        try {
            return response()->json($sap->ambil((int) $dl, (string) $info[3], $dari, $sampai));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['pesan' => 'Laporan SAP tidak bisa dimuat dari ClickHouse saat ini. Coba lagi beberapa saat.'], 503);
        }
    }

    /**
     * Prototipe "DRM 2.1 - Platform PJA Command Center" (HTML statis berisi data sendiri, per 14 Nov 2026),
     * ditampilkan di tab Beranda PJA lewat iframe. Disajikan dari repo lewat route ber-login (bukan public/)
     * karena berisi data internal PJA.
     */
    public function prototipe(): BinaryFileResponse
    {
        $path = base_path('database/data/peta-risiko/prototipe-pja-command-center.html');
        abort_unless(is_file($path), 404, 'File prototipe belum ada di server.');

        return response()->file($path, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** Ringkasan semua cakupan (Site / Group Lokasi / PJA) untuk halaman indeks. */
    public function meta(Request $request): BinaryFileResponse
    {
        abort_unless($this->repo->ada(), 404);

        // File sudah berupa JSON: kirim apa adanya (decode + encode ulang file besar memakan waktu & memori).
        // Versi gzip ditulis saat sync; dipakai bila browser menerimanya dan file gzip tidak lebih tua dari meta.json.
        $headers = ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'private, no-cache', 'Vary' => 'Accept-Encoding'];
        $gz = $this->repo->pathMetaGz();
        if (str_contains((string) $request->header('Accept-Encoding'), 'gzip') && is_file($gz) && filemtime($gz) >= filemtime($this->repo->pathMeta())) {
            return response()->file($gz, $headers + ['Content-Encoding' => 'gzip']);
        }

        return response()->file($this->repo->pathMeta(), $headers);
    }
}
