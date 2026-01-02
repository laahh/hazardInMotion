<?php

namespace App\Http\Controllers\ValidasiTbc;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ValidasiTbcController extends Controller
{
    /**
     * Halaman utama validasi TBC per week & per site.
     *
     * NOTE:
     * - Di sini kita hanya siapkan kerangka modul (route, controller, view).
     * - Query ke tabel hazard utama bisa diisi sesuai struktur database-mu.
     */
    public function index(Request $request)
    {
        // Week start: Senin minggu yang dipilih (default: Senin minggu ini)
        $weekStart = $request->input('week_start');
        if ($weekStart) {
            $weekStart = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        } else {
            $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        }
        $weekEnd = (clone $weekStart)->addWeek();

        // Site yang dipilih (nanti bisa diisi dari list site di DB)
        $site = $request->input('site');

        // Ambil data hazard untuk divalidasi (kerangka, isi query sesuai kebutuhan)
        $hazards = $this->getHazardsForValidation(
            site: $site,
            weekStart: $weekStart,
            weekEnd: $weekEnd
        );

        return view('validasi_tbc.index', [
            'hazards' => $hazards,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'selectedSite' => $site,
            // Placeholder, nanti bisa diganti dari DB (contoh: config site)
            'availableSites' => [
                'SMO',
                'LMO',
                'BSL',
                'BIN',
                'SNA',
                'DEL',
            ],
        ]);
    }

    /**
     * Simpan hasil validasi TBC (hanya yang Valid).
     *
     * Di sini hanya contoh struktur; penyimpanan sebenarnya (ke tabel hazard_tbc,
     * progress, dll.) bisa diisi sesuai rancangan migrasi yang kamu buat.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'week_start' => ['required', 'date'],
            'site' => ['required', 'string', 'max:255'],
            'hazards' => ['nullable', 'array'],
            'hazards.*.tasklist' => ['required_with:hazards', 'string', 'max:255'],
            'hazards.*.gr_status' => ['nullable', 'string', 'max:255'],
            'hazards.*.sub_ketidaksesuaian' => ['nullable', 'string', 'max:255'],
            'hazards.*.is_valid' => ['nullable', 'boolean'],
        ]);

        $userId = Auth::id();

        // Contoh: hanya simpan hazard yang dicentang Valid
        $validatedHazards = collect($data['hazards'] ?? [])
            ->filter(fn ($row) => !empty($row['is_valid']));

        // TODO:
        // - Simpan ke tabel hazard_tbc (hanya data valid)
        // - Update pointer progress untuk kombinasi (site, week, user)
        // Implementasi detail disesuaikan dengan struktur tabel yang akan kamu pakai.

        return redirect()
            ->route('validasi-tbc.index', [
                'week_start' => $data['week_start'],
                'site' => $data['site'],
            ])
            ->with('success', 'Validasi TBC berhasil disimpan (kerangka modul).');
    }

    /**
     * Kerangka fungsi untuk mengambil data hazard yang perlu divalidasi.
     *
     * Saat ini hanya mengembalikan Collection kosong + contoh struktur data.
     * Kamu bisa mengganti isi fungsi ini dengan query ke database hazard utama.
     */
    protected function getHazardsForValidation(
        ?string $site,
        Carbon $weekStart,
        Carbon $weekEnd
    ): Collection {
        // TODO:
        // - Query ke tabel hazard utama berdasarkan:
        //   - tanggal_pembuatan antara $weekStart dan $weekEnd
        //   - filter site (jika $site tidak null)
        // - Gabungkan dengan tabel progress (jika sudah dibuat) untuk hanya ambil yang "belum disapu"
        //
        // Contoh struktur data yang diharapkan di view:
        // [
        //   [
        //     'task' => '7640801',
        //     'tanggal_pembuatan' => '2025-12-01 00:11',
        //     'site' => 'SMO',
        //     'lokasi' => 'Pit C2H',
        //     'kategori' => 'Kondisi Tidak Aman',
        //     'deskripsi' => 'Jalan masuk disposal ...',
        //   ],
        //   ...
        // ]

        return collect([]);
    }
}


