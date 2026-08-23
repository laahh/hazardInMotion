<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Manpower;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Manpower\Certification;
use App\Models\EmergencyResponse\MasterData\CertificationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CertificationController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $certifications = Certification::query()
            ->with('type')
            ->when($q !== '', fn ($query) => $query->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.manpower.certification.index', [
            'certifications' => $certifications,
            'q' => $q,
            'certificationTypes' => CertificationType::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        Certification::create($data);

        return redirect()->route('emergency-response.manpower.certifications.index')->with('success', 'Sertifikasi berhasil ditambahkan.');
    }

    public function update(Request $request, Certification $certification): RedirectResponse
    {
        $data = $request->validate($this->rules($certification));
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $certification->update($data);

        return redirect()->route('emergency-response.manpower.certifications.index')->with('success', 'Sertifikasi berhasil diperbarui.');
    }

    public function destroy(Certification $certification): RedirectResponse
    {
        $certification->delete();

        return redirect()->route('emergency-response.manpower.certifications.index')->with('success', 'Sertifikasi berhasil dihapus.');
    }

    private function rules(?Certification $ignore = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('er_certifications', 'code')->ignore($ignore)],
            'name' => ['required', 'string', 'max:255'],
            'certification_type_id' => ['nullable', Rule::exists('er_certification_types', 'id')],
            'issuing_body' => ['nullable', 'string', 'max:255'],
            'default_validity_months' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
