<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Incident;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\Incident\IncidentRequest;
use App\Models\EmergencyResponse\Incident\Incident;
use App\Models\EmergencyResponse\Incident\IncidentAttachment;
use App\Models\EmergencyResponse\Incident\IncidentVictim;
use App\Models\EmergencyResponse\MasterData\IncidentType;
use App\Models\EmergencyResponse\MasterData\PriorityLevel;
use App\Models\EmergencyResponse\MasterData\SeverityLevel;
use App\Models\EmergencyResponse\MasterData\Site;
use App\Models\User;
use App\Services\EmergencyResponse\NotificationService;
use App\Support\EmergencyResponse\PrintableExporter;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentController extends Controller
{
    private const TIMESTAMP_FIELDS = ['dispatched_at', 'arrived_at', 'handling_started_at', 'contained_at', 'handling_completed_at'];

    public function index(Request $request): View
    {
        $incidents = Incident::query()
            ->with(['incidentType', 'severityLevel', 'priorityLevel', 'site'])
            ->when($request->filled('q'), fn ($query) => $query->where('incident_number', 'like', '%'.$request->query('q').'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('site_id'), fn ($query) => $query->where('site_id', $request->query('site_id')))
            ->when($request->filled('incident_type_id'), fn ($query) => $query->where('incident_type_id', $request->query('incident_type_id')))
            ->orderByDesc('reported_at')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.incident.index', [
            'incidents' => $incidents,
            'statuses' => Incident::STATUSES,
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
            'incidentTypes' => IncidentType::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Incident());
    }

    public function edit(Incident $incident): View
    {
        abort_unless($incident->status === 'open', 403, 'Insiden yang sudah dikonfirmasi tidak bisa diedit lagi, gunakan alur status/timeline.');

        return $this->form($incident);
    }

    private function form(Incident $incident): View
    {
        return view('EmergencyResponse.incident.form', [
            'incident' => $incident,
            'incidentTypes' => IncidentType::query()->where('is_active', true)->orderBy('name')->get(),
            'severityLevels' => SeverityLevel::query()->where('is_active', true)->orderBy('level')->get(),
            'priorityLevels' => PriorityLevel::query()->where('is_active', true)->orderBy('level')->get(),
            'sites' => Site::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(IncidentRequest $request, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $incident = DB::transaction(function () use ($data, $request, $user) {
            $incident = Incident::create([
                ...$data,
                'incident_number' => $this->generateIncidentNumber(),
                'reported_at' => now(),
                'victim_count' => $data['victim_count'] ?? 0,
                'reported_by' => $user->id,
                'reporter_name' => $data['reporter_name'] ?: $user->name,
                'reporter_phone' => $data['reporter_phone'] ?? null,
                'reporter_department' => $data['reporter_department'] ?? null,
                'status' => 'open',
                'created_by' => $user->id,
            ]);

            $incident->addTimelineEntry('generic', 'Laporan insiden dibuat oleh '.$user->name.'.', $user->id);
            $this->flagPossibleDuplicate($incident);

            return $incident;
        });

        $notifications->notifyRole(
            'dispatcher',
            'incident_new',
            'Laporan Insiden Baru',
            "Laporan insiden baru {$incident->incident_number}: {$incident->description}",
            route('emergency-response.incident.show', $incident),
        );

        return redirect()->route('emergency-response.incident.show', $incident)->with('success', 'Laporan insiden berhasil dibuat: '.$incident->incident_number);
    }

    public function update(IncidentRequest $request, Incident $incident): RedirectResponse
    {
        abort_unless($incident->status === 'open', 403);

        $incident->update([...$request->validated(), 'updated_by' => $request->user()->id]);

        return redirect()->route('emergency-response.incident.show', $incident)->with('success', 'Laporan insiden berhasil diperbarui.');
    }

    public function show(Incident $incident): View
    {
        $incident->load([
            'incidentType', 'severityLevel', 'priorityLevel', 'site', 'reportedBy', 'closedBy',
            'victims', 'statusHistories.changedBy', 'assignments.user', 'responseUnits.emergencyUnit',
            'responseUnits.personnel.user', 'equipmentUsages.equipmentable', 'attachments.uploader', 'timeline.creator',
        ]);

        return view('EmergencyResponse.incident.show', [
            'incident' => $incident,
            'users' => User::query()->orderBy('name')->get(),
            'emergencyUnits' => \App\Models\EmergencyResponse\MasterData\EmergencyUnit::query()->where('is_active', true)->orderBy('name')->get(),
            'availableEquipment' => \App\Models\EmergencyResponse\Equipment\EmergencyEquipment::query()->orderBy('name')->get(),
            'availableSafetyDevices' => \App\Models\EmergencyResponse\SafetyDevice\SafetyDevice::query()->orderBy('name')->get(),
        ]);
    }

    public function confirm(Request $request, Incident $incident): RedirectResponse
    {
        $incident->update(['status' => 'in_progress', 'confirmed_at' => now()]);
        $incident->recordStatusChange('in_progress', 'Laporan dikonfirmasi oleh dispatcher.', $request->user()->id);

        return back()->with('success', 'Insiden dikonfirmasi, status menjadi In Progress.');
    }

    public function updateTimestamp(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate(['field' => ['required', Rule::in(self::TIMESTAMP_FIELDS)]]);

        $incident->update([$data['field'] => now()]);
        $incident->addTimelineEntry('generic', $this->timestampLabel($data['field']).' dicatat.', $request->user()->id);

        return back()->with('success', 'Waktu berhasil dicatat.');
    }

    public function resolve(Request $request, Incident $incident): RedirectResponse
    {
        $incident->update([
            'status' => 'resolved',
            'handling_completed_at' => $incident->handling_completed_at ?? now(),
        ]);
        $incident->recordStatusChange('resolved', $request->input('notes'), $request->user()->id);

        return back()->with('success', 'Kondisi dinyatakan terkendali, status menjadi Resolved.');
    }

    public function close(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'root_cause' => ['nullable', 'string'],
            'corrective_action' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $request, $incident) {
            $incident->update([
                ...$data,
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $request->user()->id,
            ]);
            $incident->recordStatusChange('closed', 'Investigasi & administrasi selesai.', $request->user()->id);

            foreach ($incident->equipmentUsages as $usage) {
                $usage->equipmentable?->forceFill(['next_inspection_at' => now()->addDays(3)])->save();
            }
        });

        return back()->with('success', 'Insiden ditutup. Equipment yang dipakai dijadwalkan ulang untuk inspeksi.');
    }

    public function dismissDuplicate(Incident $incident): RedirectResponse
    {
        $incident->update(['is_possible_duplicate' => false]);

        return back()->with('success', 'Ditandai bukan duplikat.');
    }

    public function assignPic(Request $request, Incident $incident, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'role_note' => ['nullable', 'string', 'max:255']]);

        $incident->assignments()->create([...$data, 'assigned_by' => $request->user()->id, 'assigned_at' => now()]);
        $user = User::find($data['user_id']);
        $incident->addTimelineEntry('assignment', $user->name.' ditugaskan sebagai PIC'.($data['role_note'] ?? '' ? " ({$data['role_note']})" : '').'.', $request->user()->id);

        $notifications->notifyUser(
            $user,
            'assignment',
            'Anda Ditugaskan sebagai PIC Insiden',
            "Anda ditugaskan sebagai PIC untuk insiden {$incident->incident_number}".($data['role_note'] ?? '' ? " ({$data['role_note']})" : '').'.',
            route('emergency-response.incident.show', $incident),
        );

        return back()->with('success', 'PIC berhasil ditugaskan.');
    }

    public function addComment(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate(['comment' => ['required', 'string'], 'is_internal' => ['nullable', 'boolean']]);

        $incident->addTimelineEntry('comment', $data['comment'], $request->user()->id, $request->boolean('is_internal'));

        return back()->with('success', 'Komentar ditambahkan.');
    }

    public function storeVictim(Request $request, Incident $incident): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'condition' => ['required', Rule::in(array_keys(IncidentVictim::CONDITIONS))],
            'details' => ['nullable', 'string'],
        ]);

        $incident->victims()->create([...$data, 'created_by' => $request->user()->id]);
        $incident->increment('victim_count');

        return back()->with('success', 'Data korban ditambahkan.');
    }

    public function destroyVictim(Incident $incident, IncidentVictim $victim): RedirectResponse
    {
        $victim->delete();
        $incident->decrement('victim_count');

        return back()->with('success', 'Data korban dihapus.');
    }

    public function storeAttachment(Request $request, Incident $incident): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,mp4,mov,pdf,doc,docx'],
            'type' => ['required', 'in:photo,video,document'],
        ]);

        $file = $request->file('file');
        $path = $file->store('emergency-response/incident-attachments', 'public');

        $incident->attachments()->create([
            'type' => $request->input('type'),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        return back()->with('success', 'Lampiran berhasil diunggah.');
    }

    public function destroyAttachment(Incident $incident, IncidentAttachment $attachment): RedirectResponse
    {
        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Lampiran dihapus.');
    }

    public function pdf(Incident $incident, PrintableExporter $exporter): Response
    {
        $incident->load(['incidentType', 'severityLevel', 'priorityLevel', 'site', 'victims', 'responseUnits.emergencyUnit', 'timeline']);

        return $exporter->streamPdf(
            'EmergencyResponse.incident.pdf',
            ['incident' => $incident],
            "laporan-insiden-{$incident->incident_number}.pdf",
        );
    }

    public function export(): Response
    {
        $spreadsheet = SpreadsheetExporter::createSheetWithHeaders([
            'No. Insiden', 'Tanggal Kejadian', 'Jenis', 'Tingkat Keparahan', 'Prioritas', 'Site', 'Status', 'Response Time (menit)',
        ]);
        $sheet = $spreadsheet->getActiveSheet();

        $incidents = Incident::query()->with(['incidentType', 'severityLevel', 'priorityLevel', 'site'])->orderByDesc('reported_at')->get();

        foreach ($incidents as $i => $incident) {
            $sheet->fromArray([
                $incident->incident_number,
                $incident->occurred_at->format('Y-m-d H:i'),
                $incident->incidentType->name ?? '-',
                $incident->severityLevel->name ?? '-',
                $incident->priorityLevel->name ?? '-',
                $incident->site->name ?? '-',
                $incident->statusLabel(),
                $incident->responseTimeMinutes() ?? '-',
            ], null, 'A'.($i + 2));
        }

        SpreadsheetExporter::download($spreadsheet, 'incidents-'.now()->format('Ymd-His').'.xlsx');
    }

    private function generateIncidentNumber(): string
    {
        $year = now()->format('Y');
        $count = Incident::query()->whereYear('created_at', $year)->lockForUpdate()->count();

        return sprintf('INC-%s-%06d', $year, $count + 1);
    }

    private function flagPossibleDuplicate(Incident $incident): void
    {
        $duplicate = Incident::query()
            ->where('id', '!=', $incident->id)
            ->where('site_id', $incident->site_id)
            ->where('incident_type_id', $incident->incident_type_id)
            ->whereIn('status', ['open', 'in_progress'])
            ->where('reported_at', '>=', now()->subHours(2))
            ->latest('reported_at')
            ->first();

        if ($duplicate) {
            $incident->update(['is_possible_duplicate' => true, 'possible_duplicate_of' => $duplicate->id]);
        }
    }

    private function timestampLabel(string $field): string
    {
        return match ($field) {
            'dispatched_at' => 'Waktu tim mulai bergerak',
            'arrived_at' => 'Waktu tim tiba di lokasi',
            'handling_started_at' => 'Waktu penanganan dimulai',
            'contained_at' => 'Waktu kondisi terkendali',
            'handling_completed_at' => 'Waktu penanganan selesai',
            default => $field,
        };
    }
}
