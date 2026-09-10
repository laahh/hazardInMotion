<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlRoom\ControlRoomTbcExcelImportRequest;
use App\Http\Requests\ControlRoom\ControlRoomTbcValidationRequest;
use App\Models\ControlRoom\ControlRoomTbcValidation;
use App\Services\ControlRoom\ControlRoomTbcExcelParser;
use App\Services\ControlRoom\ControlRoomTbcExcelTemplateService;
use App\Services\ControlRoom\ControlRoomTbcValidationUpsertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ControlRoomTbcValidationController extends Controller
{
    /**
     * @var list<array{key: string, label: string}>
     */
    public const FORM_FIELDS = [
        ['key' => 'no_alert', 'label' => 'No Alert'],
        ['key' => 'validator', 'label' => 'Validator'],
        ['key' => 'tasklist', 'label' => 'Tasklist'],
        ['key' => 'to_be_concerned_hazard', 'label' => 'TobeConcernedHazard'],
        ['key' => 'gr', 'label' => 'GR'],
        ['key' => 'catatan', 'label' => 'Catatan'],
        ['key' => 'nomor_gr_valid', 'label' => 'Nomor GR valid'],
        ['key' => 'kategori_gr_valid_kpi', 'label' => 'Kategori GR valid KPI'],
        ['key' => 'blindspot_terlapor_bc', 'label' => 'Blindspot terlapor BC'],
        ['key' => 'kronologi_singkat', 'label' => 'Kronologi Singkat (summary dari Deskripsi)'],
        ['key' => 'rootcause_aktual', 'label' => 'Rootcause Aktual'],
        ['key' => 'detail_rootcause_aktual', 'label' => 'Detail Rootcause Aktual'],
        ['key' => 'sid_pekerja_terlibat', 'label' => 'SID Pekerja Terlibat (pelaku/pelanggar)'],
        ['key' => 'sid_pengawas_aktual', 'label' => 'SID Pengawas Aktual/Pengawas Langsung (pelaku/pelanggar)'],
        ['key' => 'tindakan_perbaikan_aktual', 'label' => 'Tindakan Perbaikan Aktual'],
        ['key' => 'no_item_pspp', 'label' => 'No Item PSPP'],
        ['key' => 'kategori_gr', 'label' => 'Kategori GR'],
    ];

    public function index(Request $request): View
    {
        $q = trim((string) $request->string('q')->toString());
        $query = ControlRoomTbcValidation::query()->orderByDesc('id');
        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $like = '%'.$q.'%';
                $sub->where('tasklist', 'like', $like)
                    ->orWhere('validator', 'like', $like)
                    ->orWhere('to_be_concerned_hazard', 'like', $like)
                    ->orWhere('gr', 'like', $like)
                    ->orWhere('kronologi_singkat', 'like', $like)
                    ->orWhere('sid_pekerja_terlibat', 'like', $like)
                    ->orWhere('sid_pengawas_aktual', 'like', $like)
                    ->orWhere('rootcause_aktual', 'like', $like);
            });
        }

        return view('control-room.tbc-validations.index', [
            'rows' => $query->paginate(20)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('control-room.tbc-validations.form', [
            'mode' => 'create',
            'row' => new ControlRoomTbcValidation(),
            'fields' => self::FORM_FIELDS,
            'rootcauseOptions' => $this->configList('tbc_rootcause_options'),
            'noAlertOptions' => $this->configList('tbc_no_alert_options'),
        ]);
    }

    public function store(ControlRoomTbcValidationRequest $request): RedirectResponse
    {
        $payload = $request->attributesPayload();
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;
        ControlRoomTbcValidation::query()->create($payload);

        return redirect()
            ->route('control-room.tbc-validations.index')
            ->with('success', 'Validasi TBC disimpan.');
    }

    public function edit(ControlRoomTbcValidation $tbcValidation): View
    {
        return view('control-room.tbc-validations.form', [
            'mode' => 'edit',
            'row' => $tbcValidation,
            'fields' => self::FORM_FIELDS,
            'rootcauseOptions' => $this->configList('tbc_rootcause_options'),
            'noAlertOptions' => $this->configList('tbc_no_alert_options'),
        ]);
    }

    public function update(ControlRoomTbcValidationRequest $request, ControlRoomTbcValidation $tbcValidation): RedirectResponse
    {
        $payload = $request->attributesPayload();
        $payload['updated_by'] = $request->user()?->id;
        $tbcValidation->fill($payload);
        $tbcValidation->save();

        return redirect()
            ->route('control-room.tbc-validations.index')
            ->with('success', 'Validasi TBC diperbarui.');
    }

    public function destroy(ControlRoomTbcValidation $tbcValidation): RedirectResponse
    {
        $tbcValidation->delete();

        return redirect()
            ->route('control-room.tbc-validations.index')
            ->with('success', 'Validasi TBC dihapus.');
    }

    public function excelTemplate(ControlRoomTbcExcelTemplateService $templates): StreamedResponse
    {
        return $templates->download();
    }

    public function excelImport(
        ControlRoomTbcExcelImportRequest $request,
        ControlRoomTbcExcelParser $parser,
        ControlRoomTbcValidationUpsertService $upsert,
    ): RedirectResponse {
        $file = $request->file('file');
        $path = $file?->getRealPath();
        if (! is_string($path) || $path === '') {
            return back()->withErrors(['file' => 'File tidak dapat dibaca.']);
        }
        $parsed = $parser->parse($path);
        if ($parsed->hasErrors()) {
            return back()->withErrors(['file' => $parsed->errors])->withInput();
        }
        if ($parsed->rows === []) {
            return back()->withErrors(['file' => 'Tidak ada baris dengan Tasklist yang bisa diimpor.'])->withInput();
        }

        $result = $upsert->upsert($parsed->rows, $request->user()?->id);
        if ($result->hasErrors()) {
            return back()->withErrors(['file' => $result->errors])->withInput();
        }

        $warnings = [...$parsed->warnings, ...$result->warnings];

        return redirect()
            ->route('control-room.tbc-validations.index')
            ->with('success', "Excel masuk: {$result->created} baru, {$result->updated} diperbarui.")
            ->with('warnings', $warnings);
    }

    /**
     * @return list<string>
     */
    private function configList(string $key): array
    {
        $raw = config('control-room.'.$key, []);
        if (! is_array($raw)) {
            return [];
        }

        $options = [];
        foreach ($raw as $option) {
            $label = trim((string) $option);
            if ($label !== '') {
                $options[] = $label;
            }
        }

        return $options;
    }
}
