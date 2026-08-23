<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Manpower;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Manpower\Certification;
use App\Models\EmergencyResponse\Manpower\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeCertificationController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'certification_id' => ['required', Rule::exists('er_certifications', 'id')],
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'issuing_body' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['required', 'date'],
            'certificate' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $certification = Certification::findOrFail($data['certification_id']);
        $data['expires_at'] = $certification->default_validity_months
            ? \Illuminate\Support\Carbon::parse($data['issued_at'])->addMonths($certification->default_validity_months)
            : null;
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('certificate')) {
            $data['certificate_path'] = $request->file('certificate')->store('emergency-response/certification-files', 'public');
        }
        unset($data['certificate']);

        $employee->certifications()->create($data);

        return back()->with('success', 'Sertifikasi berhasil ditambahkan.');
    }

    public function destroy(Employee $employee, \App\Models\EmergencyResponse\Manpower\EmployeeCertification $certification): RedirectResponse
    {
        $certification->delete();

        return back()->with('success', 'Sertifikasi berhasil dihapus.');
    }
}
