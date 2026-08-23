<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\MasterData\SiteRequest;
use App\Models\EmergencyResponse\MasterData\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $sites = Site::query()
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->withCount('locations')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.master-data.site.index', ['sites' => $sites, 'q' => $q]);
    }

    public function store(SiteRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        Site::create($data);

        return redirect()->route('emergency-response.master-data.sites.index')->with('success', 'Site berhasil ditambahkan.');
    }

    public function update(SiteRequest $request, Site $site): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $site->update($data);

        return redirect()->route('emergency-response.master-data.sites.index')->with('success', 'Site berhasil diperbarui.');
    }

    public function destroy(Request $request, Site $site): RedirectResponse
    {
        $site->update(['updated_by' => $request->user()->id]);
        $site->delete();

        return redirect()->route('emergency-response.master-data.sites.index')->with('success', 'Site berhasil dihapus.');
    }
}
