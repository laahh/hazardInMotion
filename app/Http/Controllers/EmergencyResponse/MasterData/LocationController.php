<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\MasterData\LocationRequest;
use App\Models\EmergencyResponse\MasterData\Location;
use App\Models\EmergencyResponse\MasterData\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $locations = Location::query()
            ->with('site')
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $sites = Site::query()->where('is_active', true)->orderBy('name')->get();

        return view('EmergencyResponse.master-data.location.index', compact('locations', 'sites', 'q'));
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        Location::create($data);

        return redirect()->route('emergency-response.master-data.locations.index')->with('success', 'Lokasi berhasil ditambahkan.');
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $location->update($data);

        return redirect()->route('emergency-response.master-data.locations.index')->with('success', 'Lokasi berhasil diperbarui.');
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        $location->update(['updated_by' => $request->user()->id]);
        $location->delete();

        return redirect()->route('emergency-response.master-data.locations.index')->with('success', 'Lokasi berhasil dihapus.');
    }
}
