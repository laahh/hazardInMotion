<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Manpower;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Manpower\Training;
use App\Models\EmergencyResponse\MasterData\TrainingType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TrainingController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $trainings = Training::query()
            ->with('type')
            ->when($q !== '', fn ($query) => $query->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.manpower.training.index', [
            'trainings' => $trainings,
            'q' => $q,
            'trainingTypes' => TrainingType::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        Training::create($data);

        return redirect()->route('emergency-response.manpower.trainings.index')->with('success', 'Training berhasil ditambahkan.');
    }

    public function update(Request $request, Training $training): RedirectResponse
    {
        $data = $request->validate($this->rules($training));
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $training->update($data);

        return redirect()->route('emergency-response.manpower.trainings.index')->with('success', 'Training berhasil diperbarui.');
    }

    public function destroy(Training $training): RedirectResponse
    {
        $training->delete();

        return redirect()->route('emergency-response.manpower.trainings.index')->with('success', 'Training berhasil dihapus.');
    }

    private function rules(?Training $ignore = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('er_trainings', 'code')->ignore($ignore)],
            'name' => ['required', 'string', 'max:255'],
            'training_type_id' => ['nullable', Rule::exists('er_training_types', 'id')],
            'provider' => ['nullable', 'string', 'max:255'],
            'default_validity_months' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
