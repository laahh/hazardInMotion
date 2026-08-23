<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\MasterData\ChecklistTemplateRequest;
use App\Models\EmergencyResponse\MasterData\ChecklistTemplate;
use App\Models\EmergencyResponse\MasterData\EquipmentCategory;
use App\Models\EmergencyResponse\MasterData\SafetyDeviceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChecklistTemplateController extends Controller
{
    public const APPLIES_TO_OPTIONS = ['emergency_equipment' => 'Emergency Equipment', 'safety_device' => 'Safety Device'];

    public const ANSWER_TYPE_OPTIONS = ['compliance' => 'Sesuai/Tidak Sesuai/Tidak Berlaku', 'measurement' => 'Nilai Pengukuran', 'text' => 'Teks Bebas'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $templates = ChecklistTemplate::query()
            ->withCount('items')
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.master-data.checklist-template.index', compact('templates', 'q'));
    }

    public function create(): View
    {
        return $this->form(new ChecklistTemplate());
    }

    public function edit(ChecklistTemplate $checklist_template): View
    {
        $checklist_template->load('items');

        return $this->form($checklist_template);
    }

    private function form(ChecklistTemplate $template): View
    {
        return view('EmergencyResponse.master-data.checklist-template.form', [
            'template' => $template,
            'equipmentCategories' => EquipmentCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'safetyDeviceTypes' => SafetyDeviceType::query()->where('is_active', true)->orderBy('name')->get(),
            'appliesToOptions' => self::APPLIES_TO_OPTIONS,
            'answerTypeOptions' => self::ANSWER_TYPE_OPTIONS,
        ]);
    }

    public function store(ChecklistTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        DB::transaction(function () use ($data, $items): void {
            $template = ChecklistTemplate::create($data);
            $this->saveItems($template, $items);
        });

        return redirect()->route('emergency-response.master-data.checklist-templates.index')->with('success', 'Template checklist berhasil ditambahkan.');
    }

    public function update(ChecklistTemplateRequest $request, ChecklistTemplate $checklist_template): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        DB::transaction(function () use ($checklist_template, $data, $items): void {
            $checklist_template->update($data);
            $checklist_template->items()->delete();
            $this->saveItems($checklist_template, $items);
        });

        return redirect()->route('emergency-response.master-data.checklist-templates.index')->with('success', 'Template checklist berhasil diperbarui.');
    }

    public function destroy(Request $request, ChecklistTemplate $checklist_template): RedirectResponse
    {
        $checklist_template->update(['updated_by' => $request->user()->id]);
        $checklist_template->delete();

        return redirect()->route('emergency-response.master-data.checklist-templates.index')->with('success', 'Template checklist berhasil dihapus.');
    }

    private function saveItems(ChecklistTemplate $template, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            $template->items()->create([
                'sort_order' => $index,
                'item_text' => $item['item_text'],
                'answer_type' => $item['answer_type'],
                'is_required' => ! empty($item['is_required']),
            ]);
        }
    }
}
