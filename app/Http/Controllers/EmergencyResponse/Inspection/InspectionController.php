<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Inspection;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Inspection\Inspection;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Support\EmergencyResponse\PrintableExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InspectionController extends Controller
{
    public function index(Request $request): View
    {
        $inspections = Inspection::query()
            ->with(['target', 'site', 'inspector'])
            ->when($request->filled('q'), fn ($query) => $query->where('inspection_number', 'like', '%'.$request->query('q').'%'))
            ->when($request->filled('site_id'), fn ($query) => $query->where('site_id', $request->query('site_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.inspection.index', [
            'inspections' => $inspections,
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => Inspection::STATUSES,
        ]);
    }

    /**
     * Pengisian inspeksi dipindahkan ke form berdiri sendiri di
     * /form/inspeksi-emergency (tanpa login, dipakai lewat HP di lapangan).
     * Modul ini tinggal mengurus register, review, dan approval.
     */
    public function startForm(Request $request): RedirectResponse
    {
        return redirect()->away(route('er-inspection.public.form', array_filter([
            'code' => $request->query('code'),
        ])));
    }

    public function show(Inspection $inspection): View
    {
        $inspection->load(['target', 'checklistTemplate', 'site', 'inspector', 'results.templateItem', 'findings.pic', 'approvedBy', 'rejectedBy']);

        return view('EmergencyResponse.inspection.show', ['inspection' => $inspection]);
    }

    public function approve(Request $request, Inspection $inspection): RedirectResponse
    {
        $hasFindings = $inspection->findings()->where('status', '!=', 'resolved')->exists();

        $inspection->update([
            'status' => $hasFindings ? 'follow_up_required' : 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return redirect()->route('emergency-response.inspection.show', $inspection)->with('success', 'Inspeksi disetujui.');
    }

    public function reject(Request $request, Inspection $inspection): RedirectResponse
    {
        $request->validate(['approval_notes' => ['required', 'string']]);

        $inspection->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $request->user()->id,
            'approval_notes' => $request->input('approval_notes'),
        ]);

        return redirect()->route('emergency-response.inspection.show', $inspection)->with('success', 'Inspeksi ditolak, inspector perlu mengulang.');
    }

    public function pdf(Inspection $inspection, PrintableExporter $exporter): Response
    {
        $inspection->load(['target', 'checklistTemplate', 'site', 'inspector', 'results', 'findings']);

        return $exporter->streamPdf(
            'EmergencyResponse.inspection.pdf',
            ['inspection' => $inspection],
            "laporan-inspeksi-{$inspection->inspection_number}.pdf",
        );
    }
}
