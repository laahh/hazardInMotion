<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\PncMonitoring\PncMonitoringExcelImportRequest;
use App\Http\Requests\PncMonitoring\PncMonitoringInventoryToolMasterRequest;
use App\Models\PncMonitoring\PncMonitoringInventoryCategory;
use App\Models\PncMonitoring\PncMonitoringInventoryToolAttribute;
use App\Models\PncMonitoring\PncMonitoringInventoryToolChecklistItem;
use App\Models\PncMonitoring\PncMonitoringInventoryToolFunction;
use App\Models\PncMonitoring\PncMonitoringInventoryToolInspectionMethod;
use App\Models\PncMonitoring\PncMonitoringInventoryToolMaster;
use App\Models\PncMonitoring\PncMonitoringInventoryToolSafetyFeature;
use App\Models\PncMonitoring\PncMonitoringInventoryToolStandard;
use App\Models\PncMonitoring\PncMonitoringInventoryToolUsageRule;
use App\Services\PncMonitoring\PncMonitoringInventoryChecklistExcelService;
use App\Services\PncMonitoring\PncMonitoringInventoryToolMasterDetailExcelService;
use App\Services\PncMonitoring\PncMonitoringInventoryToolMasterExcelExportService;
use App\Services\PncMonitoring\PncMonitoringInventoryToolMasterExcelParser;
use App\Services\PncMonitoring\PncMonitoringInventoryToolMasterExcelTemplateService;
use App\Services\PncMonitoring\PncMonitoringInventoryToolMasterUpsertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PncMonitoringInventoryToolMasterController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->string('q')->toString());
        $categoryId = $request->integer('category_id') ?: null;

        $query = PncMonitoringInventoryToolMaster::query()
            ->with('category')
            ->withCount(['assets', 'checklistItems'])
            ->orderBy('standard_name');

        if ($q !== '') {
            $query->where('standard_name', 'like', '%'.$q.'%');
        }
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        return view('pnc-monitoring.inventory-tool-master.index', [
            'rows' => $query->paginate(20)->withQueryString(),
            'q' => $q,
            'categoryId' => $categoryId,
            'categories' => PncMonitoringInventoryCategory::query()->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        return view('pnc-monitoring.inventory-tool-master.form', [
            'mode' => 'create',
            'row' => new PncMonitoringInventoryToolMaster(),
            'categories' => PncMonitoringInventoryCategory::query()->orderBy('code')->get(),
        ]);
    }

    public function store(PncMonitoringInventoryToolMasterRequest $request): RedirectResponse
    {
        $toolMaster = DB::transaction(function () use ($request): PncMonitoringInventoryToolMaster {
            $toolMaster = PncMonitoringInventoryToolMaster::query()->create($request->corePayload());
            $this->syncChildren($toolMaster, $request);

            return $toolMaster;
        });

        return redirect()
            ->route('pnc-monitoring.inventory-tool-master.edit', $toolMaster)
            ->with('success', 'Jenis alat disimpan.');
    }

    public function edit(PncMonitoringInventoryToolMaster $inventoryToolMaster): View
    {
        $inventoryToolMaster->load([
            'category', 'functions', 'inspectionMethods', 'safetyFeatures',
            'standards', 'checklistItems', 'usageRules', 'attributes',
        ]);

        return view('pnc-monitoring.inventory-tool-master.form', [
            'mode' => 'edit',
            'row' => $inventoryToolMaster,
            'categories' => PncMonitoringInventoryCategory::query()->orderBy('code')->get(),
        ]);
    }

    public function update(PncMonitoringInventoryToolMasterRequest $request, PncMonitoringInventoryToolMaster $inventoryToolMaster): RedirectResponse
    {
        DB::transaction(function () use ($request, $inventoryToolMaster): void {
            $inventoryToolMaster->update($request->corePayload());
            $this->syncChildren($inventoryToolMaster, $request);
        });

        return redirect()
            ->route('pnc-monitoring.inventory-tool-master.edit', $inventoryToolMaster)
            ->with('success', 'Jenis alat diperbarui.');
    }

    public function destroy(PncMonitoringInventoryToolMaster $inventoryToolMaster): RedirectResponse
    {
        if ($inventoryToolMaster->assets()->exists()) {
            return back()->withErrors(['tool_master' => 'Jenis alat tidak bisa dihapus karena masih punya unit aset terdaftar.']);
        }

        $inventoryToolMaster->delete();

        return redirect()
            ->route('pnc-monitoring.inventory-tool-master.index')
            ->with('success', 'Jenis alat dihapus.');
    }

    public function excelTemplate(PncMonitoringInventoryToolMasterExcelTemplateService $templates): StreamedResponse
    {
        return $templates->download();
    }

    public function export(Request $request, PncMonitoringInventoryToolMasterExcelExportService $exporter): StreamedResponse
    {
        $q = trim((string) $request->string('q')->toString());
        $categoryId = $request->integer('category_id') ?: null;

        $query = PncMonitoringInventoryToolMaster::query()
            ->with(['category', 'functions', 'inspectionMethods', 'safetyFeatures', 'standards', 'checklistItems', 'usageRules', 'attributes'])
            ->orderBy('standard_name');
        if ($q !== '') {
            $query->where('standard_name', 'like', '%'.$q.'%');
        }
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        return $exporter->download($query->get());
    }

    public function excelImport(
        PncMonitoringExcelImportRequest $request,
        PncMonitoringInventoryToolMasterExcelParser $parser,
        PncMonitoringInventoryToolMasterUpsertService $upsert,
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
        if ($parsed->coreRows === [] && $parsed->detailRows === []) {
            return back()->withErrors(['file' => 'Tidak ada baris yang bisa diimpor.'])->withInput();
        }

        $result = $upsert->upsert($parsed);

        $detailSummary = collect($result->detailCounts)
            ->map(fn (int $count, string $section) => PncMonitoringInventoryToolMasterExcelParser::DETAIL_SECTIONS[$section]['sheetTitle'].": {$count}")
            ->implode(', ');

        $message = "Excel Katalog Alat: {$result->coreCreated} baru, {$result->coreUpdated} diperbarui.";
        if ($detailSummary !== '') {
            $message .= " Detail — {$detailSummary}.";
        }

        return redirect()
            ->route('pnc-monitoring.inventory-tool-master.index')
            ->with('success', $message)
            ->with('warnings', [...$parsed->warnings, ...$result->warnings]);
    }

    public function checklistExport(PncMonitoringInventoryToolMaster $inventoryToolMaster, PncMonitoringInventoryChecklistExcelService $service): StreamedResponse
    {
        $inventoryToolMaster->load('checklistItems');

        return $service->download($inventoryToolMaster);
    }

    public function checklistImport(
        PncMonitoringExcelImportRequest $request,
        PncMonitoringInventoryToolMaster $inventoryToolMaster,
        PncMonitoringInventoryChecklistExcelService $service,
    ): RedirectResponse {
        $file = $request->file('file');
        $path = $file?->getRealPath();
        if (! is_string($path) || $path === '') {
            return back()->withErrors(['file' => 'File tidak dapat dibaca.']);
        }

        $result = $service->importReplace($inventoryToolMaster, $path);
        if ($result->hasErrors()) {
            return back()->withErrors(['file' => $result->errors]);
        }

        return redirect()
            ->route('pnc-monitoring.inventory-tool-master.edit', $inventoryToolMaster)
            ->with('success', "Checklist diimpor: {$result->created} baris (menggantikan checklist sebelumnya).");
    }

    public function detailExport(
        PncMonitoringInventoryToolMaster $inventoryToolMaster,
        string $section,
        PncMonitoringInventoryToolMasterDetailExcelService $service,
    ): StreamedResponse {
        abort_unless(in_array($section, PncMonitoringInventoryToolMasterDetailExcelService::sectionKeys(), true), 404);

        return $service->download($section, $inventoryToolMaster);
    }

    public function detailImport(
        PncMonitoringExcelImportRequest $request,
        PncMonitoringInventoryToolMaster $inventoryToolMaster,
        string $section,
        PncMonitoringInventoryToolMasterDetailExcelService $service,
    ): RedirectResponse {
        abort_unless(in_array($section, PncMonitoringInventoryToolMasterDetailExcelService::sectionKeys(), true), 404);

        $file = $request->file('file');
        $path = $file?->getRealPath();
        if (! is_string($path) || $path === '') {
            return back()->withErrors(['file' => 'File tidak dapat dibaca.']);
        }

        $result = $service->importReplace($section, $inventoryToolMaster, $path);
        if ($result->hasErrors()) {
            return back()->withErrors(['file' => $result->errors]);
        }

        return redirect()
            ->route('pnc-monitoring.inventory-tool-master.edit', $inventoryToolMaster)
            ->with('success', "Data diimpor: {$result->created} baris (menggantikan data sebelumnya).")
            ->with('warnings', $result->warnings);
    }

    private function syncChildren(PncMonitoringInventoryToolMaster $toolMaster, PncMonitoringInventoryToolMasterRequest $request): void
    {
        $toolMasterId = $toolMaster->tool_master_id;

        PncMonitoringInventoryToolFunction::query()->where('tool_master_id', $toolMasterId)->delete();
        $this->insertMany(PncMonitoringInventoryToolFunction::class, $toolMasterId, $request->functionsPayload());

        PncMonitoringInventoryToolInspectionMethod::query()->where('tool_master_id', $toolMasterId)->delete();
        $this->insertMany(PncMonitoringInventoryToolInspectionMethod::class, $toolMasterId, $request->inspectionMethodsPayload());

        PncMonitoringInventoryToolSafetyFeature::query()->where('tool_master_id', $toolMasterId)->delete();
        $this->insertMany(PncMonitoringInventoryToolSafetyFeature::class, $toolMasterId, $request->safetyFeaturesPayload());

        PncMonitoringInventoryToolStandard::query()->where('tool_master_id', $toolMasterId)->delete();
        $this->insertMany(PncMonitoringInventoryToolStandard::class, $toolMasterId, $request->standardsPayload());

        PncMonitoringInventoryToolChecklistItem::query()->where('tool_master_id', $toolMasterId)->delete();
        $this->insertMany(PncMonitoringInventoryToolChecklistItem::class, $toolMasterId, $request->checklistItemsPayload());

        PncMonitoringInventoryToolUsageRule::query()->where('tool_master_id', $toolMasterId)->delete();
        $this->insertMany(PncMonitoringInventoryToolUsageRule::class, $toolMasterId, $request->usageRulesPayload());

        PncMonitoringInventoryToolAttribute::query()->where('tool_master_id', $toolMasterId)->delete();
        $this->insertMany(PncMonitoringInventoryToolAttribute::class, $toolMasterId, $request->attributesPayload());
    }

    /**
     * @param  class-string  $modelClass
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertMany(string $modelClass, int $toolMasterId, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $modelClass::query()->insert(array_map(
            static fn (array $row): array => ['tool_master_id' => $toolMasterId, ...$row],
            $rows,
        ));
    }
}
