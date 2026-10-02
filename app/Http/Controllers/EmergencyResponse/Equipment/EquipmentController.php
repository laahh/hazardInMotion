<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Equipment;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EmergencyResponse\Shared\Concerns\ManagesEquipmentDocuments;
use App\Http\Requests\EmergencyResponse\Equipment\EquipmentRequest;
use App\Jobs\EmergencyResponse\ImportEquipmentJob;
use App\Models\Company;
use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\MasterData\Department;
use App\Models\EmergencyResponse\MasterData\EmergencyUnit;
use App\Models\EmergencyResponse\MasterData\EquipmentCategory;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\EmergencyResponse\Shared\EquipmentDocument;
use App\Support\EmergencyResponse\EquipmentImportTemplate;
use App\Support\EmergencyResponse\QrCodeService;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EquipmentController extends Controller
{
    use ManagesEquipmentDocuments;

    private const PER_PAGE = 15;

    /** Kolom export, sesuai urutan register BA. */
    private const EXPORT_HEADERS = [
        'No', 'UUID', 'Kategori Peralatan', 'Nama Peralatan', 'No Registrasi', 'Detail Peralatan',
        'Klasifikasi Alat', 'Kondisi Peralatan', 'Keterangan Alat', 'Keterangan Kerusakan',
        'Status Posisi Barang', 'SITE', 'Perusahaan', 'Progress BA', 'Keterangan BA',
        'Status Barang', 'Tanggal Close BA',
    ];

    public function index(Request $request): View
    {
        $equipment = $this->filtered($request)
            ->with(['category', 'site', 'company'])
            ->orderBy('name')
            ->orderBy('sequence_number')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('EmergencyResponse.equipment.index', [
            'equipment' => $equipment,
            'q' => trim((string) $request->query('q', '')),
        ] + $this->filterOptions());
    }

    /**
     * Filter dipakai bersama oleh index dan export supaya hasil unduhan sama
     * dengan yang terlihat di layar.
     */
    private function filtered(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return EmergencyEquipment::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('registration_number', 'like', "%{$q}%")))
            ->when($request->filled('equipment_category_id'), fn ($query) => $query->where('equipment_category_id', $request->query('equipment_category_id')))
            ->when($request->filled('site_id'), fn ($query) => $query->where('site_id', $request->query('site_id')))
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->query('company_id')))
            ->when($request->filled('condition'), fn ($query) => $query->where('condition', $request->query('condition')))
            ->when($request->filled('position_status'), fn ($query) => $query->where('position_status', $request->query('position_status')))
            ->when($request->filled('ba_progress'), fn ($query) => $query->where('ba_progress', $request->query('ba_progress')))
            ->when($request->filled('item_status'), fn ($query) => $query->where('item_status', $request->query('item_status')));
    }

    /** @return array<string, mixed> */
    private function filterOptions(): array
    {
        return [
            'categories' => EquipmentCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(),
            'conditions' => EmergencyEquipment::CONDITIONS,
            'positionStatuses' => EmergencyEquipment::POSITION_STATUSES,
            'baProgresses' => EmergencyEquipment::BA_PROGRESSES,
            'itemStatuses' => EmergencyEquipment::ITEM_STATUSES,
        ];
    }

    public function create(): View
    {
        return $this->form(new EmergencyEquipment());
    }

    public function edit(EmergencyEquipment $equipment): View
    {
        return $this->form($equipment);
    }

    private function form(EmergencyEquipment $equipment): View
    {
        return view('EmergencyResponse.equipment.form', [
            'equipment' => $equipment,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'emergencyUnits' => EmergencyUnit::query()->where('is_active', true)->orderBy('name')->get(),
            'operationalStatuses' => EmergencyEquipment::OPERATIONAL_STATUSES,
            'classificationSuggestions' => $this->classificationSuggestions(),
        ] + $this->filterOptions());
    }

    /**
     * Klasifikasi alat sengaja berupa teks bebas; nilai yang sudah pernah
     * dipakai ditawarkan sebagai datalist agar tetap konsisten.
     *
     * @return array<int, string>
     */
    private function classificationSuggestions(): array
    {
        return EmergencyEquipment::query()
            ->whereNotNull('classification')
            ->where('classification', '!=', '')
            ->distinct()
            ->orderBy('classification')
            ->pluck('classification')
            ->all();
    }

    public function show(EmergencyEquipment $equipment): View
    {
        $equipment->load(['category', 'site', 'company', 'location', 'area', 'department', 'emergencyUnit', 'documents', 'statusHistories.changedBy']);

        return view('EmergencyResponse.equipment.show', [
            'equipment' => $equipment,
            'scanUrl' => route('emergency-response.scan.show', ['code' => $equipment->code]),
        ]);
    }

    public function store(EquipmentRequest $request): RedirectResponse
    {
        $data = $this->payload($request);
        $data['created_by'] = $request->user()->id;

        $equipment = EmergencyEquipment::create($data);

        return redirect()->route('emergency-response.equipment.show', $equipment)->with('success', "Peralatan berhasil ditambahkan dengan UUID {$equipment->code}.");
    }

    public function update(EquipmentRequest $request, EmergencyEquipment $equipment): RedirectResponse
    {
        $data = $this->payload($request);
        $data['updated_by'] = $request->user()->id;

        $equipment->update($data);

        return redirect()->route('emergency-response.equipment.show', $equipment)->with('success', 'Peralatan berhasil diperbarui.');
    }

    /** @return array<string, mixed> */
    private function payload(EquipmentRequest $request): array
    {
        $data = $request->validated();
        unset($data['photo']);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('emergency-response/equipment-photos', 'public');
        }

        return $data;
    }

    public function destroy(Request $request, EmergencyEquipment $equipment): RedirectResponse
    {
        $equipment->update(['updated_by' => $request->user()->id]);
        $equipment->delete();

        return redirect()->route('emergency-response.equipment.index')->with('success', 'Peralatan berhasil dihapus.');
    }

    public function storeDocument(Request $request, EmergencyEquipment $equipment): RedirectResponse
    {
        $this->storeDocumentFor($equipment, $request);

        return redirect()->route('emergency-response.equipment.show', $equipment)->with('success', 'Dokumen berhasil diunggah.');
    }

    public function destroyDocument(EmergencyEquipment $equipment, EquipmentDocument $document): RedirectResponse
    {
        $this->deleteDocument($document);

        return redirect()->route('emergency-response.equipment.show', $equipment)->with('success', 'Dokumen berhasil dihapus.');
    }

    public function qrSvg(EmergencyEquipment $equipment, QrCodeService $qrCodeService): Response
    {
        $url = route('emergency-response.scan.show', ['code' => $equipment->code]);

        return response($qrCodeService->svg($url), 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function print(EmergencyEquipment $equipment): View
    {
        return view('EmergencyResponse.equipment.print', ['equipment' => $equipment]);
    }

    public function export(Request $request): Response
    {
        $spreadsheet = SpreadsheetExporter::createSheetWithHeaders(self::EXPORT_HEADERS);
        $sheet = $spreadsheet->getActiveSheet();

        $equipment = $this->filtered($request)
            ->with(['category', 'site', 'company'])
            ->orderBy('name')
            ->orderBy('sequence_number')
            ->get();

        foreach ($equipment as $i => $item) {
            $sheet->fromArray([
                $i + 1,
                $item->code,
                $item->category->name ?? '-',
                $item->name,
                $item->registration_number ?: '-',
                $item->equipment_detail ?: '-',
                $item->classification ?: '-',
                $item->conditionLabel(),
                $item->equipment_remarks ?: '-',
                $item->damage_remarks ?: '-',
                $item->positionStatusLabel() ?: '-',
                $item->site->name ?? '-',
                $item->company->name ?? '-',
                $item->baProgressLabel() ?: '-',
                $item->ba_remarks ?: '-',
                $item->itemStatusLabel() ?: '-',
                optional($item->ba_closed_at)->format('Y-m-d'),
            ], null, 'A'.($i + 2));
        }

        SpreadsheetExporter::download($spreadsheet, 'emergency-equipment-'.now()->format('Ymd-His').'.xlsx');
    }

    public function importTemplate(EquipmentImportTemplate $template): Response
    {
        SpreadsheetExporter::download($template->build(), 'template-import-database-equipment.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);

        $file = $request->file('excel_file');
        $uniqueName = uniqid('er_equipment_', true).'.'.$file->getClientOriginalExtension();
        $storedPath = $file->storeAs('emergency-response/imports', $uniqueName);

        ImportEquipmentJob::dispatch($storedPath, $request->user()->id);

        return redirect()->route('emergency-response.equipment.index')->with('success', 'File berhasil diunggah dan sedang diproses di background.');
    }
}
