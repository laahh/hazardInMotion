<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\PncMonitoring\PncMonitoringExcelImportRequest;
use App\Http\Requests\PncMonitoring\PncMonitoringInventoryToolAssetRequest;
use App\Models\PncMonitoring\PncMonitoringInventoryCompany;
use App\Models\PncMonitoring\PncMonitoringInventoryToolAsset;
use App\Models\PncMonitoring\PncMonitoringInventoryToolMaster;
use App\Services\PncMonitoring\PncMonitoringInventoryToolAssetExcelExportService;
use App\Services\PncMonitoring\PncMonitoringInventoryToolAssetExcelParser;
use App\Services\PncMonitoring\PncMonitoringInventoryToolAssetExcelTemplateService;
use App\Services\PncMonitoring\PncMonitoringInventoryToolAssetUpsertService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PncMonitoringInventoryToolAssetController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->string('q')->toString());
        $status = trim((string) $request->string('status')->toString());

        return view('pnc-monitoring.inventory-tool-assets.index', [
            'rows' => $this->filteredQuery($q, $status)->paginate(20)->withQueryString(),
            'q' => $q,
            'status' => $status,
            'statuses' => PncMonitoringInventoryToolAsset::STATUSES,
        ]);
    }

    public function export(Request $request, PncMonitoringInventoryToolAssetExcelExportService $exporter): StreamedResponse
    {
        $q = trim((string) $request->string('q')->toString());
        $status = trim((string) $request->string('status')->toString());

        return $exporter->download($this->filteredQuery($q, $status)->get());
    }

    private function filteredQuery(string $q, string $status): Builder
    {
        $query = PncMonitoringInventoryToolAsset::query()
            ->with(['toolMaster.category', 'ownerCompany'])
            ->orderByDesc('asset_id');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like): void {
                $sub->where('inventory_id', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('serial_number', 'like', $like)
                    ->orWhere('location_detail', 'like', $like)
                    ->orWhereHas('toolMaster', fn ($tm) => $tm->where('standard_name', 'like', $like));
            });
        }
        if ($status !== '') {
            $query->where('status_availability', $status);
        }

        return $query;
    }

    public function create(): View
    {
        return view('pnc-monitoring.inventory-tool-assets.form', [
            'mode' => 'create',
            'row' => new PncMonitoringInventoryToolAsset(),
            'toolMasters' => PncMonitoringInventoryToolMaster::query()->orderBy('standard_name')->get(),
            'companies' => PncMonitoringInventoryCompany::query()->orderBy('name')->get(),
            'statuses' => PncMonitoringInventoryToolAsset::STATUSES,
            'conditions' => PncMonitoringInventoryToolAsset::CONDITIONS,
        ]);
    }

    public function store(PncMonitoringInventoryToolAssetRequest $request): RedirectResponse
    {
        PncMonitoringInventoryToolAsset::query()->create($request->validated());

        return redirect()
            ->route('pnc-monitoring.inventory-tool-assets.index')
            ->with('success', 'Unit aset disimpan.');
    }

    public function edit(PncMonitoringInventoryToolAsset $inventoryToolAsset): View
    {
        return view('pnc-monitoring.inventory-tool-assets.form', [
            'mode' => 'edit',
            'row' => $inventoryToolAsset,
            'toolMasters' => PncMonitoringInventoryToolMaster::query()->orderBy('standard_name')->get(),
            'companies' => PncMonitoringInventoryCompany::query()->orderBy('name')->get(),
            'statuses' => PncMonitoringInventoryToolAsset::STATUSES,
            'conditions' => PncMonitoringInventoryToolAsset::CONDITIONS,
        ]);
    }

    public function update(PncMonitoringInventoryToolAssetRequest $request, PncMonitoringInventoryToolAsset $inventoryToolAsset): RedirectResponse
    {
        $inventoryToolAsset->update($request->validated());

        return redirect()
            ->route('pnc-monitoring.inventory-tool-assets.index')
            ->with('success', 'Unit aset diperbarui.');
    }

    public function destroy(PncMonitoringInventoryToolAsset $inventoryToolAsset): RedirectResponse
    {
        $inventoryToolAsset->delete();

        return redirect()
            ->route('pnc-monitoring.inventory-tool-assets.index')
            ->with('success', 'Unit aset dihapus.');
    }

    public function excelTemplate(PncMonitoringInventoryToolAssetExcelTemplateService $templates): StreamedResponse
    {
        return $templates->download();
    }

    public function excelImport(
        PncMonitoringExcelImportRequest $request,
        PncMonitoringInventoryToolAssetExcelParser $parser,
        PncMonitoringInventoryToolAssetUpsertService $upsert,
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

        $result = $upsert->upsert($parsed->rows);

        return redirect()
            ->route('pnc-monitoring.inventory-tool-assets.index')
            ->with('success', "Excel Unit Aset: {$result->created} baru, {$result->updated} diperbarui.")
            ->with('warnings', [...$parsed->warnings, ...$result->warnings]);
    }
}
