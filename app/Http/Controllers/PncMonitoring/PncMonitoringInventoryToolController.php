<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\PncMonitoring\PncMonitoringExcelImportRequest;
use App\Http\Requests\PncMonitoring\PncMonitoringInventoryToolRequest;
use App\Models\PncMonitoring\PncMonitoringInventoryTool;
use App\Services\PncMonitoring\PncMonitoringInventoryExcelParser;
use App\Services\PncMonitoring\PncMonitoringInventoryExcelTemplateService;
use App\Services\PncMonitoring\PncMonitoringInventoryUpsertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PncMonitoringInventoryToolController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->string('q')->toString());
        $category = trim((string) $request->string('category')->toString());
        $status = trim((string) $request->string('status')->toString());

        $query = PncMonitoringInventoryTool::query()->orderByDesc('id');
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like): void {
                $sub->where('nama_alat', 'like', $like)
                    ->orWhere('asset_id', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('serial_number', 'like', $like)
                    ->orWhere('site', 'like', $like);
            });
        }
        if ($category !== '') {
            $query->where('category', $category);
        }
        if ($status !== '') {
            $query->where('status_ketersediaan', $status);
        }

        return view('pnc-monitoring.inventory-tools.index', [
            'rows' => $query->paginate(20)->withQueryString(),
            'q' => $q,
            'category' => $category,
            'status' => $status,
            'categories' => PncMonitoringInventoryTool::CATEGORIES,
            'statuses' => PncMonitoringInventoryTool::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('pnc-monitoring.inventory-tools.form', [
            'mode' => 'create',
            'row' => new PncMonitoringInventoryTool(),
            'categories' => PncMonitoringInventoryTool::CATEGORIES,
            'statuses' => PncMonitoringInventoryTool::STATUSES,
            'conditions' => PncMonitoringInventoryTool::CONDITIONS,
        ]);
    }

    public function store(PncMonitoringInventoryToolRequest $request): RedirectResponse
    {
        $payload = $request->attributesPayload();
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;
        PncMonitoringInventoryTool::query()->create($payload);

        return redirect()
            ->route('pnc-monitoring.inventory-tools.index')
            ->with('success', 'Data Inventory Tools disimpan.');
    }

    public function edit(PncMonitoringInventoryTool $inventoryTool): View
    {
        return view('pnc-monitoring.inventory-tools.form', [
            'mode' => 'edit',
            'row' => $inventoryTool,
            'categories' => PncMonitoringInventoryTool::CATEGORIES,
            'statuses' => PncMonitoringInventoryTool::STATUSES,
            'conditions' => PncMonitoringInventoryTool::CONDITIONS,
        ]);
    }

    public function update(PncMonitoringInventoryToolRequest $request, PncMonitoringInventoryTool $inventoryTool): RedirectResponse
    {
        $payload = $request->attributesPayload();
        $payload['updated_by'] = $request->user()?->id;
        $inventoryTool->fill($payload);
        $inventoryTool->save();

        return redirect()
            ->route('pnc-monitoring.inventory-tools.index')
            ->with('success', 'Data Inventory Tools diperbarui.');
    }

    public function destroy(PncMonitoringInventoryTool $inventoryTool): RedirectResponse
    {
        $inventoryTool->delete();

        return redirect()
            ->route('pnc-monitoring.inventory-tools.index')
            ->with('success', 'Data Inventory Tools dihapus.');
    }

    public function excelTemplate(PncMonitoringInventoryExcelTemplateService $templates): StreamedResponse
    {
        return $templates->download();
    }

    public function excelImport(
        PncMonitoringExcelImportRequest $request,
        PncMonitoringInventoryExcelParser $parser,
        PncMonitoringInventoryUpsertService $upsert,
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
            ->route('pnc-monitoring.inventory-tools.index')
            ->with('success', "Excel Inventory Tools: {$result->created} baru, {$result->updated} diperbarui.")
            ->with('warnings', [...$parsed->warnings, ...$result->warnings]);
    }
}
