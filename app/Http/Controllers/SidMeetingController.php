<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Event;
use App\Models\EventMinute;
use App\Models\MeetingType;
use App\Models\MinuteIssue;
use App\Models\Site;
use App\Services\SemanticSimilarityService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SidMeetingController extends Controller
{
    public function dashboard(): View
    {
        return view('sid-meeting.desain');
    }

    public function sites(): View
    {
        return view('sid-meeting.master-sites', ['rows' => Site::query()->orderBy('name')->get()]);
    }

    public function storeSite(Request $request): RedirectResponse
    {
        $payload = $request->validate(['name' => 'required|string|max:255', 'code' => 'nullable|string|max:50', 'is_active' => 'nullable|boolean']);
        Site::query()->create($payload + ['is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Site berhasil disimpan.');
    }

    public function updateSite(Request $request, Site $site): RedirectResponse
    {
        $payload = $request->validate(['name' => 'required|string|max:255', 'code' => 'nullable|string|max:50', 'is_active' => 'nullable|boolean']);
        $site->update($payload + ['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'Site berhasil diupdate.');
    }

    public function destroySite(Site $site): RedirectResponse
    {
        $site->delete();
        return back()->with('success', 'Site berhasil dihapus.');
    }

    public function meetingTypes(): View
    {
        return view('sid-meeting.master-meeting-types', ['rows' => MeetingType::query()->orderBy('name')->get()]);
    }

    public function storeMeetingType(Request $request): RedirectResponse
    {
        $payload = $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:255', 'is_active' => 'nullable|boolean']);
        MeetingType::query()->create($payload + ['is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Jenis meeting berhasil disimpan.');
    }

    public function updateMeetingType(Request $request, MeetingType $meetingType): RedirectResponse
    {
        $payload = $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:255', 'is_active' => 'nullable|boolean']);
        $meetingType->update($payload + ['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'Jenis meeting berhasil diupdate.');
    }

    public function destroyMeetingType(MeetingType $meetingType): RedirectResponse
    {
        $meetingType->delete();
        return back()->with('success', 'Jenis meeting berhasil dihapus.');
    }

    public function companies(): View
    {
        $rows = Company::query()->with('sites')->orderBy('name')->get();
        return view('sid-meeting.master-companies', ['rows' => $rows, 'sites' => Site::query()->orderBy('name')->get()]);
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        $payload = $request->validate(['name' => 'required|string|max:255', 'code' => 'nullable|string|max:50', 'is_active' => 'nullable|boolean', 'site_ids' => 'array']);
        $company = Company::query()->create($payload + ['is_active' => $request->boolean('is_active', true)]);
        $syncData = collect($request->input('site_ids', []))->mapWithKeys(fn ($siteId): array => [$siteId => ['is_required' => true]])->all();
        $company->sites()->sync($syncData);
        return back()->with('success', 'Perusahaan berhasil disimpan.');
    }

    public function updateCompany(Request $request, Company $company): RedirectResponse
    {
        $payload = $request->validate(['name' => 'required|string|max:255', 'code' => 'nullable|string|max:50', 'is_active' => 'nullable|boolean', 'site_ids' => 'array']);
        $company->update($payload + ['is_active' => $request->boolean('is_active')]);
        $syncData = collect($request->input('site_ids', []))->mapWithKeys(fn ($siteId): array => [$siteId => ['is_required' => true]])->all();
        $company->sites()->sync($syncData);
        return back()->with('success', 'Perusahaan berhasil diupdate.');
    }

    public function destroyCompany(Company $company): RedirectResponse
    {
        $company->delete();
        return back()->with('success', 'Perusahaan berhasil dihapus.');
    }

    public function employees(Request $request): View
    {
        $query = Employee::query()->with('company');
        if ($term = $request->string('q')->toString()) {
            $query->where(function ($inner) use ($term): void {
                $inner->where('kode_sid', 'like', '%' . $term . '%')
                    ->orWhere('nama', 'like', '%' . $term . '%')
                    ->orWhereHas('company', fn ($q) => $q->where('name', 'like', '%' . $term . '%'));
            });
        }
        return view('sid-meeting.master-employees', ['rows' => $query->latest()->paginate(20), 'companies' => Company::query()->orderBy('name')->get()]);
    }

    public function storeEmployee(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'kode_sid' => 'required|string|max:50|unique:employees,kode_sid',
            'nama' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'jabatan_struktural' => 'nullable|string|max:255',
            'jabatan_fungsional' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);
        Employee::query()->create($payload + ['is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Employee berhasil disimpan.');
    }

    public function updateEmployee(Request $request, Employee $employee): RedirectResponse
    {
        $payload = $request->validate([
            'kode_sid' => 'required|string|max:50|unique:employees,kode_sid,' . $employee->id,
            'nama' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
            'jabatan_struktural' => 'nullable|string|max:255',
            'jabatan_fungsional' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);
        $employee->update($payload + ['is_active' => $request->boolean('is_active')]);
        return back()->with('success', 'Employee berhasil diupdate.');
    }

    public function destroyEmployee(Employee $employee): RedirectResponse
    {
        $employee->delete();
        return back()->with('success', 'Employee berhasil dihapus.');
    }

    public function events(Request $request): View
    {
        $query = Event::query()->with(['site', 'meetingType'])->latest('meeting_date');
        foreach (['site_id', 'week', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($search = $request->string('q')->toString()) {
            $query->where('event_code', 'like', '%' . $search . '%');
        }

        return view('sid-meeting.events', [
            'rows' => $query->paginate(20),
            'sites' => Site::query()->orderBy('name')->get(),
            'meetingTypes' => MeetingType::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'meeting_type_id' => 'required|exists:meeting_types,id',
            'site_id' => 'required|exists:sites,id',
            'meeting_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'status' => 'nullable|string',
        ]);

        $event = $this->createEventWithUniqueCode(function (string $eventCode) use ($payload): Event {
            return Event::query()->create([
                ...$payload,
                'week' => Carbon::parse($payload['meeting_date'])->format('o-\WW'),
                'event_code' => $eventCode,
                'qr_token' => (string) Str::uuid(),
                'status' => $payload['status'] ?? 'draft',
                'created_by' => auth()->id(),
            ]);
        }, $payload['meeting_date']);

        return back()->with('success', 'Event berhasil dibuat.');
    }

    public function showEvent(Event $event): View
    {
        $event->load(['site', 'meetingType', 'attendances', 'eventMinute.issues']);
        $eligibleCompanies = Company::query()->whereHas('sites', fn ($q) => $q->where('sites.id', $event->site_id))->get();
        $attendedCompanyNames = $event->attendances->pluck('perusahaan_snapshot')->unique();
        return view('sid-meeting.event-detail', [
            'event' => $event,
            'employees' => Employee::query()->where('is_active', true)->with('company')->orderBy('nama')->get(),
            'eligibleCompanies' => $eligibleCompanies,
            'attendedCompanyNames' => $attendedCompanyNames,
        ]);
    }

    public function closeEvent(Event $event): RedirectResponse
    {
        $event->update(['status' => 'closed', 'closed_at' => now()]);
        return back()->with('success', 'Event berhasil ditutup.');
    }

    public function manualAttendance(Request $request, Event $event): RedirectResponse
    {
        $request->validate(['kode_sid' => 'required|string']);
        $employee = Employee::query()->with('company')->where('kode_sid', $request->input('kode_sid'))->where('is_active', true)->first();
        if (!$employee) {
            return back()->with('error', 'SID tidak ditemukan.');
        }
        $duplicate = Attendance::query()->where('event_id', $event->id)->where('employee_id', $employee->id)->exists();
        if ($duplicate) {
            return back()->with('error', 'SID sudah hadir pada event ini.');
        }
        Attendance::query()->create([
            'event_id' => $event->id,
            'employee_id' => $employee->id,
            'kode_sid' => $employee->kode_sid,
            'nama_snapshot' => $employee->nama,
            'perusahaan_snapshot' => $employee->company->name,
            'jabatan_struktural_snapshot' => $employee->jabatan_struktural,
            'jabatan_fungsional_snapshot' => $employee->jabatan_fungsional,
            'attended_at' => now(),
            'input_method' => 'manual',
        ]);

        return back()->with('success', 'Absensi manual berhasil disimpan.');
    }

    public function saveMinutes(Request $request, Event $event): RedirectResponse
    {
        $payload = $request->validate(['title' => 'nullable|string|max:255', 'notulis' => 'nullable|string|max:255', 'location' => 'nullable|string|max:255']);
        $minute = EventMinute::query()->updateOrCreate(['event_id' => $event->id], [...$payload, 'updated_by' => auth()->id()]);
        return redirect()->route('sid-meeting.events.show', $event)->with('success', 'Header notulensi disimpan.');
    }

    public function storeIssue(Request $request, Event $event): RedirectResponse
    {
        $minute = EventMinute::query()->firstOrCreate(['event_id' => $event->id], ['updated_by' => auth()->id()]);
        $payload = $request->validate([
            'section' => 'required|in:enviro,safety,general',
            'nomor' => 'required|integer|min:1',
            'catatan_meeting' => 'required|string',
            'issued_by' => 'nullable|string|max:255',
            'pic' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
            'status' => 'required|in:Open,Progress,Closed,Overdue',
            'keterangan' => 'nullable|string',
        ]);
        $minute->issues()->create($payload);
        return back()->with('success', 'Issue notulen berhasil disimpan.');
    }

    public function deleteIssue(MinuteIssue $issue): RedirectResponse
    {
        $issue->delete();
        return back()->with('success', 'Issue berhasil dihapus.');
    }

    public function reports(Request $request): View
    {
        $attendanceQuery = Attendance::query()->with(['event.site', 'event.meetingType', 'employee.company']);
        if ($request->filled('site_id')) {
            $attendanceQuery->whereHas('event', fn ($q) => $q->where('site_id', $request->input('site_id')));
        }
        if ($request->filled('week')) {
            $attendanceQuery->whereHas('event', fn ($q) => $q->where('week', $request->input('week')));
        }
        $attendanceRows = $attendanceQuery->latest('attended_at')->paginate(20, ['*'], 'attendance_page');

        $issuesQuery = MinuteIssue::query()->with(['eventMinute.event.site', 'eventMinute.event.meetingType']);
        if ($request->filled('section')) {
            $issuesQuery->where('section', $request->input('section'));
        }
        if ($request->filled('status')) {
            $issuesQuery->where('status', $request->input('status'));
        }
        $issueRows = $issuesQuery->latest()->paginate(20, ['*'], 'issues_page');

        return view('sid-meeting.reports', ['attendanceRows' => $attendanceRows, 'issueRows' => $issueRows, 'sites' => Site::query()->orderBy('name')->get()]);
    }

    public function exportAttendanceCsv(Request $request): StreamedResponse
    {
        $rows = Attendance::query()->with(['event.site', 'event.meetingType', 'employee.company'])->latest('attended_at')->get();
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tanggal', 'Week', 'Site', 'Jenis Meeting', 'Kode Event', 'Kode SID', 'Nama', 'Perusahaan', 'Jabatan Struktural', 'Jabatan Fungsional', 'Timestamp']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    optional($row->event)->meeting_date?->format('Y-m-d'),
                    optional($row->event)->week,
                    optional(optional($row->event)->site)->name,
                    optional(optional($row->event)->meetingType)->name,
                    optional($row->event)->event_code,
                    $row->kode_sid,
                    $row->nama_snapshot,
                    $row->perusahaan_snapshot,
                    $row->jabatan_struktural_snapshot,
                    $row->jabatan_fungsional_snapshot,
                    optional($row->attended_at)->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($out);
        }, 'attendance-export.csv');
    }

    public function exportMinutesCsv(): StreamedResponse
    {
        $rows = MinuteIssue::query()->with(['eventMinute.event.site', 'eventMinute.event.meetingType'])->latest()->get();
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tanggal', 'Week', 'Site', 'Jenis Meeting', 'Kode Event', 'Judul Notulen', 'Notulis', 'Lokasi', 'Section', 'No', 'Catatan Meeting', 'Issued By', 'PIC', 'Batas Waktu', 'Status', 'Keterangan', 'Updated At']);
            foreach ($rows as $row) {
                $event = optional($row->eventMinute)->event;
                fputcsv($out, [
                    optional($event?->meeting_date)->format('Y-m-d'),
                    $event?->week,
                    optional($event?->site)->name,
                    optional($event?->meetingType)->name,
                    $event?->event_code,
                    $row->eventMinute?->title,
                    $row->eventMinute?->notulis,
                    $row->eventMinute?->location,
                    $row->section,
                    $row->nomor,
                    $row->catatan_meeting,
                    $row->issued_by,
                    $row->pic,
                    optional($row->due_date)->format('Y-m-d'),
                    $row->computed_status,
                    $row->keterangan,
                    optional($row->updated_at)->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($out);
        }, 'minutes-export.csv');
    }

    public function performance(Request $request): View
    {
        $events = Event::query()->with(['site', 'attendances'])->get()->groupBy('week');
        $trend = $events->map(fn ($weekEvents, $week): array => [
            'week' => $week,
            'rate' => round($weekEvents->avg(fn (Event $event): float => $event->attendanceRate()), 2),
        ])->values();

        return view('sid-meeting.performance', [
            'trend' => $trend,
            'totalSite' => Site::query()->count(),
            'avgRate' => round($trend->avg('rate') ?? 0, 2),
            'weekCoverage' => $trend->count(),
            'companyRows' => $this->companyPerformanceRows(),
        ]);
    }

    public function semantic(Request $request, SemanticSimilarityService $service): View
    {
        $threshold = (float) $request->input('threshold', 55);
        $crossSiteOnly = $request->boolean('cross_site_only');
        $pairs = $service->getSimilarityPairs($threshold, $crossSiteOnly, $request->input('q'));
        $groups = $service->getRepeatedGroups($pairs);

        return view('sid-meeting.semantic', [
            'threshold' => $threshold,
            'crossSiteOnly' => $crossSiteOnly,
            'pairs' => $pairs,
            'groups' => $groups,
            'summary' => [
                'total_issue' => MinuteIssue::query()->count(),
                'similar_pairs' => $pairs->count(),
                'repeated_groups' => $groups->count(),
                'cross_site_pairs' => $pairs->where('cross_site', true)->count(),
            ],
        ]);
    }

    public function exportSimilarityCsv(Request $request, SemanticSimilarityService $service): StreamedResponse
    {
        $pairs = $service->getSimilarityPairs((float) $request->input('threshold', 55), $request->boolean('cross_site_only'));
        return response()->streamDownload(function () use ($pairs): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Similarity', 'Level', 'Site A', 'Issue A', 'Site B', 'Issue B', 'Action Signal']);
            foreach ($pairs as $pair) {
                fputcsv($out, [$pair['similarity'], $pair['level'], $pair['site_a'], $pair['issue_a'], $pair['site_b'], $pair['issue_b'], $pair['action_signal']]);
            }
            fclose($out);
        }, 'similarity-export.csv');
    }

    public function apiFormOptions(): JsonResponse
    {
        $sites = Site::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Site $site): array => ['id' => $site->id, 'name' => $site->name])
            ->values();

        $meetingTypes = MeetingType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (MeetingType $type): array => ['id' => $type->id, 'name' => $type->name])
            ->values();

        return response()->json([
            'sites' => $sites,
            'meetingTypes' => $meetingTypes,
        ]);
    }

    public function apiStats(): JsonResponse
    {
        $totalEvents = Event::query()->count();
        $activeEvents = Event::query()->runtimeActive()->count();
        $totalAttendance = Attendance::query()->count();
        $eventsWithAttendance = Attendance::query()->distinct('event_id')->count('event_id');
        $attendanceRate = $totalEvents > 0
            ? (int) round(($eventsWithAttendance / $totalEvents) * 100)
            : 0;

        return response()->json([
            'totalEvents' => $totalEvents,
            'activeEvents' => $activeEvents,
            'totalAttendance' => $totalAttendance,
            'attendanceRate' => $attendanceRate,
        ]);
    }

    public function apiToggleCompanySite(Request $request, Company $company): JsonResponse
    {
        $payload = $request->validate([
            'site' => 'required|string|max:255',
            'checked' => 'required|boolean',
        ]);

        $site = Site::query()->where('name', $payload['site'])->first();
        if (!$site) {
            return response()->json(['ok' => false, 'message' => 'Site tidak ditemukan'], 422);
        }

        if ($request->boolean('checked')) {
            $company->sites()->syncWithoutDetaching([$site->id => ['is_required' => true]]);
        } else {
            $company->sites()->detach($site->id);
        }

        return response()->json(['ok' => true]);
    }

    public function apiEventsList(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 50) {
            $perPage = 20;
        }

        $search = trim((string) $request->input('q', ''));
        $listMode = $request->input('list_mode', 'active') === 'inactive' ? 'inactive' : 'active';

        $query = Event::query()
            ->with(['site:id,name', 'meetingType:id,name'])
            ->withCount('attendances');

        if ($listMode === 'active') {
            $query->runtimeActive();
        } else {
            $query->runtimeInactive();
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('event_code', 'like', '%' . $search . '%')
                    ->orWhere('week', 'like', '%' . $search . '%')
                    ->orWhereHas('site', fn ($sq) => $sq->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('meetingType', fn ($sq) => $sq->where('name', 'like', '%' . $search . '%'));
            });
        }

        $paginator = $query->orderByDesc('meeting_date')->paginate($perPage, ['*'], 'page', $page);

        $data = $paginator->getCollection()->map(function (Event $event): array {
            $legacy = $this->mapEventToLegacy($event, false);

            return array_merge($legacy, [
                'attendanceCount' => (int) $event->attendances_count,
            ]);
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function apiCompaniesOptions(): JsonResponse
    {
        $companies = Company::query()
            ->with('sites:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (Company $company): array {
                return [
                    'id' => 'COMP-' . $company->id,
                    'name' => $company->name,
                    'sites' => $company->sites->pluck('name')->values()->all(),
                    'createdAt' => optional($company->created_at)->toIso8601String(),
                    'updatedAt' => optional($company->updated_at)->toIso8601String(),
                ];
            })
            ->values();

        return response()->json(['companies' => $companies]);
    }

    public function apiEventsData(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 0);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length < 1 || $length > 100) {
            $length = 15;
        }

        $search = trim((string) ($request->input('search.value') ?? ''));
        $listMode = $request->input('list_mode', 'active') === 'inactive' ? 'inactive' : 'active';
        $orderColIndex = (int) $request->input('order.0.column', 3);
        $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $orderColumns = [
            0 => 'events.event_code',
            1 => 'sites.name',
            2 => 'meeting_types.name',
            3 => 'events.meeting_date',
            4 => 'events.week',
            5 => 'events.status',
            6 => 'attendances_count',
        ];
        $orderBy = $orderColumns[$orderColIndex] ?? 'events.meeting_date';

        $baseQuery = Event::query()
            ->with(['site:id,name', 'meetingType:id,name'])
            ->withCount('attendances')
            ->leftJoin('sites', 'sites.id', '=', 'events.site_id')
            ->leftJoin('meeting_types', 'meeting_types.id', '=', 'events.meeting_type_id')
            ->select('events.*');

        if ($listMode === 'active') {
            $baseQuery->runtimeActive();
        } else {
            $baseQuery->runtimeInactive();
        }

        $recordsTotal = Event::query()
            ->when($listMode === 'active', fn ($q) => $q->runtimeActive())
            ->when($listMode === 'inactive', fn ($q) => $q->runtimeInactive())
            ->count();

        $filteredQuery = clone $baseQuery;
        if ($search !== '') {
            $filteredQuery->where(function ($q) use ($search): void {
                $q->where('events.event_code', 'like', '%' . $search . '%')
                    ->orWhere('events.week', 'like', '%' . $search . '%')
                    ->orWhere('sites.name', 'like', '%' . $search . '%')
                    ->orWhere('meeting_types.name', 'like', '%' . $search . '%');
            });
        }

        $recordsFiltered = (clone $filteredQuery)->count('events.id');
        $items = (clone $filteredQuery)
            ->orderBy($orderBy, $orderDir)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $items->map(function (Event $event): array {
            $runtimeStatus = $event->runtimeStatus();
            $qrLink = url('/attendance/' . $event->qr_token);
            $statusClass = match ($runtimeStatus) {
                'Open' => 'badge-open',
                'Overrun' => 'badge-overrun',
                'Closed' => 'badge-closed',
                'Upcoming' => 'badge-upcoming',
                default => 'badge-draft',
            };
            $statusLabel = match ($runtimeStatus) {
                'Closed' => 'Meeting Ditutup',
                'Overrun' => 'Lewat Jam Selesai',
                'Open' => 'QR Aktif',
                'Upcoming' => 'Belum Mulai',
                default => 'Draft',
            };

            return [
                'id' => (string) $event->id,
                'event_code' => $event->event_code,
                'site' => $event->site?->name ?? '-',
                'meeting_type' => $event->meetingType?->name ?? '-',
                'meeting_date' => optional($event->meeting_date)->format('Y-m-d'),
                'week' => $event->week,
                'start_time' => substr((string) $event->start_time, 0, 5),
                'end_time' => substr((string) $event->end_time, 0, 5),
                'status' => $runtimeStatus,
                'status_badge' => '<span class="badge ' . $statusClass . '">' . e($statusLabel) . '</span>',
                'attendance_count' => (int) ($event->attendances_count ?? 0),
                'qr_link' => $qrLink,
                'actions' => $this->buildEventActionsHtml($event, $runtimeStatus, $qrLink),
            ];
        })->values()->all();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function apiEventDetail(Event $event): JsonResponse
    {
        $event->load(['site', 'meetingType', 'eventMinute.issues', 'attendances.employee.company']);
        $event->loadCount('attendances');

        $eligibleCompanies = Company::query()
            ->whereHas('sites', fn ($q) => $q->where('sites.id', $event->site_id))
            ->orderBy('name')
            ->pluck('name')
            ->values();

        return response()->json([
            'event' => array_merge($this->mapEventToLegacy($event, true), [
                'attendanceCount' => (int) $event->attendances_count,
            ]),
            'eligibleCompanies' => $eligibleCompanies,
            'attendance' => $event->attendances->map(function (Attendance $row): array {
                return [
                    'id' => (string) $row->id,
                    'eventId' => (string) $row->event_id,
                    'sid' => $row->kode_sid,
                    'name' => $row->nama_snapshot,
                    'company' => $row->perusahaan_snapshot,
                    'structuralPosition' => $row->jabatan_struktural_snapshot,
                    'functionalPosition' => $row->jabatan_fungsional_snapshot,
                    'timestamp' => optional($row->attended_at)->toIso8601String(),
                    'source' => strtoupper($row->input_method),
                    'employeeId' => (string) $row->employee_id,
                ];
            })->values(),
        ]);
    }

    public function apiCompaniesData(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 0);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length < 1 || $length > 100) {
            $length = 25;
        }

        $search = trim((string) ($request->input('search.value') ?? ''));
        $orderColIndex = (int) $request->input('order.0.column', 0);
        $orderDir = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderBy = $orderColIndex === 1 ? 'sites_count' : 'companies.name';

        $query = Company::query()->with('sites:id,name');
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('sites', fn ($sq) => $sq->where('name', 'like', '%' . $search . '%'));
            });
        }

        $recordsTotal = Company::query()->count();
        $recordsFiltered = (clone $query)->count();
        $items = $query->orderBy($orderBy === 'sites_count' ? 'name' : $orderBy, $orderDir)
            ->skip($start)
            ->take($length)
            ->get();

        $siteNames = Site::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();

        $data = $items->map(function (Company $company) use ($siteNames): array {
            $companySites = $company->sites->pluck('name')->all();
            $siteCount = count($companySites);

            return [
                'id' => $company->id,
                'name' => '<div class="company-row-name">' . e($company->name) . '</div>'
                    . '<div class="company-row-meta">' . $siteCount . ' site eligible</div>',
                'site_cells' => collect($siteNames)->map(function (string $site) use ($company, $companySites): string {
                    $isChecked = in_array($site, $companySites, true);
                    $checked = $isChecked ? 'checked' : '';
                    $activeClass = $isChecked ? ' is-checked' : '';
                    $siteJson = htmlspecialchars(json_encode($site), ENT_QUOTES, 'UTF-8');

                    return '<label class="company-site-toggle' . $activeClass . '" title="' . e($site) . '">'
                        . '<input type="checkbox" ' . $checked . ' onchange="toggleCompanySiteDb(' . (int) $company->id . ', ' . $siteJson . ', this.checked); this.closest(\'.company-site-toggle\')?.classList.toggle(\'is-checked\', this.checked)" />'
                        . '<span class="company-site-toggle-ui" aria-hidden="true"></span>'
                        . '</label>';
                })->all(),
                'sites_list' => implode(', ', $companySites),
                'actions' => '<div class="flex flex-col items-stretch justify-center gap-1.5 sm:flex-row sm:items-center">'
                    . '<button type="button" onclick="editCompanyDb(' . (int) $company->id . ', ' . htmlspecialchars(json_encode($company->name), ENT_QUOTES, 'UTF-8') . ')" class="company-action-btn company-action-btn-edit">Edit</button>'
                    . '<button type="button" onclick="deleteCompanyDb(' . (int) $company->id . ')" class="company-action-btn company-action-btn-delete">Hapus</button>'
                    . '</div>',
            ];
        })->values()->all();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'siteColumns' => $siteNames,
        ]);
    }

    public function apiReportFilters(): JsonResponse
    {
        $sites = Site::query()->where('is_active', true)->orderBy('name')->pluck('name')->values();
        $weeks = Event::query()->whereNotNull('week')->distinct()->orderByDesc('week')->pluck('week')->filter()->values();

        return response()->json([
            'sites' => $sites,
            'weeks' => $weeks,
        ]);
    }

    public function apiReportAttendanceData(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 0);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        if ($length < 1 || $length > 100) {
            $length = 25;
        }

        $site = (string) $request->input('site', 'ALL');
        $week = (string) $request->input('week', 'ALL');
        $search = trim((string) ($request->input('search.value') ?? $request->input('q', '')));
        $orderColIndex = (int) $request->input('order.0.column', 10);
        $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $orderColumns = [
            0 => 'events.meeting_date',
            1 => 'events.week',
            2 => 'sites.name',
            3 => 'meeting_types.name',
            4 => 'events.event_code',
            5 => 'attendances.kode_sid',
            6 => 'attendances.nama_snapshot',
            7 => 'attendances.perusahaan_snapshot',
            8 => 'attendances.jabatan_struktural_snapshot',
            9 => 'attendances.jabatan_fungsional_snapshot',
            10 => 'attendances.attended_at',
        ];
        $orderBy = $orderColumns[$orderColIndex] ?? 'attendances.attended_at';

        $baseQuery = Attendance::query()
            ->join('events', 'events.id', '=', 'attendances.event_id')
            ->leftJoin('sites', 'sites.id', '=', 'events.site_id')
            ->leftJoin('meeting_types', 'meeting_types.id', '=', 'events.meeting_type_id');

        if ($site !== '' && $site !== 'ALL') {
            $baseQuery->where('sites.name', $site);
        }
        if ($week !== '' && $week !== 'ALL') {
            $baseQuery->where('events.week', $week);
        }

        $recordsTotal = Attendance::query()->count();

        $filteredQuery = clone $baseQuery;
        if ($search !== '') {
            $filteredQuery->where(function ($q) use ($search): void {
                $q->where('attendances.kode_sid', 'like', '%' . $search . '%')
                    ->orWhere('attendances.nama_snapshot', 'like', '%' . $search . '%')
                    ->orWhere('attendances.perusahaan_snapshot', 'like', '%' . $search . '%')
                    ->orWhere('attendances.jabatan_struktural_snapshot', 'like', '%' . $search . '%')
                    ->orWhere('attendances.jabatan_fungsional_snapshot', 'like', '%' . $search . '%')
                    ->orWhere('events.event_code', 'like', '%' . $search . '%')
                    ->orWhere('sites.name', 'like', '%' . $search . '%')
                    ->orWhere('meeting_types.name', 'like', '%' . $search . '%')
                    ->orWhere('events.week', 'like', '%' . $search . '%');
            });
        }

        $recordsFiltered = (clone $filteredQuery)->count('attendances.id');
        $items = (clone $filteredQuery)
            ->select([
                'attendances.*',
                'events.id as event_id',
                'events.event_code',
                'events.meeting_date',
                'events.week',
                'sites.name as site_name',
                'meeting_types.name as meeting_type_name',
            ])
            ->orderBy($orderBy, $orderDir)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $items->map(function ($row): array {
            $eventId = (string) $row->event_id;
            $meetingDate = $row->meeting_date instanceof Carbon
                ? $row->meeting_date->format('Y-m-d')
                : (string) $row->meeting_date;
            $attendedAt = $row->attended_at instanceof Carbon
                ? $row->attended_at->format('Y-m-d H:i:s')
                : (string) $row->attended_at;

            return [
                'event_id' => $eventId,
                'tanggal_meeting' => $meetingDate,
                'week' => $row->week ?? '-',
                'site' => $row->site_name ?? '-',
                'jenis_meeting' => $row->meeting_type_name ?? '-',
                'kode_event' => $row->event_code ?? '-',
                'kode_sid' => $row->kode_sid ?? '-',
                'nama' => $row->nama_snapshot ?? '-',
                'perusahaan' => $row->perusahaan_snapshot ?? '-',
                'jabatan_struktural' => $row->jabatan_struktural_snapshot ?? '-',
                'jabatan_fungsional' => $row->jabatan_fungsional_snapshot ?? '-',
                'timestamp' => $attendedAt,
            ];
        })->values()->all();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function apiReportMinutesData(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 0);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        if ($length < 1 || $length > 100) {
            $length = 25;
        }

        $site = (string) $request->input('site', 'ALL');
        $week = (string) $request->input('week', 'ALL');
        $search = trim((string) ($request->input('search.value') ?? $request->input('q', '')));
        $orderColIndex = (int) $request->input('order.0.column', 16);
        $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $orderColumns = [
            0 => 'events.meeting_date',
            1 => 'events.week',
            2 => 'sites.name',
            3 => 'meeting_types.name',
            4 => 'events.event_code',
            5 => 'event_minutes.title',
            6 => 'event_minutes.notulis',
            7 => 'event_minutes.location',
            8 => 'minute_issues.section',
            9 => 'minute_issues.nomor',
            10 => 'minute_issues.catatan_meeting',
            11 => 'minute_issues.issued_by',
            12 => 'minute_issues.pic',
            13 => 'minute_issues.due_date',
            14 => 'minute_issues.status',
            15 => 'minute_issues.keterangan',
            16 => 'minute_issues.updated_at',
        ];
        $orderBy = $orderColumns[$orderColIndex] ?? 'minute_issues.updated_at';

        $baseQuery = MinuteIssue::query()
            ->join('event_minutes', 'event_minutes.id', '=', 'minute_issues.event_minute_id')
            ->join('events', 'events.id', '=', 'event_minutes.event_id')
            ->leftJoin('sites', 'sites.id', '=', 'events.site_id')
            ->leftJoin('meeting_types', 'meeting_types.id', '=', 'events.meeting_type_id');

        if ($site !== '' && $site !== 'ALL') {
            $baseQuery->where('sites.name', $site);
        }
        if ($week !== '' && $week !== 'ALL') {
            $baseQuery->where('events.week', $week);
        }

        $recordsTotal = MinuteIssue::query()->count();

        $filteredQuery = clone $baseQuery;
        if ($search !== '') {
            $filteredQuery->where(function ($q) use ($search): void {
                $q->where('minute_issues.catatan_meeting', 'like', '%' . $search . '%')
                    ->orWhere('minute_issues.issued_by', 'like', '%' . $search . '%')
                    ->orWhere('minute_issues.pic', 'like', '%' . $search . '%')
                    ->orWhere('minute_issues.keterangan', 'like', '%' . $search . '%')
                    ->orWhere('event_minutes.title', 'like', '%' . $search . '%')
                    ->orWhere('event_minutes.notulis', 'like', '%' . $search . '%')
                    ->orWhere('events.event_code', 'like', '%' . $search . '%')
                    ->orWhere('sites.name', 'like', '%' . $search . '%')
                    ->orWhere('meeting_types.name', 'like', '%' . $search . '%');
            });
        }

        $recordsFiltered = (clone $filteredQuery)->count('minute_issues.id');
        $items = (clone $filteredQuery)
            ->select([
                'minute_issues.*',
                'events.id as event_id',
                'events.event_code',
                'events.meeting_date',
                'events.week',
                'sites.name as site_name',
                'meeting_types.name as meeting_type_name',
                'event_minutes.title as minute_title',
                'event_minutes.notulis as minute_notulis',
                'event_minutes.location as minute_location',
                'event_minutes.updated_at as minute_updated_at',
                'event_minutes.issue_sections as minute_issue_sections',
            ])
            ->orderBy($orderBy, $orderDir)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $items->map(function ($row): array {
            $issueSections = is_string($row->minute_issue_sections ?? null)
                ? json_decode($row->minute_issue_sections, true)
                : ($row->minute_issue_sections ?? null);
            $sectionLabel = $this->resolveIssueSectionTitle(is_array($issueSections) ? $issueSections : null, (string) $row->section);
            $meetingDate = $row->meeting_date instanceof Carbon
                ? $row->meeting_date->format('Y-m-d')
                : (string) $row->meeting_date;
            $updatedAt = $row->updated_at instanceof Carbon
                ? $row->updated_at->format('Y-m-d H:i:s')
                : (string) ($row->minute_updated_at ?? '');

            return [
                'event_id' => (string) $row->event_id,
                'tanggal_meeting' => $meetingDate,
                'week' => $row->week ?? '-',
                'site' => $row->site_name ?? '-',
                'jenis_meeting' => $row->meeting_type_name ?? '-',
                'kode_event' => $row->event_code ?? '-',
                'judul_notulen' => $row->minute_title ?? '-',
                'notulis' => $row->minute_notulis ?? '-',
                'lokasi' => $row->minute_location ?? '-',
                'section' => $sectionLabel,
                'no' => (int) $row->nomor,
                'catatan_meeting' => $row->catatan_meeting ?? '-',
                'issued_by' => $row->issued_by ?? '-',
                'pic' => $row->pic ?? '-',
                'batas_waktu' => $row->due_date instanceof Carbon ? $row->due_date->format('Y-m-d') : ($row->due_date ?? ''),
                'status_catatan' => $row->status ?? 'Open',
                'keterangan' => $row->keterangan ?? '-',
                'updated_at' => $updatedAt,
            ];
        })->values()->all();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function apiBootstrap(): JsonResponse
    {
        $events = Event::query()
            ->with(['site', 'meetingType'])
            ->orderByDesc('meeting_date')
            ->limit(500)
            ->get();

        $attendance = Attendance::query()
            ->with(['event:id,event_code', 'employee:id,kode_sid,nama'])
            ->orderByDesc('attended_at')
            ->get()
            ->map(function (Attendance $row): array {
                return [
                    'id' => (string) $row->id,
                    'eventId' => (string) $row->event_id,
                    'sid' => $row->kode_sid,
                    'name' => $row->nama_snapshot,
                    'company' => $row->perusahaan_snapshot,
                    'structuralPosition' => $row->jabatan_struktural_snapshot,
                    'functionalPosition' => $row->jabatan_fungsional_snapshot,
                    'timestamp' => optional($row->attended_at)->toIso8601String(),
                    'source' => strtoupper($row->input_method),
                    'employeeId' => (string) $row->employee_id,
                ];
            })
            ->values();

        $companies = Company::query()->with('sites:id,name')->orderBy('name')->get()->map(function (Company $company): array {
            return [
                'id' => 'COMP-' . $company->id,
                'name' => $company->name,
                'sites' => $company->sites->pluck('name')->values()->all(),
                'createdAt' => optional($company->created_at)->toIso8601String(),
                'updatedAt' => optional($company->updated_at)->toIso8601String(),
            ];
        })->values();

        $meetingTypes = MeetingType::query()->where('is_active', true)->orderBy('name')->pluck('name')->values();

        $eventRows = $events->map(fn (Event $event): array => $this->mapEventToLegacy($event, false))->values();

        return response()->json([
            'events' => $eventRows,
            'attendance' => $attendance,
            'companies' => $companies,
            'meetingTypes' => $meetingTypes,
        ]);
    }

    public function apiStoreEvent(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'meeting_type' => 'required|string|max:255',
            'site' => 'required|string|max:255',
            'meeting_date' => 'required|date',
            'week' => 'required|string|max:16',
            'start_time' => 'required',
            'end_time' => 'required',
            'meeting_level' => 'nullable|in:site,company,department',
            'target_companies' => 'nullable|array',
            'target_companies.*' => 'string|max:255',
            'target_positions' => 'nullable|array',
            'target_positions.*' => 'string|max:255',
            'target_departments' => 'nullable|array',
            'target_departments.*' => 'string|max:255',
        ]);

        $meetingLevel = $payload['meeting_level'] ?? 'site';
        $targetCompanies = array_values(array_filter($payload['target_companies'] ?? []));
        $targetPositions = array_values(array_filter($payload['target_positions'] ?? []));
        $targetDepartments = array_values(array_filter($payload['target_departments'] ?? []));

        if ($meetingLevel !== 'site' && $targetCompanies === []) {
            return response()->json(['ok' => false, 'message' => 'Pilih minimal 1 perusahaan wajib hadir.'], 422);
        }
        if ($meetingLevel === 'company' && $targetPositions === []) {
            return response()->json(['ok' => false, 'message' => 'Pilih minimal 1 jabatan wajib hadir.'], 422);
        }
        if ($meetingLevel === 'department' && $targetDepartments === []) {
            return response()->json(['ok' => false, 'message' => 'Pilih minimal 1 department wajib hadir.'], 422);
        }

        $meetingType = MeetingType::query()->firstOrCreate(['name' => $payload['meeting_type']], ['is_active' => true]);
        $site = Site::query()->firstOrCreate(['name' => $payload['site']], ['is_active' => true]);

        $event = $this->createEventWithUniqueCode(function (string $eventCode) use ($payload, $meetingType, $site, $meetingLevel, $targetCompanies, $targetPositions, $targetDepartments): Event {
            return Event::query()->create([
                'event_code' => $eventCode,
                'qr_token' => (string) Str::uuid(),
                'meeting_type_id' => $meetingType->id,
                'site_id' => $site->id,
                'meeting_level' => $meetingLevel,
                'target_companies' => $meetingLevel === 'site' ? null : $targetCompanies,
                'target_positions' => $meetingLevel === 'company' ? $targetPositions : null,
                'target_departments' => $meetingLevel === 'department' ? $targetDepartments : null,
                'meeting_date' => $payload['meeting_date'],
                'week' => $payload['week'],
                'start_time' => $payload['start_time'],
                'end_time' => $payload['end_time'],
                'status' => 'open',
                'created_by' => auth()->id(),
            ]);
        }, $payload['meeting_date']);

        return response()->json(['ok' => true, 'id' => $event->id]);
    }

    public function apiStoreCompany(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => 'required|string|max:255',
            'sites' => 'array',
            'sites.*' => 'string|max:255',
        ]);

        $company = Company::query()->firstOrCreate(['name' => $payload['name']]);
        $siteIds = collect($payload['sites'] ?? [])
            ->map(fn (string $siteName): int => Site::query()->firstOrCreate(['name' => $siteName], ['is_active' => true])->id)
            ->unique()
            ->values();
        $company->sites()->sync($siteIds->mapWithKeys(fn ($siteId): array => [$siteId => ['is_required' => true]])->all());

        return response()->json(['ok' => true, 'id' => $company->id]);
    }

    public function apiStoreAttendance(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'event_id' => 'required|exists:events,id',
            'kode_sid' => 'required|string|max:50',
            'input_method' => 'nullable|in:qr,manual',
        ]);

        $event = Event::query()->findOrFail($payload['event_id']);
        $employee = Employee::query()->with('company')->where('kode_sid', $payload['kode_sid'])->where('is_active', true)->first();
        if (!$employee) {
            return response()->json(['ok' => false, 'message' => 'SID tidak ditemukan'], 422);
        }

        $exists = Attendance::query()->where('event_id', $event->id)->where('employee_id', $employee->id)->exists();
        if ($exists) {
            return response()->json(['ok' => false, 'message' => 'SID sudah melakukan absensi pada event ini'], 422);
        }

        Attendance::query()->create([
            'event_id' => $event->id,
            'employee_id' => $employee->id,
            'kode_sid' => $employee->kode_sid,
            'nama_snapshot' => $employee->nama,
            'perusahaan_snapshot' => optional($employee->company)->name,
            'jabatan_struktural_snapshot' => $employee->jabatan_struktural,
            'jabatan_fungsional_snapshot' => $employee->jabatan_fungsional,
            'attended_at' => now(),
            'input_method' => $payload['input_method'] ?? 'manual',
        ]);

        return response()->json(['ok' => true]);
    }

    public function apiSaveMinutes(Request $request, Event $event): JsonResponse
    {
        $payload = $request->validate([
            'title' => 'nullable|string|max:255',
            'notulis' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'meeting_type' => 'nullable|string|max:255',
            'meeting_date' => 'nullable|date',
            'issueSections' => 'nullable|array',
            'issueSections.*.id' => 'required_with:issueSections|string|max:64',
            'issueSections.*.title' => 'required_with:issueSections|string|max:255',
            'issueSections.*.defaultRows' => 'nullable|integer|min:1|max:50',
            'issues' => 'array',
            'issues.*.section' => 'required|string|max:64',
            'issues.*.nomor' => 'nullable|integer|min:1',
            'issues.*.note' => 'nullable|string',
            'issues.*.issuedBy' => 'nullable|string|max:255',
            'issues.*.pic' => 'nullable|string|max:255',
            'issues.*.dueDate' => 'nullable|date',
            'issues.*.status' => 'nullable|in:Open,Progress,Closed,Overdue',
            'issues.*.remark' => 'nullable|string',
        ]);

        DB::transaction(function () use ($event, $payload): void {
            $issueSections = collect($payload['issueSections'] ?? [])
                ->map(fn (array $section): array => [
                    'id' => trim((string) ($section['id'] ?? '')),
                    'title' => trim((string) ($section['title'] ?? '')),
                    'defaultRows' => max(1, (int) ($section['defaultRows'] ?? 3)),
                ])
                ->filter(fn (array $section): bool => $section['id'] !== '' && $section['title'] !== '')
                ->values()
                ->all();

            $minute = EventMinute::query()->updateOrCreate(
                ['event_id' => $event->id],
                [
                    'title' => $payload['title'] ?? null,
                    'notulis' => $payload['notulis'] ?? null,
                    'location' => $payload['location'] ?? null,
                    'issue_sections' => $issueSections !== [] ? $issueSections : null,
                    'updated_by' => auth()->id(),
                ]
            );

            if (filled($payload['meeting_type'] ?? null)) {
                $meetingType = MeetingType::query()->firstOrCreate(
                    ['name' => trim((string) $payload['meeting_type'])],
                    ['is_active' => true]
                );
                $event->update(['meeting_type_id' => $meetingType->id]);
            }

            if (filled($payload['meeting_date'] ?? null)) {
                $event->update(['meeting_date' => $payload['meeting_date']]);
            }

            if (! array_key_exists('issues', $payload)) {
                return;
            }

            $minute->issues()->delete();

            foreach ($payload['issues'] as $index => $issue) {
                $note = trim((string) ($issue['note'] ?? ''));
                $issuedBy = trim((string) ($issue['issuedBy'] ?? ''));
                $pic = trim((string) ($issue['pic'] ?? ''));
                $remark = trim((string) ($issue['remark'] ?? ''));
                $dueRaw = $issue['dueDate'] ?? null;
                $hasContent = $note !== ''
                    || $issuedBy !== ''
                    || $pic !== ''
                    || $remark !== ''
                    || filled($dueRaw);

                if (! $hasContent) {
                    continue;
                }

                $minute->issues()->create([
                    'section' => $issue['section'],
                    'nomor' => (int) ($issue['nomor'] ?? ($index + 1)),
                    'catatan_meeting' => $note !== '' ? $note : '—',
                    'issued_by' => $issuedBy !== '' ? $issuedBy : null,
                    'pic' => $pic !== '' ? $pic : null,
                    'due_date' => filled($dueRaw) ? $dueRaw : null,
                    'status' => $issue['status'] ?? 'Open',
                    'keterangan' => $remark !== '' ? $remark : null,
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Sinkronisasi legacy — hanya upsert, tanpa penghapusan massal event/absensi.
     * Notulensi gunakan endpoint khusus api.events.minutes.store.
     */
    public function apiSync(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'events' => 'array',
            'attendance' => 'array',
            'companies' => 'array',
            'meetingTypes' => 'array',
        ]);

        DB::transaction(function () use ($payload): void {
            $eventRows = collect($payload['events'] ?? []);
            $companyRows = collect($payload['companies'] ?? []);
            $attendanceRows = collect($payload['attendance'] ?? []);
            $meetingTypeRows = collect($payload['meetingTypes'] ?? []);

            $meetingTypeNames = $meetingTypeRows
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->merge($eventRows->map(fn ($row) => trim((string) ($row['meetingType'] ?? '')))->filter())
                ->unique()
                ->values();

            $meetingTypeMap = [];
            foreach ($meetingTypeNames as $name) {
                $meetingType = MeetingType::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
                $meetingTypeMap[$name] = $meetingType->id;
            }

            $siteNames = $eventRows->map(fn ($row) => trim((string) ($row['site'] ?? '')))->filter()
                ->merge($companyRows->flatMap(fn ($row) => collect($row['sites'] ?? [])->map(fn ($site) => trim((string) $site))->filter()))
                ->unique()
                ->values();
            $siteMap = [];
            foreach ($siteNames as $name) {
                $site = Site::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
                $siteMap[$name] = $site->id;
            }

            foreach ($companyRows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $company = Company::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
                $sync = collect($row['sites'] ?? [])->mapWithKeys(function ($siteName) use ($siteMap): array {
                    $siteId = $siteMap[trim((string) $siteName)] ?? null;

                    return $siteId ? [$siteId => ['is_required' => true]] : [];
                })->all();
                if ($sync !== []) {
                    $company->sites()->syncWithoutDetaching($sync);
                }
            }

            $eventIdMap = [];
            foreach ($eventRows as $row) {
                $eventCode = trim((string) ($row['code'] ?? ''));
                if ($eventCode === '') {
                    $eventCode = $this->generateEventCode((string) ($row['date'] ?? now()->toDateString()));
                }
                $meetingTypeName = trim((string) ($row['meetingType'] ?? ''));
                $siteName = trim((string) ($row['site'] ?? ''));
                $meetingTypeId = $meetingTypeMap[$meetingTypeName] ?? null;
                $siteId = $siteMap[$siteName] ?? null;
                if (! $meetingTypeId || ! $siteId) {
                    continue;
                }

                $statusText = strtolower((string) ($row['manualStatus'] ?? 'draft'));
                $status = match ($statusText) {
                    'closed' => 'closed',
                    'overrun' => 'overrun',
                    'open' => 'open',
                    'upcoming' => 'upcoming',
                    'expired' => 'expired',
                    default => 'draft',
                };

                $event = Event::query()->updateOrCreate(
                    ['event_code' => $eventCode],
                    [
                        'qr_token' => (string) ($row['qrToken'] ?? Str::uuid()),
                        'meeting_type_id' => $meetingTypeId,
                        'site_id' => $siteId,
                        'meeting_date' => $row['date'] ?? now()->toDateString(),
                        'week' => $row['week'] ?? Carbon::parse($row['date'] ?? now()->toDateString())->format('o-\WW'),
                        'start_time' => ($row['startTime'] ?? '08:00') . ':00',
                        'end_time' => ($row['endTime'] ?? '09:00') . ':00',
                        'status' => $status,
                        'closed_at' => $row['closedAt'] ?? null,
                        'created_by' => auth()->id(),
                    ]
                );

                $clientId = (string) ($row['id'] ?? $eventCode);
                $eventIdMap[$clientId] = $event->id;
            }

            foreach ($attendanceRows as $row) {
                $clientEventId = (string) ($row['eventId'] ?? '');
                $eventId = $eventIdMap[$clientEventId] ?? (is_numeric($clientEventId) ? (int) $clientEventId : null);
                if (! $eventId) {
                    continue;
                }

                $companyName = trim((string) ($row['company'] ?? 'Tidak Ada Perusahaan'));
                $company = Company::query()->firstOrCreate(['name' => $companyName], ['is_active' => true]);
                $sid = trim((string) ($row['sid'] ?? ''));
                if ($sid === '') {
                    continue;
                }
                $employee = Employee::query()->firstOrCreate(
                    ['kode_sid' => $sid],
                    [
                        'nama' => $row['name'] ?? $sid,
                        'company_id' => $company->id,
                        'jabatan_struktural' => $row['structuralPosition'] ?? null,
                        'jabatan_fungsional' => $row['functionalPosition'] ?? null,
                        'is_active' => true,
                    ]
                );

                Attendance::query()->updateOrCreate(
                    [
                        'event_id' => $eventId,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'kode_sid' => $employee->kode_sid,
                        'nama_snapshot' => $row['name'] ?? $employee->nama,
                        'perusahaan_snapshot' => $companyName,
                        'jabatan_struktural_snapshot' => $row['structuralPosition'] ?? $employee->jabatan_struktural,
                        'jabatan_fungsional_snapshot' => $row['functionalPosition'] ?? $employee->jabatan_fungsional,
                        'attended_at' => $row['timestamp'] ?? now()->toDateTimeString(),
                        'input_method' => strtolower((string) ($row['source'] ?? 'manual')) === 'qr' ? 'qr' : 'manual',
                    ]
                );
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Sinkronisasi aman selesai. Tidak ada penghapusan massal data.',
        ]);
    }

    public function apiStoreMeetingType(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $meetingType = MeetingType::query()->firstOrCreate(
            ['name' => trim($payload['name'])],
            ['is_active' => true]
        );

        if (! $meetingType->is_active) {
            $meetingType->update(['is_active' => true]);
        }

        return response()->json([
            'ok' => true,
            'meetingType' => [
                'id' => $meetingType->id,
                'name' => $meetingType->name,
            ],
        ]);
    }

    public function apiDestroyMeetingType(MeetingType $meetingType): JsonResponse
    {
        $inUse = Event::query()->where('meeting_type_id', $meetingType->id)->exists();
        if ($inUse) {
            $meetingType->update(['is_active' => false]);

            return response()->json([
                'ok' => true,
                'deactivated' => true,
                'message' => 'Jenis meeting dinonaktifkan karena masih dipakai event.',
            ]);
        }

        $meetingType->delete();

        return response()->json(['ok' => true]);
    }

    private function generateEventCode(string $meetingDate): string
    {
        $date = Carbon::parse($meetingDate)->format('Ymd');
        $prefix = "EV-{$date}-";
        $meetingDateOnly = Carbon::parse($meetingDate)->toDateString();

        return DB::transaction(function () use ($prefix, $date, $meetingDateOnly): string {
            $lastCode = Event::query()
                ->whereDate('meeting_date', $meetingDateOnly)
                ->where('event_code', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('event_code')
                ->value('event_code');

            $next = 1;
            if (is_string($lastCode) && str_starts_with($lastCode, $prefix)) {
                $suffix = (int) substr($lastCode, -4);
                $next = max(1, $suffix + 1);
            }

            return sprintf('EV-%s-%04d', $date, $next);
        }, 3);
    }

    /**
     * Buat event dengan event_code unik walaupun ada concurrent insert.
     */
    private function createEventWithUniqueCode(callable $creator, string $meetingDate): Event
    {
        $attempts = 6;
        for ($i = 0; $i < $attempts; $i++) {
            $eventCode = $this->generateEventCode($meetingDate);
            try {
                /** @var Event $event */
                $event = $creator($eventCode);
                return $event;
            } catch (\Illuminate\Database\QueryException $e) {
                $message = (string) $e->getMessage();
                $isDuplicate = $e->getCode() === '23000'
                    && str_contains($message, 'Duplicate entry')
                    && str_contains($message, 'events_event_code_unique');
                if ($isDuplicate && $i < $attempts - 1) {
                    usleep(40_000); // beri jeda kecil agar hitungan berikutnya bergeser
                    continue;
                }
                throw $e;
            }
        }

        // Seharusnya tidak pernah sampai sini.
        throw new \RuntimeException('Gagal membuat event_code unik setelah beberapa percobaan.');
    }

    private function companyPerformanceRows()
    {
        $companies = Company::query()->with(['employees.attendances.event'])->get();
        $rank = 1;
        return $companies->map(function (Company $company) use (&$rank): array {
            $totalAbsensi = Attendance::query()->where('perusahaan_snapshot', $company->name)->count();
            $expectedEvent = Event::query()->count();
            $eventHadir = Attendance::query()->where('perusahaan_snapshot', $company->name)->distinct('event_id')->count('event_id');
            $rate = $expectedEvent > 0 ? round(($eventHadir / $expectedEvent) * 100, 2) : 0;
            $label = $rate >= 90 ? 'Excellent' : ($rate >= 75 ? 'Good' : ($rate >= 50 ? 'Need Attention' : 'Critical'));
            return [
                'rank' => $rank++,
                'company' => $company->name,
                'expected_event' => $expectedEvent,
                'event_hadir' => $eventHadir,
                'total_absensi' => $totalAbsensi,
                'rate' => $rate,
                'label' => $label,
            ];
        });
    }

    private function resolveIssueSectionTitle(?array $issueSections, string $sectionId): string
    {
        $match = collect($issueSections ?? [])->first(fn (array $section): bool => ($section['id'] ?? '') === $sectionId);
        if (is_array($match) && filled($match['title'] ?? null)) {
            return (string) $match['title'];
        }

        return match ($sectionId) {
            'enviro' => 'Enviro Issue',
            'safety' => 'Safety Issue',
            'general' => 'General Issue',
            default => ucwords(str_replace('_', ' ', $sectionId)),
        };
    }

    private function mapMinutesToLegacy(?EventMinute $minute, ?Event $event = null): ?array
    {
        if (! $minute) {
            return null;
        }

        $event ??= $minute->event;
        $issues = collect($minute->issues ?? []);
        $issueSections = is_array($minute->issue_sections) && $minute->issue_sections !== []
            ? $minute->issue_sections
            : [
                ['id' => 'enviro', 'title' => 'Enviro Issue', 'defaultRows' => 3],
                ['id' => 'safety', 'title' => 'Safety Issue', 'defaultRows' => 3],
                ['id' => 'general', 'title' => 'General Issue', 'defaultRows' => 3],
            ];

        $rowsBySection = $issues->groupBy('section');
        $issueSectionsWithRows = collect($issueSections)->map(function (array $section) use ($rowsBySection): array {
            $id = (string) ($section['id'] ?? 'section');

            return [
                'id' => $id,
                'title' => (string) ($section['title'] ?? $id),
                'defaultRows' => max(1, (int) ($section['defaultRows'] ?? 3)),
                'rows' => ($rowsBySection->get($id) ?? collect())->values()->map(fn (MinuteIssue $issue): array => $this->mapIssueToLegacy($issue))->all(),
            ];
        })->values()->all();

        return [
            'meetingTitle' => $minute->title,
            'meetingType' => optional($event?->meetingType)->name,
            'meetingDate' => optional($event?->meeting_date)->format('Y-m-d'),
            'notulis' => $minute->notulis,
            'location' => $minute->location,
            'updatedAt' => optional($minute->updated_at)->toIso8601String(),
            'issueSections' => $issueSectionsWithRows,
            'enviroIssues' => $issues->where('section', 'enviro')->values()->map(fn (MinuteIssue $issue): array => $this->mapIssueToLegacy($issue))->all(),
            'safetyIssues' => $issues->where('section', 'safety')->values()->map(fn (MinuteIssue $issue): array => $this->mapIssueToLegacy($issue))->all(),
            'generalIssues' => $issues->where('section', 'general')->values()->map(fn (MinuteIssue $issue): array => $this->mapIssueToLegacy($issue))->all(),
        ];
    }

    private function mapIssueToLegacy(MinuteIssue $issue): array
    {
        return [
            'id' => (string) $issue->id,
            'section' => $issue->section,
            'note' => $issue->catatan_meeting,
            'issuedBy' => $issue->issued_by,
            'pic' => $issue->pic,
            'dueDate' => optional($issue->due_date)->format('Y-m-d'),
            'status' => $issue->status === 'Closed' ? 'Closed' : $issue->computed_status,
            'rawStatus' => $issue->status,
            'remark' => $issue->keterangan,
            'nomor' => $issue->nomor,
            'closedAt' => optional($issue->closed_at)->toIso8601String(),
            'closedBySid' => $issue->closed_by_sid,
            'closedByName' => $issue->closed_by_name,
        ];
    }

    public function apiEmployeeBySid(string $kodeSid): JsonResponse
    {
        $sid = strtoupper(trim($kodeSid));
        $employee = Employee::query()
            ->with('company')
            ->where('kode_sid', $sid)
            ->where('is_active', true)
            ->first();

        if (! $employee) {
            return response()->json(['ok' => false, 'message' => 'SID tidak ditemukan'], 404);
        }

        return response()->json([
            'ok' => true,
            'employee' => [
                'id' => (string) $employee->id,
                'sid' => $employee->kode_sid,
                'name' => $employee->nama,
                'company' => optional($employee->company)->name,
                'structuralPosition' => $employee->jabatan_struktural,
                'functionalPosition' => $employee->jabatan_fungsional,
            ],
        ]);
    }

    public function apiMinutesManagementList(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 50) {
            $perPage = 20;
        }

        $search = trim((string) $request->input('q', ''));
        $statusFilter = strtoupper((string) $request->input('status', 'ALL'));

        $query = EventMinute::query()
            ->with(['event.site', 'event.meetingType'])
            ->whereHas('event')
            ->withCount('issues')
            ->withCount([
                'issues as open_issues_count' => fn ($q) => $q->where('status', '!=', 'Closed'),
                'issues as closed_issues_count' => fn ($q) => $q->where('status', 'Closed'),
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('notulis', 'like', '%' . $search . '%')
                    ->orWhere('location', 'like', '%' . $search . '%')
                    ->orWhereHas('event', fn ($eq) => $eq->where('event_code', 'like', '%' . $search . '%')
                        ->orWhere('week', 'like', '%' . $search . '%')
                        ->orWhereHas('site', fn ($sq) => $sq->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('meetingType', fn ($mq) => $mq->where('name', 'like', '%' . $search . '%')));
            });
        }

        if ($statusFilter === 'OPEN') {
            $query->whereHas('issues', fn ($q) => $q->where('status', '!=', 'Closed'));
        } elseif ($statusFilter === 'CLOSED') {
            $query->whereHas('issues', fn ($q) => $q->where('status', 'Closed'))
                ->whereDoesntHave('issues', fn ($q) => $q->where('status', '!=', 'Closed'));
        }

        $paginator = $query->orderByDesc('updated_at')->paginate($perPage, ['*'], 'page', $page);

        $data = $paginator->getCollection()->map(function (EventMinute $minute): array {
            $event = $minute->event;

            return [
                'eventId' => (string) $event->id,
                'code' => $event->event_code,
                'meetingType' => optional($event->meetingType)->name,
                'site' => optional($event->site)->name,
                'date' => optional($event->meeting_date)->format('Y-m-d'),
                'week' => $event->week,
                'title' => $minute->title,
                'notulis' => $minute->notulis,
                'location' => $minute->location,
                'issuesCount' => (int) $minute->issues_count,
                'openIssuesCount' => (int) $minute->open_issues_count,
                'closedIssuesCount' => (int) $minute->closed_issues_count,
                'updatedAt' => optional($minute->updated_at)->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function apiMinutesManagementDetail(Event $event): JsonResponse
    {
        $event->load(['site', 'meetingType', 'eventMinute.issues']);

        if (! $event->eventMinute) {
            return response()->json(['ok' => false, 'message' => 'Notulensi tidak ditemukan untuk event ini'], 404);
        }

        $minutes = $this->mapMinutesToLegacy($event->eventMinute, $event);
        $issueSections = is_array($minutes['issueSections'] ?? null) ? $minutes['issueSections'] : [];
        $sectionTitleMap = collect($issueSections)->mapWithKeys(fn (array $section): array => [
            (string) ($section['id'] ?? '') => (string) ($section['title'] ?? ''),
        ]);

        $issues = $event->eventMinute->issues->sortBy(['section', 'nomor'])->values()->map(function (MinuteIssue $issue) use ($sectionTitleMap): array {
            $legacy = $this->mapIssueToLegacy($issue);

            return array_merge($legacy, [
                'sectionTitle' => $sectionTitleMap->get($issue->section) ?: $this->resolveIssueSectionTitle(null, (string) $issue->section),
            ]);
        });

        return response()->json([
            'ok' => true,
            'event' => $this->mapEventToLegacy($event, true),
            'issues' => $issues,
        ]);
    }

    public function apiUpdateMinuteIssueStatus(Request $request, MinuteIssue $issue): JsonResponse
    {
        $payload = $request->validate([
            'status' => 'required|in:Open,Progress,Overdue',
        ]);

        $issue->update([
            'status' => $payload['status'],
            'closed_at' => null,
            'closed_by_sid' => null,
            'closed_by_name' => null,
        ]);

        return response()->json([
            'ok' => true,
            'issue' => $this->mapIssueToLegacy($issue->fresh()),
        ]);
    }

    public function apiCloseMinuteIssue(Request $request, MinuteIssue $issue): JsonResponse
    {
        $payload = $request->validate([
            'kode_sid' => 'required|string|max:50',
        ]);

        $employee = Employee::query()
            ->where('kode_sid', strtoupper(trim($payload['kode_sid'])))
            ->where('is_active', true)
            ->first();

        if (! $employee) {
            return response()->json(['ok' => false, 'message' => 'SID verifikator tidak ditemukan'], 422);
        }

        $issue->update([
            'status' => 'Closed',
            'closed_at' => now(),
            'closed_by_sid' => $employee->kode_sid,
            'closed_by_name' => $employee->nama,
        ]);

        return response()->json([
            'ok' => true,
            'issue' => $this->mapIssueToLegacy($issue->fresh()),
        ]);
    }

    private function mapEventToLegacy(Event $event, bool $withMinutes): array
    {
        $minute = $withMinutes ? $event->eventMinute : null;

        return [
            'id' => (string) $event->id,
            'code' => $event->event_code,
            'qrToken' => $event->qr_token,
            'meetingType' => optional($event->meetingType)->name,
            'meetingLevel' => $event->meeting_level ?: 'site',
            'targetCompanies' => array_values($event->target_companies ?? []),
            'targetPositions' => array_values($event->target_positions ?? []),
            'targetDepartments' => array_values($event->target_departments ?? []),
            'site' => optional($event->site)->name,
            'date' => optional($event->meeting_date)->format('Y-m-d'),
            'week' => $event->week,
            'startTime' => substr((string) $event->start_time, 0, 5),
            'endTime' => substr((string) $event->end_time, 0, 5),
            'manualStatus' => $event->runtimeStatus(),
            'closedAt' => optional($event->closed_at)->toIso8601String(),
            'createdAt' => optional($event->created_at)->toIso8601String(),
            'updatedAt' => optional($event->updated_at)->toIso8601String(),
            'minutes' => $this->mapMinutesToLegacy($minute, $event),
        ];
    }

    private function buildEventActionsHtml(Event $event, string $runtimeStatus, string $qrLink): string
    {
        $id = (int) $event->id;
        $qrJs = addslashes($qrLink);
        $closeBtn = $runtimeStatus !== 'Closed'
            ? '<button type="button" onclick="event.stopPropagation(); askCloseMeeting(\'' . $id . '\', true)" class="rounded-xl bg-orange-50 px-3 py-2 text-xs font-black text-orange-700">Tutup</button>'
            : '';

        return '<div class="flex flex-wrap justify-end gap-1">'
            . '<button type="button" onclick="event.stopPropagation(); openQRModalById(' . $id . ')" class="rounded-xl bg-cyan-50 px-3 py-2 text-xs font-black text-cyan-700">QR</button>'
            . '<button type="button" onclick="event.stopPropagation(); openEventRecapModal(\'' . $id . '\')" class="rounded-xl bg-blue-50 px-3 py-2 text-xs font-black text-blue-700">Detail</button>'
            . '<button type="button" onclick="event.stopPropagation(); copyText(\'' . $qrJs . '\', this)" class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-700">Copy</button>'
            . $closeBtn
            . '</div>';
    }
}
