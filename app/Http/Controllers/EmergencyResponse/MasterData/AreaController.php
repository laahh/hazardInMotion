<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\MasterData\AreaRequest;
use App\Models\EmergencyResponse\MasterData\Area;
use App\Models\EmergencyResponse\MasterData\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $areas = Area::query()
            ->with('location.site')
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $locations = Location::query()->with('site')->where('is_active', true)->orderBy('name')->get();

        return view('EmergencyResponse.master-data.area.index', compact('areas', 'locations', 'q'));
    }

    public function store(AreaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        Area::create($data);

        return redirect()->route('emergency-response.master-data.areas.index')->with('success', 'Area berhasil ditambahkan.');
    }

    public function update(AreaRequest $request, Area $area): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $area->update($data);

        return redirect()->route('emergency-response.master-data.areas.index')->with('success', 'Area berhasil diperbarui.');
    }

    public function destroy(Request $request, Area $area): RedirectResponse
    {
        $area->update(['updated_by' => $request->user()->id]);
        $area->delete();

        return redirect()->route('emergency-response.master-data.areas.index')->with('success', 'Area berhasil dihapus.');
    }
}
