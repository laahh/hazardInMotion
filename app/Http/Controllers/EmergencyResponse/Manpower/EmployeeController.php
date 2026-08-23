<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Manpower;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\Manpower\EmployeeRequest;
use App\Jobs\EmergencyResponse\ImportEmployeeJob;
use App\Models\EmergencyResponse\Manpower\Certification;
use App\Models\EmergencyResponse\Manpower\Employee;
use App\Models\EmergencyResponse\Manpower\Training;
use App\Models\EmergencyResponse\MasterData\Department;
use App\Models\EmergencyResponse\MasterData\EmergencyUnit;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\User;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $employees = Employee::query()
            ->with(['department', 'site', 'emergencyUnit'])
            ->when($q !== '', fn ($query) => $query
                ->where('employee_number', 'like', "%{$q}%")
                ->orWhere('full_name', 'like', "%{$q}%"))
            ->when($request->filled('site_id'), fn ($query) => $query->where('site_id', $request->query('site_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->query('department_id')))
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.manpower.employee.index', [
            'employees' => $employees,
            'q' => $q,
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Employee());
    }

    public function edit(Employee $employee): View
    {
        return $this->form($employee);
    }

    private function form(Employee $employee): View
    {
        return view('EmergencyResponse.manpower.employee.form', [
            'employee' => $employee,
            'users' => User::query()->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'emergencyUnits' => EmergencyUnit::query()->where('is_active', true)->orderBy('name')->get(),
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['photo']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('emergency-response/employee-photos', 'public');
        }

        $employee = Employee::create($data);

        return redirect()->route('emergency-response.manpower.employees.show', $employee)->with('success', 'Data personel berhasil ditambahkan.');
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->validated();
        unset($data['photo']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('emergency-response/employee-photos', 'public');
        }

        $employee->update($data);

        return redirect()->route('emergency-response.manpower.employees.show', $employee)->with('success', 'Data personel berhasil diperbarui.');
    }

    public function show(Employee $employee): View
    {
        $employee->load(['department', 'site', 'emergencyUnit', 'user', 'attendance', 'trainings.training', 'certifications.certification']);

        return view('EmergencyResponse.manpower.employee.show', [
            'employee' => $employee,
            'trainingCatalog' => Training::query()->where('is_active', true)->orderBy('name')->get(),
            'certificationCatalog' => Certification::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $employee->update(['updated_by' => $request->user()->id]);
        $employee->delete();

        return redirect()->route('emergency-response.manpower.employees.index')->with('success', 'Data personel berhasil dihapus.');
    }

    public function export(): Response
    {
        $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
            'No. Pegawai', 'Nama', 'Jabatan', 'Departemen', 'Site', 'Status', 'Peran Emergency',
        ]);
        $sheet = $spreadsheet->getActiveSheet();

        $employees = Employee::query()->with(['department', 'site'])->orderBy('full_name')->get();

        foreach ($employees as $i => $employee) {
            $sheet->fromArray([
                $employee->employee_number,
                $employee->full_name,
                $employee->position,
                $employee->department->name ?? '-',
                $employee->site->name ?? '-',
                $employee->is_active ? 'Aktif' : 'Nonaktif',
                $employee->emergency_role ?? '-',
            ], null, 'A'.($i + 2));
        }

        SpreadsheetExporter::download($spreadsheet, 'employees-'.now()->format('Ymd-His').'.xlsx');
    }

    public function importTemplate(): Response
    {
        $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
            'No. Pegawai', 'Nama Lengkap', 'Jabatan', 'Departemen (kode)', 'Unit Emergency (kode)',
            'Site (kode)', 'Email', 'Telepon', 'Status Pekerjaan', 'Peran Emergency',
        ]);
        $spreadsheet->getActiveSheet()->fromArray([
            'CONTOH-001', 'Contoh: Budi Santoso (hapus baris ini)', 'HSE Officer', 'HSE', 'FIRE-01',
            'SITE-A', 'budi.santoso@example.com', '081234567890', 'permanent', 'Fire Warden',
        ], null, 'A2');

        SpreadsheetExporter::download($spreadsheet, 'template-import-employees.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);

        $file = $request->file('excel_file');
        $uniqueName = uniqid('er_employee_', true).'.'.$file->getClientOriginalExtension();
        $storedPath = $file->storeAs('emergency-response/imports', $uniqueName);

        ImportEmployeeJob::dispatch($storedPath, $request->user()->id);

        return redirect()->route('emergency-response.manpower.employees.index')->with('success', 'File berhasil diunggah dan sedang diproses di background.');
    }
}
