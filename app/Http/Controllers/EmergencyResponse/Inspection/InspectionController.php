<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Inspection;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\Inspection\Inspection;
use App\Models\EmergencyResponse\Inspection\InspectionFinding;
use App\Models\EmergencyResponse\Inspection\InspectionResult;
use App\Models\EmergencyResponse\MasterData\ChecklistTemplate;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\EmergencyResponse\SafetyDevice\SafetyDevice;
use App\Support\EmergencyResponse\PrintableExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InspectionController extends Controller
{
    public function index(Request $request): View
    {
        $inspections = Inspection::query()
            ->with(['target', 'site', 'inspector'])
            ->when($request->filled('q'), fn ($query) => $query->where('inspection_number', 'like', '%'.$request->query('q').'%'))
            ->when($request->filled('site_id'), fn ($query) => $query->where('site_id', $request->query('site_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.inspection.index', [
            'inspections' => $inspections,
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => Inspection::STATUSES,
        ]);
    }

    public function pickTarget(Request $request): View
    {
        $siteId = $request->query('site_id');
        $targets = collect();

        if ($siteId) {
            $equipment = EmergencyEquipment::query()->where('site_id', $siteId)->orderBy('name')->get()
                ->map(fn ($item) => ['type' => 'equipment', 'id' => $item->id, 'code' => $item->code, 'name' => $item->name]);
            $devices = SafetyDevice::query()->where('site_id', $siteId)->orderBy('name')->get()
                ->map(fn ($item) => ['type' => 'safety_device', 'id' => $item->id, 'code' => $item->code, 'name' => $item->name]);
            $targets = $equipment->concat($devices)->sortBy('name')->values();
        }

        return view('EmergencyResponse.inspection.pick-target', [
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'siteId' => $siteId,
            'targets' => $targets,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $request->validate(['type' => ['required', 'in:equipment,safety_device'], 'id' => ['required', 'uuid']]);

        $target = $this->resolveTarget($request->query('type'), $request->query('id'));
        if (! $target) {
            return redirect()->route('emergency-response.inspection.pick-target')->with('error', 'Target inspeksi tidak ditemukan.');
        }

        $template = $this->resolveChecklistTemplate($request->query('type'), $target);
        if (! $template) {
            return redirect()->route('emergency-response.inspection.pick-target')->with('error', 'Belum ada template checklist untuk kategori/jenis ini. Buat dulu di Master Data > Template Checklist.');
        }

        return view('EmergencyResponse.inspection.form', [
            'inspection' => new Inspection(),
            'targetType' => $request->query('type'),
            'target' => $target,
            'template' => $template->load('items'),
            'complianceValues' => InspectionResult::COMPLIANCE_VALUES,
            'conditions' => EmergencyEquipment::CONDITIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSubmission($request);

        $target = $this->resolveTarget($data['target_type'], $data['target_id']);
        $template = ChecklistTemplate::findOrFail($data['checklist_template_id']);

        $inspection = DB::transaction(function () use ($request, $data, $target, $template) {
            $inspection = Inspection::create([
                'inspection_number' => $this->generateInspectionNumber(),
                'target_type' => get_class($target),
                'target_id' => $target->id,
                'checklist_template_id' => $template->id,
                'site_id' => $target->site_id,
                'inspector_id' => $request->user()->id,
                'status' => $data['action'] === 'submit' ? 'submitted' : 'draft',
                'condition_result' => $data['condition_result'] ?? null,
                'notes' => $data['notes'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'inspected_at' => now(),
                'submitted_at' => $data['action'] === 'submit' ? now() : null,
                'signature_path' => $this->storeSignature($request->input('signature_data')),
                'created_by' => $request->user()->id,
            ]);

            $this->saveResults($inspection, $request, $data['items']);

            return $inspection;
        });

        return redirect()->route('emergency-response.inspection.show', $inspection)
            ->with('success', $data['action'] === 'submit' ? 'Inspeksi berhasil disubmit.' : 'Inspeksi disimpan sebagai draft.');
    }

    public function show(Inspection $inspection): View
    {
        $inspection->load(['target', 'checklistTemplate', 'site', 'inspector', 'results.templateItem', 'findings.pic', 'approvedBy', 'rejectedBy']);

        return view('EmergencyResponse.inspection.show', ['inspection' => $inspection]);
    }

    public function approve(Request $request, Inspection $inspection): RedirectResponse
    {
        $hasFindings = $inspection->findings()->where('status', '!=', 'resolved')->exists();

        $inspection->update([
            'status' => $hasFindings ? 'follow_up_required' : 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return redirect()->route('emergency-response.inspection.show', $inspection)->with('success', 'Inspeksi disetujui.');
    }

    public function reject(Request $request, Inspection $inspection): RedirectResponse
    {
        $request->validate(['approval_notes' => ['required', 'string']]);

        $inspection->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $request->user()->id,
            'approval_notes' => $request->input('approval_notes'),
        ]);

        return redirect()->route('emergency-response.inspection.show', $inspection)->with('success', 'Inspeksi ditolak, inspector perlu mengulang.');
    }

    public function pdf(Inspection $inspection, PrintableExporter $exporter): Response
    {
        $inspection->load(['target', 'checklistTemplate', 'site', 'inspector', 'results', 'findings']);

        return $exporter->streamPdf(
            'EmergencyResponse.inspection.pdf',
            ['inspection' => $inspection],
            "laporan-inspeksi-{$inspection->inspection_number}.pdf",
        );
    }

    private function resolveTarget(string $type, string $id): EmergencyEquipment|SafetyDevice|null
    {
        return $type === 'equipment'
            ? EmergencyEquipment::find($id)
            : SafetyDevice::find($id);
    }

    private function resolveChecklistTemplate(string $type, EmergencyEquipment|SafetyDevice $target): ?ChecklistTemplate
    {
        $appliesTo = $type === 'equipment' ? 'emergency_equipment' : 'safety_device';
        $categoryColumn = $type === 'equipment' ? 'equipment_category_id' : 'safety_device_type_id';
        $categoryId = $type === 'equipment' ? $target->equipment_category_id : $target->safety_device_type_id;

        return ChecklistTemplate::query()
            ->where('applies_to', $appliesTo)
            ->where('is_active', true)
            ->where(function ($query) use ($categoryColumn, $categoryId) {
                $query->where($categoryColumn, $categoryId)->orWhereNull($categoryColumn);
            })
            ->orderByRaw("{$categoryColumn} is null") // yang match kategori spesifik diutamakan
            ->first();
    }

    private function generateInspectionNumber(): string
    {
        $year = now()->format('Y');
        $count = Inspection::query()->whereYear('created_at', $year)->lockForUpdate()->count();

        return sprintf('INSP-%s-%06d', $year, $count + 1);
    }

    private function validateSubmission(Request $request): array
    {
        return $request->validate([
            'target_type' => ['required', 'in:equipment,safety_device'],
            'target_id' => ['required', 'uuid'],
            'checklist_template_id' => ['required', 'uuid'],
            'action' => ['required', 'in:draft,submit'],
            'condition_result' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.checklist_template_item_id' => ['required', 'uuid'],
            'items.*.item_text' => ['required', 'string'],
            'items.*.answer_type' => ['required', 'in:compliance,measurement,text'],
            'items.*.answer_value' => ['nullable', 'string'],
            'items.*.notes' => ['nullable', 'string'],
        ]);
    }

    private function saveResults(Inspection $inspection, Request $request, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            $photoBefore = $request->file("items.{$index}.photo_before");
            $photoAfter = $request->file("items.{$index}.photo_after");

            $result = $inspection->results()->create([
                'checklist_template_item_id' => $item['checklist_template_item_id'],
                'sort_order' => $index,
                'item_text_snapshot' => $item['item_text'],
                'answer_type_snapshot' => $item['answer_type'],
                'answer_value' => $item['answer_value'] ?? null,
                'notes' => $item['notes'] ?? null,
                'photo_before_path' => $photoBefore?->store('emergency-response/inspection-photos', 'public'),
                'photo_after_path' => $photoAfter?->store('emergency-response/inspection-photos', 'public'),
            ]);

            if ($result->isNonCompliant()) {
                InspectionFinding::create([
                    'inspection_id' => $inspection->id,
                    'inspection_result_id' => $result->id,
                    'description' => 'Temuan: '.$item['item_text'].($item['notes'] ?? '' ? ' — '.$item['notes'] : ''),
                    'status' => 'open',
                    'created_by' => $request->user()->id,
                ]);
            }
        }

        if ($inspection->findings()->exists() && $inspection->status === 'submitted') {
            $inspection->update(['status' => 'follow_up_required']);
        }
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
