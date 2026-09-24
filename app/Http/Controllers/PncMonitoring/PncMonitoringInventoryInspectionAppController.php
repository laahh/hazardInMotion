<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use App\Models\PncMonitoring\PncMonitoringInventoryCategory;
use App\Models\PncMonitoring\PncMonitoringInventoryToolInspectionRecord;
use App\Models\PncMonitoring\PncMonitoringInventoryToolMaster;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "App" inspeksi alat — halaman terpisah, mobile-first, untuk teknisi lapangan:
 * scan/pilih alat, lihat panduan & checklist, lihat riwayat inspeksi, mulai inspeksi baru.
 * TAHAP INI: UI/UX saja — data dibaca dari Katalog Alat/Unit Aset yang sudah ada,
 * tapi aksi "Scan" & "Simpan Inspeksi" belum tersambung ke backend.
 */
final class PncMonitoringInventoryInspectionAppController extends Controller
{
    public function home(Request $request): View
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

        return view('pnc-monitoring.inspection-app.home', [
            'tools' => $query->limit(30)->get(),
            'categories' => PncMonitoringInventoryCategory::query()->orderBy('code')->get(),
            'q' => $q,
            'categoryId' => $categoryId,
            'totalTools' => PncMonitoringInventoryToolMaster::count(),
        ]);
    }

    public function scan(): View
    {
        return view('pnc-monitoring.inspection-app.scan', [
            'recentTools' => PncMonitoringInventoryToolMaster::query()
                ->with('category')
                ->latest('tool_master_id')
                ->limit(5)
                ->get(),
        ]);
    }

    public function history(): View
    {
        $records = PncMonitoringInventoryToolInspectionRecord::query()
            ->with(['asset.toolMaster', 'inspector'])
            ->latest('inspection_date')
            ->limit(30)
            ->get();

        return view('pnc-monitoring.inspection-app.history', [
            'records' => $records,
        ]);
    }

    public function showTool(PncMonitoringInventoryToolMaster $toolMaster): View
    {
        $toolMaster->load([
            'category', 'functions', 'inspectionMethods', 'safetyFeatures',
            'standards', 'checklistItems', 'usageRules', 'attributes', 'assets',
        ]);

        $inspectionRecords = PncMonitoringInventoryToolInspectionRecord::query()
            ->whereIn('asset_id', $toolMaster->assets->pluck('asset_id'))
            ->with('inspector')
            ->latest('inspection_date')
            ->limit(10)
            ->get();

        return view('pnc-monitoring.inspection-app.tool-show', [
            'tool' => $toolMaster,
            'inspectionRecords' => $inspectionRecords,
        ]);
    }

    public function inspect(PncMonitoringInventoryToolMaster $toolMaster): View
    {
        $toolMaster->load(['category', 'assets', 'checklistItems']);

        return view('pnc-monitoring.inspection-app.tool-inspect', [
            'tool' => $toolMaster,
        ]);
    }
}
