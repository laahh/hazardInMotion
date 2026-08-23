<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\MasterData\SlaRequest;
use App\Models\EmergencyResponse\MasterData\Sla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SlaController extends Controller
{
    public const APPLIES_TO_OPTIONS = [
        'incident' => 'Insiden',
        'work_order' => 'Work Order',
        'inspection_followup' => 'Tindak Lanjut Inspeksi',
    ];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $slas = Sla::query()
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.master-data.sla.index', [
            'slas' => $slas,
            'q' => $q,
            'appliesToOptions' => self::APPLIES_TO_OPTIONS,
        ]);
    }

    public function store(SlaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        Sla::create($data);

        return redirect()->route('emergency-response.master-data.slas.index')->with('success', 'SLA berhasil ditambahkan.');
    }

    public function update(SlaRequest $request, Sla $sla): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $sla->update($data);

        return redirect()->route('emergency-response.master-data.slas.index')->with('success', 'SLA berhasil diperbarui.');
    }

    public function destroy(Request $request, Sla $sla): RedirectResponse
    {
        $sla->update(['updated_by' => $request->user()->id]);
        $sla->delete();

        return redirect()->route('emergency-response.master-data.slas.index')->with('success', 'SLA berhasil dihapus.');
    }
}
