<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\SafetyDevice;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EmergencyResponse\Shared\Concerns\ManagesEquipmentDocuments;
use App\Http\Requests\EmergencyResponse\SafetyDevice\SafetyDeviceRequest;
use App\Jobs\EmergencyResponse\ImportSafetyDeviceJob;
use App\Models\EmergencyResponse\MasterData\Area;
use App\Models\EmergencyResponse\MasterData\Department;
use App\Models\EmergencyResponse\MasterData\Location;
use App\Models\EmergencyResponse\MasterData\SafetyDeviceType;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\EmergencyResponse\SafetyDevice\SafetyDevice;
use App\Models\EmergencyResponse\Shared\EquipmentDocument;
use App\Support\EmergencyResponse\QrCodeService;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SafetyDeviceController extends Controller
{
    use ManagesEquipmentDocuments;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $devices = SafetyDevice::query()
            ->with(['type', 'site', 'location'])
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->when($request->filled('safety_device_type_id'), fn ($query) => $query->where('safety_device_type_id', $request->query('safety_device_type_id')))
            ->when($request->filled('site_id'), fn ($query) => $query->where('site_id', $request->query('site_id')))
            ->when($request->filled('condition'), fn ($query) => $query->where('condition', $request->query('condition')))
            ->when($request->filled('operational_status'), fn ($query) => $query->where('operational_status', $request->query('operational_status')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.safety-device.index', [
            'devices' => $devices,
            'q' => $q,
            'types' => SafetyDeviceType::query()->where('is_active', true)->orderBy('name')->get(),
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'conditions' => SafetyDevice::CONDITIONS,
            'operationalStatuses' => SafetyDevice::OPERATIONAL_STATUSES,
        ]);
    }

    public function create(): View
    {
        return $this->form(new SafetyDevice());
    }

    public function edit(SafetyDevice $safety_device): View
    {
        return $this->form($safety_device);
    }

    private function form(SafetyDevice $device): View
    {
        return view('EmergencyResponse.safety-device.form', [
            'device' => $device,
            'types' => SafetyDeviceType::query()->where('is_active', true)->orderBy('name')->get(),
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::query()->with('site')->where('is_active', true)->orderBy('name')->get(),
            'areas' => Area::query()->with('location')->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'conditions' => SafetyDevice::CONDITIONS,
            'operationalStatuses' => SafetyDevice::OPERATIONAL_STATUSES,
        ]);
    }

    public function show(SafetyDevice $safety_device): View
    {
        $safety_device->load(['type', 'site', 'location', 'area', 'department', 'documents', 'statusHistories.changedBy']);

        return view('EmergencyResponse.safety-device.show', [
            'device' => $safety_device,
            'scanUrl' => route('emergency-response.scan.show', ['code' => $safety_device->code]),
        ]);
    }

    public function store(SafetyDeviceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['photo']);
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('emergency-response/safety-device-photos', 'public');
        }

        $device = SafetyDevice::create($data);

        return redirect()->route('emergency-response.safety-device.show', $device)->with('success', 'Safety device berhasil ditambahkan.');
    }

    public function update(SafetyDeviceRequest $request, SafetyDevice $safety_device): RedirectResponse
    {
        $data = $request->validated();
        unset($data['photo']);
        $data['updated_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('emergency-response/safety-device-photos', 'public');
        }

        $safety_device->update($data);

        return redirect()->route('emergency-response.safety-device.show', $safety_device)->with('success', 'Safety device berhasil diperbarui.');
    }

    public function destroy(Request $request, SafetyDevice $safety_device): RedirectResponse
    {
        $safety_device->update(['updated_by' => $request->user()->id]);
        $safety_device->delete();

        return redirect()->route('emergency-response.safety-device.index')->with('success', 'Safety device berhasil dihapus.');
    }

    public function storeDocument(Request $request, SafetyDevice $safety_device): RedirectResponse
    {
        $this->storeDocumentFor($safety_device, $request);

        return redirect()->route('emergency-response.safety-device.show', $safety_device)->with('success', 'Dokumen berhasil diunggah.');
    }

    public function destroyDocument(SafetyDevice $safety_device, EquipmentDocument $document): RedirectResponse
    {
        $this->deleteDocument($document);

        return redirect()->route('emergency-response.safety-device.show', $safety_device)->with('success', 'Dokumen berhasil dihapus.');
    }

    public function qrSvg(SafetyDevice $safety_device, QrCodeService $qrCodeService): Response
    {
        $url = route('emergency-response.scan.show', ['code' => $safety_device->code]);

        return response($qrCodeService->svg($url), 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function print(SafetyDevice $safety_device): View
    {
        return view('EmergencyResponse.safety-device.print', ['device' => $safety_device]);
    }

    public function export(): Response
    {
        $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
            'Kode', 'Nama', 'Jenis', 'Merek', 'Model', 'No. Seri', 'Site', 'Lokasi', 'Kondisi', 'Status Operasional', 'Kalibrasi Berikutnya',
        ]);
        $sheet = $spreadsheet->getActiveSheet();

        $devices = SafetyDevice::query()->with(['type', 'site', 'location'])->orderBy('name')->get();

        foreach ($devices as $i => $item) {
            $row = $i + 2;
            $sheet->fromArray([
                $item->code,
                $item->name,
                $item->type->name ?? '-',
                $item->brand,
                $item->model,
                $item->serial_number,
                $item->site->name ?? '-',
                $item->location->name ?? '-',
                $item->conditionLabel(),
                $item->operationalStatusLabel(),
                optional($item->next_calibration_at)->format('Y-m-d'),
            ], null, "A{$row}");
        }

        SpreadsheetExporter::download($spreadsheet, 'safety-device-'.now()->format('Ymd-His').'.xlsx');
    }

    public function importTemplate(): Response
    {
        $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
            'Kode', 'Nama', 'Jenis (kode)', 'Merek', 'Model', 'No. Seri', 'Site (kode)', 'Kondisi', 'Status Operasional',
        ]);
        $spreadsheet->getActiveSheet()->fromArray([
            'CONTOH-001', 'Contoh: Gas Detector Area Tambang (hapus baris ini)', 'GAS-DET', 'Drager', 'X-am 2500', 'SN-98765', 'SITE-A', 'baik', 'available',
        ], null, 'A2');

        SpreadsheetExporter::download($spreadsheet, 'template-import-safety-device.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);

        $file = $request->file('excel_file');
        $uniqueName = uniqid('er_safety_device_', true).'.'.$file->getClientOriginalExtension();
        $storedPath = $file->storeAs('emergency-response/imports', $uniqueName);

        ImportSafetyDeviceJob::dispatch($storedPath, $request->user()->id);

        return redirect()->route('emergency-response.safety-device.index')->with('success', 'File berhasil diunggah dan sedang diproses di background.');
    }
}
