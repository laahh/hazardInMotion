<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData\Concerns;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $data = $request->validate($this->rules(), $this->validationMessages());
        $data['is_active'] = $request->boolean('is_active', true);
        $userId = $request->user()->id;

        try {
            DB::transaction(function () use ($data, $userId): void {
                // Unique index `code` masih berlaku untuk baris soft-deleted.
                // Kalau kode pernah dipakai lalu dihapus, restore + update agar
                // halaman kosong tidak "menolak" kode yang sama tanpa alasan jelas.
                $existing = $this->model::withTrashed()->where('code', $data['code'])->first();

                if ($existing !== null) {
                    if (! $existing->trashed()) {
                        throw ValidationException::withMessages([
                            'code' => 'Kode sudah digunakan oleh data aktif.',
                        ]);
                    }

                    $existing->restore();
                    $existing->update([
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'is_active' => $data['is_active'],
                        'updated_by' => $userId,
                    ]);

                    return;
                }

                $data['created_by'] = $userId;
                $this->model::create($data);
            });
        } catch (QueryException $e) {
            report($e);

            return redirect()
                ->route("{$this->routeName}.index")
                ->withInput()
                ->withErrors(['code' => 'Gagal menyimpan. Kode mungkin sudah digunakan.']);
        }

        return redirect()->route("{$this->routeName}.index")->with('success', "{$this->pageTitle} berhasil ditambahkan.");
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $item = $this->model::findOrFail($id);
        $data = $request->validate($this->rules($id), $this->validationMessages());
        $data['updated_by'] = $request->user()->id;
        $data['is_active'] = $request->boolean('is_active', true);

        try {
            $item->update($data);
        } catch (QueryException $e) {
            report($e);

            return redirect()
                ->route("{$this->routeName}.index")
                ->withInput()
                ->withErrors(['code' => 'Gagal memperbarui. Kode mungkin sudah digunakan.']);
        }

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
        $unique = Rule::unique($table, 'code')->whereNull('deleted_at');

        if ($ignoreId !== null && $ignoreId !== '') {
            $unique->ignore($ignoreId);
        }

        return [
            'code' => ['required', 'string', 'max:50', $unique],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationMessages(): array
    {
        return [
            'code.required' => 'Kode wajib diisi.',
            'code.unique' => 'Kode sudah digunakan oleh data aktif.',
            'name.required' => 'Nama wajib diisi.',
        ];
    }
}
