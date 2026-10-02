<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Publik;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\PublicInspectionStoreRequest;
use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\MasterData\ChecklistTemplate;
use App\Models\EmergencyResponse\SafetyDevice\SafetyDevice;
use App\Services\EmergencyResponse\InspectionSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Form inspeksi publik (tanpa login) di /form/inspeksi-emergency.
 * Dipakai inspector lewat HP: scan QR di stiker peralatan atau cari manual,
 * checklist-nya muncul otomatis sesuai kategori peralatan.
 *
 * Hasilnya masuk berstatus submitted/follow_up_required dan baru dianggap sah
 * setelah di-approve admin di /emergency-response/inspection (perlu login).
 */
class InspectionPublicController extends Controller
{
    /** Batas hasil pencarian, supaya daftar di HP tetap enak dibaca. */
    private const LOOKUP_LIMIT = 15;

    public function __construct(
        private readonly InspectionSubmissionService $submissions,
    ) {}

    public function show(Request $request): View
    {
        return view('EmergencyResponse.publik.inspection-form', [
            'prefillQuery' => trim((string) $request->query('code', $request->query('q', ''))),
            'conditions' => EmergencyEquipment::CONDITIONS,
        ]);
    }

    public function success(Request $request): View
    {
        return view('EmergencyResponse.publik.inspection-success', [
            'inspectionNumber' => trim((string) $request->query('no', '')),
            'targetName' => trim((string) $request->query('target', '')),
            'findingCount' => max(0, (int) $request->query('findings', 0)),
            'submittedAt' => trim((string) $request->query('at', '')),
        ]);
    }

    /**
     * AJAX: cari peralatan/safety device beserta checklist yang berlaku.
     */
    public function lookup(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(['found' => false, 'message' => 'Ketik UUID, No Registrasi, atau nama peralatan.']);
        }

        $options = $this->findEquipment($q)
            ->concat($this->findSafetyDevices($q))
            ->sortBy('name')
            ->take(self::LOOKUP_LIMIT)
            ->values();

        if ($options->isEmpty()) {
            return response()->json([
                'found' => false,
                'message' => 'Tidak ditemukan. Periksa kembali UUID/No Registrasi, atau hubungi admin kalau peralatannya belum terdaftar.',
            ]);
        }

        return response()->json([
            'found' => true,
            'message' => $options->count() === 1
                ? 'Peralatan ditemukan. Periksa datanya lalu isi checklist di bawah.'
                : 'Ditemukan '.$options->count().' peralatan. Pilih yang akan diinspeksi.',
            'options' => $options,
        ]);
    }

    public function store(PublicInspectionStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $target = $this->resolveTarget($validated['target_type'], $validated['target_id']);

        if ($target === null) {
            return back()->withInput()->withErrors(['target_id' => 'Peralatan tidak ditemukan. Cari ulang peralatannya.']);
        }

        $template = ChecklistTemplate::query()->find($validated['checklist_template_id']);

        // Template harus benar-benar yang berlaku untuk target ini, bukan id
        // sembarangan yang dikirim dari form.
        if ($template === null || ! $this->templateAppliesTo($template, $validated['target_type'], $target)) {
            return back()->withInput()->withErrors(['checklist_template_id' => 'Checklist tidak sesuai dengan peralatan yang dipilih. Cari ulang peralatannya.']);
        }

        $inspection = $this->submissions->submit(
            $target,
            $template,
            $this->itemPayload($request),
            [
                'status' => 'submitted',
                'condition_result' => $validated['condition_result'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'inspector_name' => $validated['inspector_name'],
                'inspector_nik' => $validated['inspector_nik'] ?? null,
                'inspector_sid' => $validated['inspector_sid'] ?? null,
                'inspector_phone' => $validated['inspector_phone'],
            ],
            $validated['signature_data'] ?? null,
        );

        return redirect()->route('er-inspection.public.success', [
            'no' => $inspection->inspection_number,
            'target' => $target->name,
            'findings' => $inspection->findings()->count(),
            'at' => now()->format('d M Y, H:i'),
        ]);
    }

    /**
     * Menyatukan isian checklist dengan file foto-nya.
     *
     * @return array<int, array<string, mixed>>
     */
    private function itemPayload(PublicInspectionStoreRequest $request): array
    {
        $items = [];

        foreach (array_values($request->validated()['items']) as $index => $item) {
            $items[] = [
                'checklist_template_item_id' => $item['checklist_template_item_id'],
                'answer_value' => $item['answer_value'] ?? null,
                'notes' => $item['notes'] ?? null,
                'photo_before' => $request->file("items.{$index}.photo"),
            ];
        }

        return $items;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function findEquipment(string $q)
    {
        return EmergencyEquipment::query()
            ->with(['category', 'site', 'company'])
            ->where(fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('registration_number', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(self::LOOKUP_LIMIT)
            ->get()
            ->map(fn (EmergencyEquipment $item): array => $this->targetOption(
                'equipment',
                $item,
                $item->category->name,
                [
                    'No Registrasi' => $item->registration_number,
                    'Perusahaan' => $item->company?->name,
                    'Lokasi' => $item->locationLabel(),
                ],
            ))
            ->filter(fn (array $option): bool => $option['template'] !== null)
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function findSafetyDevices(string $q)
    {
        return SafetyDevice::query()
            ->with(['type', 'site'])
            ->where(fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(self::LOOKUP_LIMIT)
            ->get()
            ->map(fn (SafetyDevice $item): array => $this->targetOption(
                'safety_device',
                $item,
                $item->type->name ?? null,
                ['No. Seri' => $item->serial_number],
            ))
            ->filter(fn (array $option): bool => $option['template'] !== null)
            ->values();
    }

    /**
     * @param  array<string, string|null>  $details
     * @return array<string, mixed>
     */
    private function targetOption(string $type, EmergencyEquipment|SafetyDevice $item, ?string $categoryName, array $details): array
    {
        $template = $this->resolveChecklistTemplate($type, $item);

        return [
            'type' => $type,
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'type_label' => $type === 'equipment' ? 'Emergency Equipment' : 'Safety Device',
            'category' => $categoryName,
            'site' => $item->site->name ?? null,
            'condition' => $item->conditionLabel(),
            'details' => array_filter($details, fn (?string $value): bool => $value !== null && trim($value) !== ''),
            'template' => $template === null ? null : [
                'id' => $template->id,
                'name' => $template->name,
                'items' => $template->items->map(fn ($templateItem): array => [
                    'id' => $templateItem->id,
                    'text' => $templateItem->item_text,
                    'answer_type' => $templateItem->answer_type,
                    'is_required' => (bool) $templateItem->is_required,
                ])->values(),
            ],
        ];
    }

    private function resolveTarget(string $type, string $id): EmergencyEquipment|SafetyDevice|null
    {
        return $type === 'equipment'
            ? EmergencyEquipment::find($id)
            : SafetyDevice::find($id);
    }

    private function templateAppliesTo(ChecklistTemplate $template, string $type, EmergencyEquipment|SafetyDevice $target): bool
    {
        return $this->resolveChecklistTemplate($type, $target)?->id === $template->id;
    }

    /**
     * Template yang cocok dengan kategori target; yang spesifik diutamakan di
     * atas template umum (kategori kosong).
     */
    private function resolveChecklistTemplate(string $type, EmergencyEquipment|SafetyDevice $target): ?ChecklistTemplate
    {
        $appliesTo = $type === 'equipment' ? 'emergency_equipment' : 'safety_device';
        $categoryColumn = $type === 'equipment' ? 'equipment_category_id' : 'safety_device_type_id';
        $categoryId = $type === 'equipment' ? $target->equipment_category_id : $target->safety_device_type_id;

        return ChecklistTemplate::query()
            ->with('items')
            ->where('applies_to', $appliesTo)
            ->where('is_active', true)
            ->where(fn ($query) => $query->where($categoryColumn, $categoryId)->orWhereNull($categoryColumn))
            ->orderByRaw("{$categoryColumn} is null")
            ->first();
    }
}
