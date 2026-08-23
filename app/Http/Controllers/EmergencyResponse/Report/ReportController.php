<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Report;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\Incident\Incident;
use App\Models\EmergencyResponse\Incident\IncidentEquipmentUsage;
use App\Models\EmergencyResponse\Inspection\Inspection;
use App\Models\EmergencyResponse\Inspection\InspectionResult;
use App\Models\EmergencyResponse\Maintenance\WorkOrder;
use App\Models\EmergencyResponse\Manpower\Attendance;
use App\Models\EmergencyResponse\Manpower\EmployeeCertification;
use App\Models\EmergencyResponse\Manpower\EmployeeTraining;
use App\Models\EmergencyResponse\MasterData\IncidentType;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('EmergencyResponse.report.index');
    }

    public function equipment(Request $request): View
    {
        $equipment = EmergencyEquipment::query()
            ->with(['category', 'site'])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->query('site_id')))
            ->when($request->filled('condition'), fn ($q) => $q->where('condition', $request->query('condition')))
            ->when($request->boolean('only_expired'), fn ($q) => $q->whereNotNull('expires_at')->where('expires_at', '<', today()))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('EmergencyResponse.report.equipment', [
            'equipment' => $equipment,
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'conditions' => EmergencyEquipment::CONDITIONS,
        ]);
    }

    public function equipmentExport(Request $request): Response
    {
        $spreadsheet = SpreadsheetExporter::createSheetWithHeaders(['Kode', 'Nama', 'Kategori', 'Site', 'Kondisi', 'Kedaluwarsa']);
        $sheet = $spreadsheet->getActiveSheet();

        $equipment = EmergencyEquipment::query()
            ->with(['category', 'site'])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->query('site_id')))
            ->when($request->filled('condition'), fn ($q) => $q->where('condition', $request->query('condition')))
            ->when($request->boolean('only_expired'), fn ($q) => $q->whereNotNull('expires_at')->where('expires_at', '<', today()))
            ->orderBy('name')->get();

        foreach ($equipment as $i => $item) {
            $sheet->fromArray([$item->code, $item->name, $item->category->name ?? '-', $item->site->name ?? '-', $item->conditionLabel(), optional($item->expires_at)->format('Y-m-d')], null, 'A'.($i + 2));
        }

        SpreadsheetExporter::download($spreadsheet, 'laporan-equipment-'.now()->format('Ymd-His').'.xlsx');
    }

    public function inspections(Request $request): View
    {
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->query('date_from')) : now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : now()->endOfMonth();

        $inspections = Inspection::query()
            ->with(['target', 'site', 'inspector'])
            ->whereBetween('inspected_at', [$dateFrom, $dateTo])
            ->when($request->boolean('only_non_compliant'), function ($q) {
                $q->whereHas('results', fn ($r) => $r->where('answer_value', 'tidak_sesuai'));
            })
            ->orderByDesc('inspected_at')
            ->paginate(20)
            ->withQueryString();

        return view('EmergencyResponse.report.inspections', ['inspections' => $inspections, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo]);
    }

    public function incidents(Request $request): View
    {
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->query('date_from')) : now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : now()->endOfMonth();

        $query = Incident::query()
            ->with(['incidentType', 'severityLevel', 'priorityLevel', 'site'])
            ->whereBetween('reported_at', [$dateFrom, $dateTo])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->query('site_id')))
            ->when($request->filled('incident_type_id'), fn ($q) => $q->where('incident_type_id', $request->query('incident_type_id')));

        $incidents = (clone $query)->orderByDesc('reported_at')->paginate(20)->withQueryString();

        $withResponseTime = (clone $query)->whereNotNull('arrived_at')->get();
        $avgResponseTime = $withResponseTime->isNotEmpty() ? round($withResponseTime->avg(fn ($i) => $i->responseTimeMinutes()), 1) : null;
        $byIncidentType = (clone $query)->with('incidentType')->get()->groupBy(fn ($i) => $i->incidentType->name ?? '-')->map->count();
        $bySite = (clone $query)->with('site')->get()->groupBy(fn ($i) => $i->site->name ?? '-')->map->count();
        $bySeverity = (clone $query)->with('severityLevel')->get()->groupBy(fn ($i) => $i->severityLevel->name ?? '-')->map->count();

        return view('EmergencyResponse.report.incidents', [
            'incidents' => $incidents,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'avgResponseTime' => $avgResponseTime,
            'byIncidentType' => $byIncidentType,
            'bySite' => $bySite,
            'bySeverity' => $bySeverity,
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'incidentTypes' => IncidentType::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function incidentsExport(Request $request): Response
    {
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->query('date_from')) : now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : now()->endOfMonth();

        $spreadsheet = SpreadsheetExporter::createSheetWithHeaders(['No. Insiden', 'Jenis', 'Site', 'Keparahan', 'Status', 'Response Time (menit)']);
        $sheet = $spreadsheet->getActiveSheet();

        $incidents = Incident::query()->with(['incidentType', 'site', 'severityLevel'])->whereBetween('reported_at', [$dateFrom, $dateTo])->orderByDesc('reported_at')->get();

        foreach ($incidents as $i => $incident) {
            $sheet->fromArray([
                $incident->incident_number, $incident->incidentType->name ?? '-', $incident->site->name ?? '-',
                $incident->severityLevel->name ?? '-', $incident->statusLabel(), $incident->responseTimeMinutes() ?? '-',
            ], null, 'A'.($i + 2));
        }

        SpreadsheetExporter::download($spreadsheet, 'laporan-insiden-'.now()->format('Ymd-His').'.xlsx');
    }

    public function equipmentUsage(Request $request): View
    {
        $usages = IncidentEquipmentUsage::query()
            ->with(['incident', 'equipmentable'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('EmergencyResponse.report.equipment-usage', ['usages' => $usages]);
    }

    public function workOrders(Request $request): View
    {
        $workOrders = WorkOrder::query()
            ->with(['equipmentable', 'assignedTechnician'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->boolean('only_overdue'), fn ($q) => $q->whereNotIn('status', ['closed'])->whereNotNull('target_end_at')->whereDate('target_end_at', '<', today()))
            ->orderByDesc('requested_at')
            ->paginate(20)
            ->withQueryString();

        $totalActualCost = WorkOrder::query()->whereNotNull('actual_cost')->sum('actual_cost');

        return view('EmergencyResponse.report.work-orders', [
            'workOrders' => $workOrders,
            'statuses' => WorkOrder::STATUSES,
            'totalActualCost' => $totalActualCost,
        ]);
    }

    public function attendance(Request $request): View
    {
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->query('date_from')) : now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : now()->endOfMonth();

        $attendance = Attendance::query()
            ->with(['employee', 'shift'])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->orderByDesc('date')
            ->paginate(30)
            ->withQueryString();

        return view('EmergencyResponse.report.attendance', ['attendance' => $attendance, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo]);
    }

    public function trainingCertification(): View
    {
        $trainings = EmployeeTraining::query()->with(['employee', 'training'])->whereNotNull('expires_at')->orderBy('expires_at')->paginate(20, ['*'], 'trainings_page');
        $certifications = EmployeeCertification::query()->with(['employee', 'certification'])->whereNotNull('expires_at')->orderBy('expires_at')->paginate(20, ['*'], 'certifications_page');

        return view('EmergencyResponse.report.training-certification', compact('trainings', 'certifications'));
    }
}
