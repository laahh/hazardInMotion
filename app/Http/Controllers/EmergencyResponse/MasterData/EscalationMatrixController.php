<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\MasterData\EscalationMatrixRequest;
use App\Models\EmergencyResponse\MasterData\EscalationMatrix;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EscalationMatrixController extends Controller
{
    public const APPLIES_TO_OPTIONS = ['incident' => 'Insiden', 'work_order' => 'Work Order'];

    public const CHANNEL_OPTIONS = ['in_app' => 'In-App', 'email' => 'Email', 'both' => 'In-App & Email'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $matrices = EscalationMatrix::query()
            ->with('notifyRole')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('applies_to')
            ->orderBy('level')
            ->paginate(15)
            ->withQueryString();

        $roles = Role::query()->orderBy('name')->get();

        return view('EmergencyResponse.master-data.escalation-matrix.index', [
            'matrices' => $matrices,
            'q' => $q,
            'roles' => $roles,
            'appliesToOptions' => self::APPLIES_TO_OPTIONS,
            'channelOptions' => self::CHANNEL_OPTIONS,
        ]);
    }

    public function store(EscalationMatrixRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        EscalationMatrix::create($data);

        return redirect()->route('emergency-response.master-data.escalation-matrices.index')->with('success', 'Escalation matrix berhasil ditambahkan.');
    }

    public function update(EscalationMatrixRequest $request, EscalationMatrix $escalation_matrix): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $escalation_matrix->update($data);

        return redirect()->route('emergency-response.master-data.escalation-matrices.index')->with('success', 'Escalation matrix berhasil diperbarui.');
    }

    public function destroy(Request $request, EscalationMatrix $escalation_matrix): RedirectResponse
    {
        $escalation_matrix->update(['updated_by' => $request->user()->id]);
        $escalation_matrix->delete();

        return redirect()->route('emergency-response.master-data.escalation-matrices.index')->with('success', 'Escalation matrix berhasil dihapus.');
    }
}
