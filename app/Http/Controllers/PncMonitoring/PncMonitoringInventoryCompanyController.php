<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\PncMonitoring\PncMonitoringInventoryCompanyRequest;
use App\Models\PncMonitoring\PncMonitoringInventoryCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class PncMonitoringInventoryCompanyController extends Controller
{
    public function index(): View
    {
        return view('pnc-monitoring.inventory-companies.index', [
            'rows' => PncMonitoringInventoryCompany::query()->withCount('assets')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('pnc-monitoring.inventory-companies.form', [
            'mode' => 'create',
            'row' => new PncMonitoringInventoryCompany(),
        ]);
    }

    public function store(PncMonitoringInventoryCompanyRequest $request): RedirectResponse
    {
        PncMonitoringInventoryCompany::query()->create($request->validated());

        return redirect()
            ->route('pnc-monitoring.inventory-companies.index')
            ->with('success', 'Perusahaan disimpan.');
    }

    public function edit(PncMonitoringInventoryCompany $inventoryCompany): View
    {
        return view('pnc-monitoring.inventory-companies.form', [
            'mode' => 'edit',
            'row' => $inventoryCompany,
        ]);
    }

    public function update(PncMonitoringInventoryCompanyRequest $request, PncMonitoringInventoryCompany $inventoryCompany): RedirectResponse
    {
        $inventoryCompany->update($request->validated());

        return redirect()
            ->route('pnc-monitoring.inventory-companies.index')
            ->with('success', 'Perusahaan diperbarui.');
    }

    public function destroy(PncMonitoringInventoryCompany $inventoryCompany): RedirectResponse
    {
        if ($inventoryCompany->assets()->exists()) {
            return back()->withErrors(['company' => 'Perusahaan tidak bisa dihapus karena masih dipakai aset.']);
        }

        $inventoryCompany->delete();

        return redirect()
            ->route('pnc-monitoring.inventory-companies.index')
            ->with('success', 'Perusahaan dihapus.');
    }
}
