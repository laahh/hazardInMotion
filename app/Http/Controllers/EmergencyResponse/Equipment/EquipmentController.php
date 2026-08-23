<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Equipment;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EmergencyResponse\Shared\Concerns\ManagesEquipmentDocuments;
use App\Http\Requests\EmergencyResponse\Equipment\EquipmentRequest;
use App\Jobs\EmergencyResponse\ImportEquipmentJob;
use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\MasterData\Area;
use App\Models\EmergencyResponse\MasterData\Department;
use App\Models\EmergencyResponse\MasterData\EmergencyUnit;
use App\Models\EmergencyResponse\MasterData\EquipmentCategory;
use App\Models\EmergencyResponse\MasterData\Location;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\EmergencyResponse\Shared\EquipmentDocument;
use App\Support\EmergencyResponse\QrCodeService;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EquipmentController extends Controller
{
    use ManagesEquipmentDocuments;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $equipment = EmergencyEquipment::query()
            ->with(['category', 'site', 'location'])
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->when($request->filled('equipment_category_id'), fn ($query) => $query->where('equipment_category_id', $request->query('equipment_category_id')))
            ->when($request->filled('site_id'), fn ($query) => $query->where('site_id', $request->query('site_id')))
            ->when($request->filled('condition'), fn ($query) => $query->where('condition', $request->query('condition')))
            ->when($request->filled('operational_status'), fn ($query) => $query->where('operational_status', $request->query('operational_status')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.equipment.index', [
            'equipment' => $equipment,
            'q' => $q,
            'categories' => EquipmentCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'conditions' => EmergencyEquipment::CONDITIONS,
            'operationalStatuses' => EmergencyEquipment::OPERATIONAL_STATUSES,
        ]);
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
            'categories' => EquipmentCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::query()->with('site')->where('is_active', true)->orderBy('name')->get(),
            'areas' => Area::query()->with('location')->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'emergencyUnits' => EmergencyUnit::query()->where('is_active', true)->orderBy('name')->get(),
            'conditions' => EmergencyEquipment::CONDITIONS,
            'operationalStatuses' => EmergencyEquipment::OPERATIONAL_STATUSES,
        ]);
    }

    public function show(EmergencyEquipment $equipment): View
    {
        $equipment->load(['category', 'site', 'location', 'area', 'department', 'emergencyUnit', 'documents', 'statusHistories.changedBy']);

        return view('EmergencyResponse.equipment.show', [
            'equipment' => $equipment,
            'scanUrl' => route('emergency-response.scan.show', ['code' => $equipment->code]),
        ]);
    }

    public function store(EquipmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['photo']);
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('emergency-response/equipment-photos', 'public');
        }

        $equipment = EmergencyEquipment::create($data);

        return redirect()->route('emergency-response.equipment.show', $equipment)->with('success', 'Emergency equipment berhasil ditambahkan.');
    }

    public function update(EquipmentRequest $request, EmergencyEquipment $equipment): RedirectResponse
    {
        $data = $request->validated();
        unset($data['photo']);
        $data['updated_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('emergency-response/equipment-photos', 'public');
        }

        $equipment->update($data);

        return redirect()->route('emergency-response.equipment.show', $equipment)->with('success', 'Emergency equipment berhasil diperbarui.');
    }

    public function destroy(Request $request, EmergencyEquipment $equipment): RedirectResponse
    {
        $equipment->update(['updated_by' => $request->user()->id]);
        $equipment->delete();

        return redirect()->route('emergency-response.equipment.index')->with('success', 'Emergency equipment berhasil dihapus.');
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

    public function export(): Response
    {
        $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
            'Kode', 'Nama', 'Kategori', 'Tipe/Model', 'Merek', 'No. Seri', 'Site', 'Lokasi', 'Kondisi', 'Status Operasional', 'Jadwal Inspeksi Berikutnya',
        ]);
        $sheet = $spreadsheet->getActiveSheet();

        $equipment = EmergencyEquipment::query()->with(['category', 'site', 'location'])->orderBy('name')->get();

        foreach ($equipment as $i => $item) {
            $row = $i + 2;
            $sheet->fromArray([
                $item->code,
                $item->name,
                $item->category->name ?? '-',
                $item->type_model,
                $item->brand,
                $item->serial_number,
                $item->site->name ?? '-',
                $item->location->name ?? '-',
                $item->conditionLabel(),
                $item->operationalStatusLabel(),
                optional($item->next_inspection_at)->format('Y-m-d'),
            ], null, "A{$row}");
        }

        SpreadsheetExporter::download($spreadsheet, 'emergency-equipment-'.now()->format('Ymd-His').'.xlsx');
    }

    public function importTemplate(): Response
    {
        $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
            'Kode', 'Nama', 'Kategori (kode)', 'Tipe/Model', 'Merek', 'No. Seri', 'Site (kode)', 'Kondisi', 'Status Operasional',
        ]);
        $spreadsheet->getActiveSheet()->fromArray([
            'CONTOH-001', 'Contoh: APAR 6kg Ruang Genset (hapus baris ini)', 'APAR', 'ABC Dry Powder', 'Chubb', 'SN-12345', 'SITE-A', 'baik', 'available',
        ], null, 'A2');

        SpreadsheetExporter::download($spreadsheet, 'template-import-emergency-equipment.xlsx');
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
