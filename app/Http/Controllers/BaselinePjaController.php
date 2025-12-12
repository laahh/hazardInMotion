<?php

namespace App\Http\Controllers;

use App\Jobs\ImportBaselinePjaJob;
use App\Models\BaselinePja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BaselinePjaController extends Controller
{
    /**
     * Display the baseline PJA entries along with input forms.
     */
    public function index(Request $request): View
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $query = BaselinePja::query();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('site', 'like', "%{$search}%")
                  ->orWhere('perusahaan', 'like', "%{$search}%")
                  ->orWhere('id_lokasi', 'like', "%{$search}%")
                  ->orWhere('lokasi', 'like', "%{$search}%")
                  ->orWhere('id_pja', 'like', "%{$search}%")
                  ->orWhere('pja', 'like', "%{$search}%")
                  ->orWhere('tipe_pja', 'like', "%{$search}%");
            });
        }

        $entries = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        return view('baseline-pja.index', compact('entries', 'perPage'));
    }

    /**
     * Store a new entry manually submitted via the form.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site' => ['nullable', 'string', 'max:255'],
            'perusahaan' => ['nullable', 'string', 'max:255'],
            'id_lokasi' => ['nullable', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'id_pja' => ['nullable', 'string', 'max:255'],
            'pja' => ['nullable', 'string', 'max:255'],
            'tipe_pja' => ['nullable', 'string', 'max:255'],
        ]);

        BaselinePja::create($validated);

        return redirect()
            ->route('baseline-pja.index')
            ->with('success', 'Data berhasil disimpan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BaselinePja $baselinePja): View
    {
        return view('baseline-pja.edit', compact('baselinePja'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BaselinePja $baselinePja): RedirectResponse
    {
        $validated = $request->validate([
            'site' => ['nullable', 'string', 'max:255'],
            'perusahaan' => ['nullable', 'string', 'max:255'],
            'id_lokasi' => ['nullable', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'id_pja' => ['nullable', 'string', 'max:255'],
            'pja' => ['nullable', 'string', 'max:255'],
            'tipe_pja' => ['nullable', 'string', 'max:255'],
        ]);

        $baselinePja->update($validated);

        return redirect()
            ->route('baseline-pja.index')
            ->with('success', 'Data berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BaselinePja $baselinePja): RedirectResponse
    {
        $baselinePja->delete();

        return redirect()
            ->route('baseline-pja.index')
            ->with('success', 'Data berhasil dihapus.');
    }

    /**
     * Import data from an uploaded Excel file.
     */
    public function import(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            ]);

            $file = $request->file('excel_file');
            
            if (!$file || !$file->isValid()) {
                return redirect()
                    ->route('baseline-pja.index')
                    ->with('error', 'File tidak valid atau gagal diunggah.');
            }

            $uniqueName = uniqid('baseline_pja_', true) . '.' . $file->getClientOriginalExtension();
            $storedPath = $file->storeAs('baseline-pja-imports', $uniqueName);

            if (!$storedPath) {
                return redirect()
                    ->route('baseline-pja.index')
                    ->with('error', 'Gagal menyimpan file. Pastikan folder storage/app/baseline-pja-imports dapat ditulis.');
            }

            // Check queue connection
            $queueConnection = config('queue.default');
            $isSync = $queueConnection === 'sync';
            
            // Check if jobs table exists for database queue
            if ($queueConnection === 'database') {
                try {
                    if (!Schema::hasTable('jobs')) {
                        return redirect()
                            ->route('baseline-pja.index')
                            ->with('error', 'Tabel jobs belum ada. Silakan jalankan: php artisan migrate');
                    }
                } catch (\Exception $e) {
                    // If we can't check, try to dispatch anyway
                }
            }
            
            // Dispatch job to queue
            ImportBaselinePjaJob::dispatch($storedPath)->onQueue('default');

            $message = 'File berhasil diunggah dan sedang diproses di background. Silakan cek beberapa saat lagi.';
            if ($isSync) {
                $message .= ' Catatan: Queue connection menggunakan "sync", pastikan untuk menjalankan queue worker jika ingin memproses di background.';
            }

            return redirect()
                ->route('baseline-pja.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('BaselinePjaController import error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()
                ->route('baseline-pja.index')
                ->with('error', 'Terjadi kesalahan saat mengunggah file: ' . $e->getMessage());
        }
    }
}

