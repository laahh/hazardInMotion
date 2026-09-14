<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\PncMonitoring\PncMonitoringCommissioningRequest;
use App\Http\Requests\PncMonitoring\PncMonitoringExcelImportRequest;
use App\Models\PncMonitoring\PncMonitoringCommissioning;
use App\Services\PncMonitoring\PncMonitoringCommissioningExcelParser;
use App\Services\PncMonitoring\PncMonitoringCommissioningExcelTemplateService;
use App\Services\PncMonitoring\PncMonitoringCommissioningUpsertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PncMonitoringCommissioningController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->string('q')->toString());
        $query = PncMonitoringCommissioning::query()->orderByDesc('id');
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like): void {
                $sub->where('no_register_spip', 'like', $like)
                    ->orWhere('site', 'like', $like)
                    ->orWhere('nama_pengawas_teknis', 'like', $like)
                    ->orWhere('pemilik_spip', 'like', $like)
                    ->orWhere('detail_jenis_spip', 'like', $like);
            });
        }

        return view('pnc-monitoring.commissionings.index', [
            'rows' => $query->paginate(20)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('pnc-monitoring.commissionings.form', [
            'mode' => 'create',
            'row' => new PncMonitoringCommissioning(),
        ]);
    }

    public function store(PncMonitoringCommissioningRequest $request): RedirectResponse
    {
        $payload = $request->attributesPayload();
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;
        PncMonitoringCommissioning::query()->create($payload);

        return redirect()
            ->route('pnc-monitoring.commissionings.index')
            ->with('success', 'Data Commissioning disimpan.');
    }

    public function edit(PncMonitoringCommissioning $commissioning): View
    {
        return view('pnc-monitoring.commissionings.form', [
            'mode' => 'edit',
            'row' => $commissioning,
        ]);
    }

    public function update(PncMonitoringCommissioningRequest $request, PncMonitoringCommissioning $commissioning): RedirectResponse
    {
        $payload = $request->attributesPayload();
        $payload['updated_by'] = $request->user()?->id;
        $commissioning->fill($payload);
        $commissioning->save();

        return redirect()
            ->route('pnc-monitoring.commissionings.index')
            ->with('success', 'Data Commissioning diperbarui.');
    }

    public function destroy(PncMonitoringCommissioning $commissioning): RedirectResponse
    {
        $commissioning->delete();

        return redirect()
            ->route('pnc-monitoring.commissionings.index')
            ->with('success', 'Data Commissioning dihapus.');
    }

    public function excelTemplate(PncMonitoringCommissioningExcelTemplateService $templates): StreamedResponse
    {
        return $templates->download();
    }

    public function excelImport(
        PncMonitoringExcelImportRequest $request,
        PncMonitoringCommissioningExcelParser $parser,
        PncMonitoringCommissioningUpsertService $upsert,
    ): RedirectResponse {
        $file = $request->file('file');
        $path = $file?->getRealPath();
        if (! is_string($path) || $path === '') {
            return back()->withErrors(['file' => 'File tidak dapat dibaca.']);
        }
        $parsed = $parser->parse($path);
        if ($parsed->hasErrors()) {
            return back()->withErrors(['file' => $parsed->errors])->withInput();
        }
        if ($parsed->rows === []) {
            return back()->withErrors(['file' => 'Tidak ada baris yang bisa diimpor.'])->withInput();
        }

        $result = $upsert->upsert($parsed->rows, $request->user()?->id);
        if ($result->hasErrors()) {
            return back()->withErrors(['file' => $result->errors])->withInput();
        }

        return redirect()
            ->route('pnc-monitoring.commissionings.index')
            ->with('success', "Excel Commissioning: {$result->created} baru, {$result->updated} diperbarui.")
            ->with('warnings', [...$parsed->warnings, ...$result->warnings]);
    }
}
