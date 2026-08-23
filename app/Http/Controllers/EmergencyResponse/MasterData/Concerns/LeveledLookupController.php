<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData\Concerns;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Shared CRUD for master-data lookup tables shaped as
 * code/name/level/color/description/is_active (severity & priority levels).
 */
abstract class LeveledLookupController extends Controller
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    protected string $model;

    protected string $routeName;

    protected string $pageTitle;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $items = $this->model::query()
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('level')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.master-data.leveled.index', [
            'items' => $items,
            'q' => $q,
            'routeName' => $this->routeName,
            'pageTitle' => $this->pageTitle,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        $this->model::create($data);

        return redirect()->route("{$this->routeName}.index")->with('success', "{$this->pageTitle} berhasil ditambahkan.");
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $item = $this->model::findOrFail($id);
        $data = $request->validate($this->rules($id));
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $item->update($data);

        return redirect()->route("{$this->routeName}.index")->with('success', "{$this->pageTitle} berhasil diperbarui.");
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $item = $this->model::findOrFail($id);
        $item->update(['updated_by' => $request->user()->id]);
        $item->delete();

        return redirect()->route("{$this->routeName}.index")->with('success', "{$this->pageTitle} berhasil dihapus.");
    }

    protected function rules(?string $ignoreId = null): array
    {
        $table = (new $this->model)->getTable();

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique($table, 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:1', 'max:20'],
            'color' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
