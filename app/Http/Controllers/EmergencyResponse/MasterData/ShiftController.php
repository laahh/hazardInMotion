<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\MasterData\ShiftRequest;
use App\Models\EmergencyResponse\MasterData\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $shifts = Shift::query()
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('start_time')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.master-data.shift.index', compact('shifts', 'q'));
    }

    public function store(ShiftRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        Shift::create($data);

        return redirect()->route('emergency-response.master-data.shifts.index')->with('success', 'Shift berhasil ditambahkan.');
    }

    public function update(ShiftRequest $request, Shift $shift): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $shift->update($data);

        return redirect()->route('emergency-response.master-data.shifts.index')->with('success', 'Shift berhasil diperbarui.');
    }

    public function destroy(Request $request, Shift $shift): RedirectResponse
    {
        $shift->update(['updated_by' => $request->user()->id]);
        $shift->delete();

        return redirect()->route('emergency-response.master-data.shifts.index')->with('success', 'Shift berhasil dihapus.');
    }
}
