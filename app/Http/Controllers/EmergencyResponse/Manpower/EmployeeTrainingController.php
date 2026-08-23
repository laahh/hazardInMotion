<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Manpower;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Manpower\Employee;
use App\Models\EmergencyResponse\Manpower\Training;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeTrainingController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'training_id' => ['required', Rule::exists('er_trainings', 'id')],
            'provider' => ['nullable', 'string', 'max:255'],
            'trained_at' => ['required', 'date'],
            'score' => ['nullable', 'string', 'max:50'],
            'is_passed' => ['nullable', 'boolean'],
            'certificate' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $training = Training::findOrFail($data['training_id']);
        $data['is_passed'] = $request->boolean('is_passed', true);
        $data['expires_at'] = $training->default_validity_months
            ? \Illuminate\Support\Carbon::parse($data['trained_at'])->addMonths($training->default_validity_months)
            : null;
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('certificate')) {
            $data['certificate_path'] = $request->file('certificate')->store('emergency-response/training-certificates', 'public');
        }
        unset($data['certificate']);

        $employee->trainings()->create($data);

        return back()->with('success', 'Riwayat training berhasil ditambahkan.');
    }

    public function destroy(Employee $employee, \App\Models\EmergencyResponse\Manpower\EmployeeTraining $training): RedirectResponse
    {
        $training->delete();

        return back()->with('success', 'Riwayat training berhasil dihapus.');
    }
}
