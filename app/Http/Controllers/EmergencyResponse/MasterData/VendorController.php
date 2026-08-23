<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\MasterData\VendorRequest;
use App\Models\EmergencyResponse\MasterData\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $vendors = Vendor::query()
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.master-data.vendor.index', compact('vendors', 'q'));
    }

    public function store(VendorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        Vendor::create($data);

        return redirect()->route('emergency-response.master-data.vendors.index')->with('success', 'Vendor berhasil ditambahkan.');
    }

    public function update(VendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $vendor->update($data);

        return redirect()->route('emergency-response.master-data.vendors.index')->with('success', 'Vendor berhasil diperbarui.');
    }

    public function destroy(Request $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update(['updated_by' => $request->user()->id]);
        $vendor->delete();

        return redirect()->route('emergency-response.master-data.vendors.index')->with('success', 'Vendor berhasil dihapus.');
    }
}
