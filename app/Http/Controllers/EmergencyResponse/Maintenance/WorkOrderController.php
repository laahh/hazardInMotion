<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EmergencyResponse\Shared\Concerns\ManagesEquipmentDocuments;
use App\Http\Requests\EmergencyResponse\Maintenance\WorkOrderRequest;
use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\Inspection\InspectionFinding;
use App\Models\EmergencyResponse\Maintenance\MaintenanceHistory;
use App\Models\EmergencyResponse\Maintenance\SparePart;
use App\Models\EmergencyResponse\Maintenance\WorkOrder;
use App\Models\EmergencyResponse\MasterData\PriorityLevel;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\EmergencyResponse\MasterData\Vendor;
use App\Models\EmergencyResponse\SafetyDevice\SafetyDevice;
use App\Models\User;
use App\Services\EmergencyResponse\NotificationService;
use App\Support\EmergencyResponse\PrintableExporter;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    use ManagesEquipmentDocuments;

    public function index(Request $request): View
    {
        $workOrders = WorkOrder::query()
            ->with(['equipmentable', 'site', 'assignedTechnician', 'priorityLevel'])
            ->when($request->filled('q'), fn ($query) => $query->where('work_order_number', 'like', '%'.$request->query('q').'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('work_type'), fn ($query) => $query->where('work_type', $request->query('work_type')))
            ->when($request->filled('site_id'), fn ($query) => $query->where('site_id', $request->query('site_id')))
            ->orderByDesc('requested_at')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.maintenance.work-order.index', [
            'workOrders' => $workOrders,
            'statuses' => WorkOrder::STATUSES,
            'workTypes' => WorkOrder::WORK_TYPES,
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function kanban(): View
    {
        $workOrders = WorkOrder::query()->with(['equipmentable', 'assignedTechnician'])->orderByDesc('requested_at')->get()->groupBy('status');

        return view('EmergencyResponse.maintenance.work-order.kanban', [
            'workOrders' => $workOrders,
            'statuses' => WorkOrder::STATUSES,
        ]);
    }

    public function calendar(Request $request): View
    {
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $start = now()->setDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $workOrders = WorkOrder::query()
            ->with('equipmentable')
            ->whereNotNull('target_end_at')
            ->whereBetween('target_end_at', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn ($wo) => $wo->target_end_at->format('Y-m-d'));

        return view('EmergencyResponse.maintenance.work-order.calendar', [
            'start' => $start,
            'end' => $end,
            'workOrders' => $workOrders,
            'prevMonth' => $start->copy()->subMonth(),
            'nextMonth' => $start->copy()->addMonth(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('EmergencyResponse.maintenance.work-order.form', [
            'equipmentType' => $request->query('equipment_type'),
            'equipmentId' => $request->query('equipment_id'),
            'sourceFindingId' => $request->query('finding_id'),
            'sourceIncidentId' => $request->query('incident_id'),
            'equipmentList' => EmergencyEquipment::query()->orderBy('name')->get(),
            'safetyDeviceList' => SafetyDevice::query()->orderBy('name')->get(),
            'priorityLevels' => PriorityLevel::query()->where('is_active', true)->orderBy('level')->get(),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(WorkOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $equipmentClass = ($data['equipment_type'] ?? null) === 'equipment' ? EmergencyEquipment::class : (($data['equipment_type'] ?? null) === 'safety_device' ? SafetyDevice::class : null);
        $equipment = $equipmentClass ? $equipmentClass::find($data['equipment_id']) : null;

        $workOrder = DB::transaction(function () use ($request, $data, $equipmentClass, $equipment) {
            $workOrder = WorkOrder::create([
                'work_order_number' => $this->generateWorkOrderNumber(),
                'equipmentable_type' => $equipmentClass,
                'equipmentable_id' => $equipment?->id,
                'site_id' => $equipment->site_id ?? null,
                'work_type' => $data['work_type'],
                'source' => $data['source'] ?? 'manual',
                'source_inspection_finding_id' => $data['source_inspection_finding_id'] ?? null,
                'source_incident_id' => $data['source_incident_id'] ?? null,
                'description' => $data['description'],
                'priority_level_id' => $data['priority_level_id'] ?? null,
                'vendor_id' => $data['vendor_id'] ?? null,
                'target_start_at' => $data['target_start_at'] ?? null,
                'target_end_at' => $data['target_end_at'] ?? null,
                'estimated_cost' => $data['estimated_cost'] ?? null,
                'requested_at' => now(),
                'status' => 'requested',
                'created_by' => $request->user()->id,
            ]);
            $workOrder->recordStatusChange('requested', 'Work order dibuat.', $request->user()->id);

            if (! empty($data['source_inspection_finding_id'])) {
                InspectionFinding::where('id', $data['source_inspection_finding_id'])->update(['work_order_id' => $workOrder->id, 'status' => 'in_progress']);
            }

            return $workOrder;
        });

        return redirect()->route('emergency-response.work-order.show', $workOrder)->with('success', 'Work order berhasil dibuat: '.$workOrder->work_order_number);
    }

    public function show(WorkOrder $work_order): View
    {
        $work_order->load(['equipmentable', 'site', 'priorityLevel', 'assignedTechnician', 'vendor', 'approvedBy', 'verifiedBy', 'statusHistories.changedBy', 'spareParts.sparePart', 'documents', 'sourceInspectionFinding', 'sourceIncident']);

        return view('EmergencyResponse.maintenance.work-order.show', [
            'workOrder' => $work_order,
            'technicians' => User::query()->orderBy('name')->get(),
            'spareParts' => SparePart::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function approve(Request $request, WorkOrder $work_order): RedirectResponse
    {
        $work_order->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        $work_order->recordStatusChange('approved', null, $request->user()->id);

        return back()->with('success', 'Work order disetujui.');
    }

    public function assign(Request $request, WorkOrder $work_order, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validate(['assigned_technician_id' => ['required', 'exists:users,id']]);

        $work_order->update([...$data, 'status' => 'assigned']);
        $work_order->recordStatusChange('assigned', null, $request->user()->id);

        $notifications->notifyUser(
            User::find($data['assigned_technician_id']),
            'assignment',
            'Anda Ditugaskan Mengerjakan Work Order',
            "Anda ditugaskan mengerjakan work order {$work_order->work_order_number}: {$work_order->description}",
            route('emergency-response.work-order.show', $work_order),
        );

        return back()->with('success', 'Teknisi berhasil ditugaskan.');
    }

    public function start(Request $request, WorkOrder $work_order): RedirectResponse
    {
        $work_order->update(['status' => 'in_progress', 'actual_start_at' => $work_order->actual_start_at ?? now()]);
        $work_order->recordStatusChange('in_progress', 'Pekerjaan dimulai.', $request->user()->id);

        return back()->with('success', 'Work order dimulai.');
    }

    public function hold(Request $request, WorkOrder $work_order): RedirectResponse
    {
        $data = $request->validate(['notes' => ['required', 'string']]);

        $work_order->update(['status' => 'on_hold']);
        $work_order->recordStatusChange('on_hold', $data['notes'], $request->user()->id);

        return back()->with('success', 'Work order ditahan sementara (on hold).');
    }

    public function resume(Request $request, WorkOrder $work_order): RedirectResponse
    {
        $work_order->update(['status' => 'in_progress']);
        $work_order->recordStatusChange('in_progress', 'Pekerjaan dilanjutkan.', $request->user()->id);

        return back()->with('success', 'Work order dilanjutkan.');
    }

    public function complete(Request $request, WorkOrder $work_order): RedirectResponse
    {
        $data = $request->validate([
            'result_notes' => ['required', 'string'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'signature_data' => ['nullable', 'string'],
        ]);

        $work_order->update([
            'result_notes' => $data['result_notes'],
            'actual_cost' => $data['actual_cost'] ?? $work_order->totalSparePartCost(),
            'actual_end_at' => now(),
            'technician_signature_path' => $this->storeSignature($data['signature_data'] ?? null) ?? $work_order->technician_signature_path,
            'status' => 'completed',
        ]);
        $work_order->recordStatusChange('completed', null, $request->user()->id);

        return back()->with('success', 'Work order ditandai selesai, menunggu verifikasi.');
    }

    public function verify(Request $request, WorkOrder $work_order): RedirectResponse
    {
        $data = $request->validate(['signature_data' => ['nullable', 'string']]);

        $work_order->update([
            'status' => 'verified',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'verifier_signature_path' => $this->storeSignature($data['signature_data'] ?? null) ?? $work_order->verifier_signature_path,
        ]);
        $work_order->recordStatusChange('verified', null, $request->user()->id);

        return back()->with('success', 'Hasil pekerjaan diverifikasi.');
    }

    public function close(Request $request, WorkOrder $work_order): RedirectResponse
    {
        DB::transaction(function () use ($request, $work_order): void {
            $work_order->update(['status' => 'closed']);
            $work_order->recordStatusChange('closed', 'Work order ditutup.', $request->user()->id);

            MaintenanceHistory::create([
                'work_order_id' => $work_order->id,
                'target_type' => $work_order->equipmentable_type,
                'target_id' => $work_order->equipmentable_id,
                'work_type' => $work_order->work_type,
                'summary' => $work_order->result_notes,
                'total_cost' => $work_order->actual_cost,
                'technician_id' => $work_order->assigned_technician_id,
                'completed_at' => $work_order->actual_end_at ?? now(),
            ]);

            if ($work_order->sourceInspectionFinding) {
                $work_order->sourceInspectionFinding->update(['status' => 'resolved', 'resolved_at' => now()]);
            }
        });

        return back()->with('success', 'Work order ditutup.');
    }

    public function storeSparePart(Request $request, WorkOrder $work_order): RedirectResponse
    {
        $data = $request->validate([
            'spare_part_id' => ['required', 'exists:er_spare_parts,id'],
            'quantity_used' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $sparePart = SparePart::find($data['spare_part_id']);

        $work_order->spareParts()->create([...$data, 'unit_cost_snapshot' => $sparePart->unit_cost]);

        return back()->with('success', 'Spare part berhasil dicatat.');
    }

    public function destroySparePart(WorkOrder $work_order, \App\Models\EmergencyResponse\Maintenance\WorkOrderSparePart $spare_part): RedirectResponse
    {
        $spare_part->delete();

        return back()->with('success', 'Catatan spare part dihapus.');
    }

    public function storeDocument(Request $request, WorkOrder $work_order): RedirectResponse
    {
        $this->storeDocumentFor($work_order, $request);

        return back()->with('success', 'Dokumen berhasil diunggah.');
    }

    public function destroyDocument(WorkOrder $work_order, \App\Models\EmergencyResponse\Shared\EquipmentDocument $document): RedirectResponse
    {
        $this->deleteDocument($document);

        return back()->with('success', 'Dokumen berhasil dihapus.');
    }

    public function pdf(WorkOrder $work_order, PrintableExporter $exporter): Response
    {
        $work_order->load(['equipmentable', 'site', 'assignedTechnician', 'vendor', 'spareParts.sparePart']);

        return $exporter->streamPdf(
            'EmergencyResponse.maintenance.work-order.pdf',
            ['workOrder' => $work_order],
            "work-order-{$work_order->work_order_number}.pdf",
        );
    }

    public function export(): Response
    {
        $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
            'No. WO', 'Equipment', 'Jenis Pekerjaan', 'Status', 'Teknisi', 'Target Selesai', 'Biaya Aktual',
        ]);
        $sheet = $spreadsheet->getActiveSheet();

        $workOrders = WorkOrder::query()->with(['equipmentable', 'assignedTechnician'])->orderByDesc('requested_at')->get();

        foreach ($workOrders as $i => $wo) {
            $sheet->fromArray([
                $wo->work_order_number,
                $wo->equipmentable->name ?? '-',
                $wo->workTypeLabel(),
                $wo->statusLabel(),
                $wo->assignedTechnician->name ?? '-',
                optional($wo->target_end_at)->format('Y-m-d'),
                $wo->actual_cost ?? '-',
            ], null, 'A'.($i + 2));
        }

        SpreadsheetExporter::download($spreadsheet, 'work-orders-'.now()->format('Ymd-His').'.xlsx');
    }

    private function generateWorkOrderNumber(): string
    {
        $year = now()->format('Y');
        $count = WorkOrder::query()->whereYear('created_at', $year)->lockForUpdate()->count();

        return sprintf('WO-%s-%06d', $year, $count + 1);
    }

    private function storeSignature(?string $signatureData): ?string
    {
        if (! $signatureData || ! str_contains($signatureData, 'base64,')) {
            return null;
        }

        [, $base64] = explode('base64,', $signatureData, 2);
        $path = 'emergency-response/signatures/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, base64_decode($base64));

        return $path;
    }
}
