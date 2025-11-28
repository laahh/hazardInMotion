<?php

namespace App\Http\Controllers;

use App\Jobs\ImportGrTableJob;
use App\Models\GrTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;
use Illuminate\View\View;

class GrTableController extends Controller
{
    /**
     * Display the GR table entries along with input forms.
     */
    public function index(Request $request): View
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $entries = GrTable::orderByDesc('created_at')->paginate($perPage)->withQueryString();

        return view('gr-table.index', compact('entries', 'perPage'));
    }

    /**
     * Store a new entry manually submitted via the form.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tasklist' => ['required', 'string', 'max:255'],
            'gr' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
        ]);

        GrTable::create($validated);

        return redirect()
            ->route('gr-table.index')
            ->with('success', 'Data berhasil disimpan.');
    }

    /**
     * Import data from an uploaded Excel file.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $file = $request->file('excel_file');
        $uniqueName = uniqid('gr_', true) . '.' . $file->getClientOriginalExtension();
        $storedPath = $file->storeAs('gr-imports', $uniqueName);

        ImportGrTableJob::dispatch($storedPath);

        return redirect()
            ->route('gr-table.index')
            ->with('success', 'File berhasil diunggah dan sedang diproses di background. Silakan cek beberapa saat lagi.');
    }
}

