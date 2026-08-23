<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData\Concerns;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Shared CRUD for master-data lookup tables with the identical
 * code/name/description/is_active shape. Subclasses only declare which
 * model/route/title they represent; the list+form view is shared
 * (resources/views/EmergencyResponse/master-data/simple/index.blade.php).
 */
abstract class SimpleLookupController extends Controller
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    protected string $model;

    protected string $routeName; // mis. 'emergency-response.master-data.departments'

    protected string $pageTitle; // mis. 'Departemen'

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $items = $this->model::query()
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.master-data.simple.index', [
            'items' => $items,
            'q' => $q,
            'routeName' => $this->routeName,
            'pageTitle' => $this->pageTitle,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['created_by'] = $request->user()->id;
        $data['is_active'] = $request->boolean('is_active', true);

        $this->model::create($data);

        return redirect()->route("{$this->routeName}.index")->with('success', "{$this->pageTitle} berhasil ditambahkan.");
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $item = $this->model::findOrFail($id);
        $data = $request->validate($this->rules($id));
        $data['updated_by'] = $request->user()->id;
        $data['is_active'] = $request->boolean('is_active', true);

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
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
