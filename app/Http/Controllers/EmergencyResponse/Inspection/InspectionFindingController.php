<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Inspection;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Inspection\InspectionFinding;
use App\Models\User;
use App\Services\EmergencyResponse\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InspectionFindingController extends Controller
{
    public function index(Request $request): View
    {
        $findings = InspectionFinding::query()
            ->with(['inspection.target', 'pic'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')), fn ($query) => $query->where('status', '!=', 'resolved'))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.inspection.findings.index', [
            'findings' => $findings,
            'statuses' => InspectionFinding::STATUSES,
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function assign(Request $request, InspectionFinding $finding, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'pic_id' => ['nullable', 'exists:users,id'],
            'target_date' => ['nullable', 'date'],
        ]);
        $data['updated_by'] = $request->user()->id;
        $isNewPic = ($data['pic_id'] ?? null) && $data['pic_id'] !== $finding->pic_id;
        if (($data['pic_id'] ?? null) && $finding->status === 'open') {
            $data['status'] = 'in_progress';
        }

        $finding->update($data);

        if ($isNewPic) {
            $notifications->notifyUser(
                User::find($data['pic_id']),
                'assignment',
                'Anda Ditugaskan sebagai PIC Temuan Inspeksi',
                "Anda ditugaskan sebagai PIC untuk temuan: {$finding->description}",
                route('emergency-response.inspection.show', $finding->inspection_id),
            );
        }

        return back()->with('success', 'Temuan berhasil diperbarui.');
    }

    public function resolve(Request $request, InspectionFinding $finding): RedirectResponse
    {
        $request->validate(['resolved_notes' => ['nullable', 'string']]);

        $finding->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_notes' => $request->input('resolved_notes'),
            'updated_by' => $request->user()->id,
        ]);

        $inspection = $finding->inspection;
        if ($inspection->status === 'follow_up_required' && ! $inspection->findings()->where('status', '!=', 'resolved')->exists()) {
            $inspection->update(['status' => 'completed']);
        }

        return back()->with('success', 'Temuan ditandai selesai.');
    }
}
