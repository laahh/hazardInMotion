<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\PncMonitoring\PncMonitoringInventoryCategoryRequest;
use App\Models\PncMonitoring\PncMonitoringInventoryCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class PncMonitoringInventoryCategoryController extends Controller
{
    public function index(): View
    {
        return view('pnc-monitoring.inventory-categories.index', [
            'rows' => PncMonitoringInventoryCategory::query()->withCount('toolMasters')->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        return view('pnc-monitoring.inventory-categories.form', [
            'mode' => 'create',
            'row' => new PncMonitoringInventoryCategory(),
        ]);
    }

    public function store(PncMonitoringInventoryCategoryRequest $request): RedirectResponse
    {
        PncMonitoringInventoryCategory::query()->create($request->validated());

        return redirect()
            ->route('pnc-monitoring.inventory-categories.index')
            ->with('success', 'Kategori disimpan.');
    }

    public function edit(PncMonitoringInventoryCategory $inventoryCategory): View
    {
        return view('pnc-monitoring.inventory-categories.form', [
            'mode' => 'edit',
            'row' => $inventoryCategory,
        ]);
    }

    public function update(PncMonitoringInventoryCategoryRequest $request, PncMonitoringInventoryCategory $inventoryCategory): RedirectResponse
    {
        $inventoryCategory->update($request->validated());

        return redirect()
            ->route('pnc-monitoring.inventory-categories.index')
            ->with('success', 'Kategori diperbarui.');
    }

    public function destroy(PncMonitoringInventoryCategory $inventoryCategory): RedirectResponse
    {
        if ($inventoryCategory->toolMasters()->exists()) {
            return back()->withErrors(['category' => 'Kategori tidak bisa dihapus karena masih dipakai jenis alat.']);
        }

        $inventoryCategory->delete();

        return redirect()
            ->route('pnc-monitoring.inventory-categories.index')
            ->with('success', 'Kategori dihapus.');
    }
}
