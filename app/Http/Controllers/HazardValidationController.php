<?php

namespace App\Http\Controllers;

use App\Jobs\ImportHazardValidationJob;
use App\Models\HazardValidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HazardValidationController extends Controller
{
    /**
     * Display the hazard validation entries along with input forms.
     */
    public function index(Request $request): View
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $query = HazardValidation::query();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('validator', 'like', "%{$search}%")
                  ->orWhere('tasklist', 'like', "%{$search}%")
                  ->orWhere('tobe_concerned_hazard', 'like', "%{$search}%")
                  ->orWhere('gr', 'like', "%{$search}%")
                  ->orWhere('catatan', 'like', "%{$search}%");
            });
        }

        $entries = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        return view('hazard-validation.index', compact('entries', 'perPage'));
    }

    /**
     * Store a new entry manually submitted via the form.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'validator' => ['nullable', 'string', 'max:255'],
            'tasklist' => ['required', 'string', 'max:255'],
            'tobe_concerned_hazard' => ['nullable', 'string', 'max:255'],
            'gr' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
        ]);

        HazardValidation::create($validated);

        return redirect()
            ->route('hazard-validation.index')
            ->with('success', 'Data berhasil disimpan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(HazardValidation $hazardValidation): View
    {
        return view('hazard-validation.edit', compact('hazardValidation'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HazardValidation $hazardValidation): RedirectResponse
    {
        $validated = $request->validate([
            'validator' => ['nullable', 'string', 'max:255'],
            'tasklist' => ['required', 'string', 'max:255'],
            'tobe_concerned_hazard' => ['nullable', 'string', 'max:255'],
            'gr' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
        ]);

        $hazardValidation->update($validated);

        return redirect()
            ->route('hazard-validation.index')
            ->with('success', 'Data berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HazardValidation $hazardValidation): RedirectResponse
    {
        $hazardValidation->delete();

        return redirect()
            ->route('hazard-validation.index')
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
                    ->route('hazard-validation.index')
                    ->with('error', 'File tidak valid atau gagal diunggah.');
            }

            $uniqueName = uniqid('hazard_validation_', true) . '.' . $file->getClientOriginalExtension();
            $storedPath = $file->storeAs('hazard-validation-imports', $uniqueName);

            if (!$storedPath) {
                return redirect()
                    ->route('hazard-validation.index')
                    ->with('error', 'Gagal menyimpan file. Pastikan folder storage/app/hazard-validation-imports dapat ditulis.');
            }

            // Check queue connection
            $queueConnection = config('queue.default');
            $isSync = $queueConnection === 'sync';
            
            // Check if jobs table exists for database queue
            if ($queueConnection === 'database') {
                try {
                    if (! \Illuminate\Support\Facades\Schema::hasTable('jobs')) {
                        return redirect()
                            ->route('hazard-validation.index')
                            ->with('error', 'Tabel jobs belum ada. Silakan jalankan: php artisan migrate');
                    }
                } catch (\Exception $e) {
                    // If we can't check, try to dispatch anyway
                }
            }
            
            // Dispatch job to queue
            ImportHazardValidationJob::dispatch($storedPath)->onQueue('default');

            $message = 'File berhasil diunggah dan sedang diproses di background. Silakan cek beberapa saat lagi.';
            if ($isSync) {
                $message .= ' Catatan: Queue connection menggunakan "sync", pastikan untuk menjalankan queue worker jika ingin memproses di background.';
            }

            return redirect()
                ->route('hazard-validation.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            \Log::error('HazardValidationController import error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()
                ->route('hazard-validation.index')
                ->with('error', 'Terjadi kesalahan saat mengunggah file: ' . $e->getMessage());
        }
    }
}

