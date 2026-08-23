<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Maintenance\SparePart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SparePartController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $spareParts = SparePart::query()
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.maintenance.spare-part.index', ['spareParts' => $spareParts, 'q' => $q]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        SparePart::create($data);

        return redirect()->route('emergency-response.maintenance.spare-parts.index')->with('success', 'Spare part berhasil ditambahkan.');
    }

    public function update(Request $request, SparePart $spare_part): RedirectResponse
    {
        $data = $request->validate($this->rules($spare_part));
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $spare_part->update($data);

        return redirect()->route('emergency-response.maintenance.spare-parts.index')->with('success', 'Spare part berhasil diperbarui.');
    }

    public function destroy(Request $request, SparePart $spare_part): RedirectResponse
    {
        $spare_part->update(['updated_by' => $request->user()->id]);
        $spare_part->delete();

        return redirect()->route('emergency-response.maintenance.spare-parts.index')->with('success', 'Spare part berhasil dihapus.');
    }

    private function rules(?SparePart $ignore = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('er_spare_parts', 'code')->ignore($ignore)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
