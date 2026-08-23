<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\Incident\Incident;
use App\Models\EmergencyResponse\Inspection\Inspection;
use App\Models\EmergencyResponse\Inspection\InspectionSchedule;
use App\Models\EmergencyResponse\Maintenance\MaintenanceSchedule;
use App\Models\EmergencyResponse\Maintenance\WorkOrder;
use App\Models\EmergencyResponse\Manpower\Attendance;
use App\Models\EmergencyResponse\Manpower\EmployeeCertification;
use App\Models\EmergencyResponse\MasterData\Department;
use App\Models\EmergencyResponse\MasterData\EquipmentCategory;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\EmergencyResponse\Notification\Alert;
use App\Models\EmergencyResponse\SafetyDevice\SafetyDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->query('date_from')) : now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : now()->endOfMonth();
        $siteId = $request->query('site_id');
        $departmentId = $request->query('department_id');
        $equipmentCategoryId = $request->query('equipment_category_id');

        $equipmentQuery = EmergencyEquipment::query()
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($equipmentCategoryId, fn ($q) => $q->where('equipment_category_id', $equipmentCategoryId));

        $safetyDeviceQuery = SafetyDevice::query()
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId));

        $incidentQuery = Incident::query()
            ->whereBetween('reported_at', [$dateFrom, $dateTo])
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId));

        // `condition` adalah reserved word di MySQL 8 — tidak aman dipakai mentah lewat selectRaw()
        // tanpa backtick, jadi kolomnya dilewatkan lewat select() biasa (auto di-backtick oleh grammar).
        $equipmentByCondition = (clone $equipmentQuery)->select('condition')->selectRaw('count(*) as total')->groupBy('condition')->pluck('total', 'condition');
        $incidentsByStatus = (clone $incidentQuery)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $incidentsByPriority = (clone $incidentQuery)->with('priorityLevel')->get()->groupBy(fn ($i) => $i->priorityLevel->name ?? 'Tidak ditentukan')->map->count();

        $incidentsWithResponseTime = (clone $incidentQuery)->whereNotNull('arrived_at')->get();
        $avgResponseTime = $incidentsWithResponseTime->isNotEmpty()
            ? round($incidentsWithResponseTime->avg(fn ($i) => $i->responseTimeMinutes()), 1)
            : null;

        $incidentTrend = Incident::query()
            ->selectRaw("DATE_FORMAT(reported_at, '%Y-%m') as ym, count(*) as total")
            ->where('reported_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('ym')->orderBy('ym')->pluck('total', 'ym');

        $responseTimeTrend = Incident::query()
            ->selectRaw("DATE_FORMAT(reported_at, '%Y-%m') as ym, AVG(TIMESTAMPDIFF(MINUTE, reported_at, arrived_at)) as avg_minutes")
            ->whereNotNull('arrived_at')
            ->where('reported_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('ym')->orderBy('ym')->pluck('avg_minutes', 'ym');

        return view('EmergencyResponse.dashboard.index', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'equipmentCategories' => EquipmentCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => compact('siteId', 'departmentId', 'equipmentCategoryId'),

            'totalEquipment' => (clone $equipmentQuery)->count(),
            'totalSafetyDevice' => (clone $safetyDeviceQuery)->count(),
            'equipmentByCondition' => $equipmentByCondition,
            'expiredEquipment' => (clone $equipmentQuery)->whereNotNull('expires_at')->where('expires_at', '<', today())->count(),

            'inspectionsThisMonth' => Inspection::whereBetween('inspected_at', [$dateFrom, $dateTo])->count(),
            'overdueInspectionSchedules' => InspectionSchedule::where('is_active', true)->where('next_due_date', '<', today())->count(),

            'incidentsByStatus' => $incidentsByStatus,
            'incidentsByPriority' => $incidentsByPriority,
            'avgResponseTime' => $avgResponseTime,

            'activeWorkOrders' => WorkOrder::whereNotIn('status', ['closed'])->count(),
            'overdueWorkOrders' => WorkOrder::whereNotIn('status', ['closed'])->whereNotNull('target_end_at')->whereDate('target_end_at', '<', today())->count(),

            'maintenanceDueSoon' => MaintenanceSchedule::where('is_active', true)->whereBetween('next_due_date', [today(), today()->addDays(30)])->count(),
            'certificationsExpiringSoon' => EmployeeCertification::whereNotNull('expires_at')->whereBetween('expires_at', [today(), today()->addDays(30)])->count(),
            'onDutyToday' => Attendance::whereDate('date', today())->where('status', 'hadir')->count(),

            'incidentTrend' => $incidentTrend,
            'responseTimeTrend' => $responseTimeTrend,

            'recentIncidents' => Incident::with(['incidentType', 'site'])->orderByDesc('reported_at')->limit(5)->get(),
            'recentAlerts' => Alert::with('alertable')->orderByDesc('created_at')->limit(5)->get(),

            'equipmentPins' => (clone $equipmentQuery)->whereNotNull('latitude')->whereNotNull('longitude')->get(['id', 'code', 'name', 'latitude', 'longitude']),
            'safetyDevicePins' => (clone $safetyDeviceQuery)->whereNotNull('latitude')->whereNotNull('longitude')->get(['id', 'code', 'name', 'latitude', 'longitude']),
            'incidentPins' => (clone $incidentQuery)->whereNotNull('latitude')->whereNotNull('longitude')->get(['id', 'incident_number', 'latitude', 'longitude', 'status']),
        ]);
    }
}
