<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\PncMonitoring\PncMonitoringExcelImportRequest;
use App\Http\Requests\PncMonitoring\PncMonitoringIkkRecordRequest;
use App\Models\PncMonitoring\PncMonitoringIkkRecord;
use App\Services\PncMonitoring\PncMonitoringIkkExcelParser;
use App\Services\PncMonitoring\PncMonitoringIkkExcelTemplateService;
use App\Services\PncMonitoring\PncMonitoringIkkUpsertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PncMonitoringIkkRecordController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->string('q')->toString());
        $query = PncMonitoringIkkRecord::query()->orderByDesc('id');
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like): void {
                $sub->where('nomor', 'like', $like)
                    ->orWhere('site', 'like', $like)
                    ->orWhere('perusahaan', 'like', $like)
                    ->orWhere('jenis', 'like', $like)
                    ->orWhere('pekerjaan', 'like', $like);
            });
        }

        return view('pnc-monitoring.ikk-records.index', [
            'rows' => $query->paginate(20)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('pnc-monitoring.ikk-records.form', [
            'mode' => 'create',
            'row' => new PncMonitoringIkkRecord(),
        ]);
    }

    public function store(PncMonitoringIkkRecordRequest $request): RedirectResponse
    {
        $payload = $request->attributesPayload();
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;
        PncMonitoringIkkRecord::query()->create($payload);

        return redirect()
            ->route('pnc-monitoring.ikk-records.index')
            ->with('success', 'Data IKK disimpan.');
    }

    public function edit(PncMonitoringIkkRecord $ikkRecord): View
    {
        return view('pnc-monitoring.ikk-records.form', [
            'mode' => 'edit',
            'row' => $ikkRecord,
        ]);
    }

    public function update(PncMonitoringIkkRecordRequest $request, PncMonitoringIkkRecord $ikkRecord): RedirectResponse
    {
        $payload = $request->attributesPayload();
        $payload['updated_by'] = $request->user()?->id;
        $ikkRecord->fill($payload);
        $ikkRecord->save();

        return redirect()
            ->route('pnc-monitoring.ikk-records.index')
            ->with('success', 'Data IKK diperbarui.');
    }

    public function destroy(PncMonitoringIkkRecord $ikkRecord): RedirectResponse
    {
        $ikkRecord->delete();

        return redirect()
            ->route('pnc-monitoring.ikk-records.index')
            ->with('success', 'Data IKK dihapus.');
    }

    public function excelTemplate(PncMonitoringIkkExcelTemplateService $templates): StreamedResponse
    {
        return $templates->download();
    }

    public function excelImport(
        PncMonitoringExcelImportRequest $request,
        PncMonitoringIkkExcelParser $parser,
        PncMonitoringIkkUpsertService $upsert,
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
            ->route('pnc-monitoring.ikk-records.index')
            ->with('success', "Excel IKK: {$result->created} baru, {$result->updated} diperbarui.")
            ->with('warnings', [...$parsed->warnings, ...$result->warnings]);
    }
}
