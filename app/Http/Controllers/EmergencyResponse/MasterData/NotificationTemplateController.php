<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\MasterData\NotificationTemplateRequest;
use App\Models\EmergencyResponse\MasterData\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    public const CHANNEL_OPTIONS = ['in_app' => 'In-App', 'email' => 'Email', 'both' => 'In-App & Email'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $templates = NotificationTemplate::query()
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.master-data.notification-template.index', compact('templates', 'q'));
    }

    public function create(): View
    {
        return view('EmergencyResponse.master-data.notification-template.form', [
            'template' => new NotificationTemplate(),
            'channelOptions' => self::CHANNEL_OPTIONS,
        ]);
    }

    public function edit(NotificationTemplate $notification_template): View
    {
        return view('EmergencyResponse.master-data.notification-template.form', [
            'template' => $notification_template,
            'channelOptions' => self::CHANNEL_OPTIONS,
        ]);
    }

    public function store(NotificationTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        NotificationTemplate::create($data);

        return redirect()->route('emergency-response.master-data.notification-templates.index')->with('success', 'Template notifikasi berhasil ditambahkan.');
    }

    public function update(NotificationTemplateRequest $request, NotificationTemplate $notification_template): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $notification_template->update($data);

        return redirect()->route('emergency-response.master-data.notification-templates.index')->with('success', 'Template notifikasi berhasil diperbarui.');
    }

    public function destroy(Request $request, NotificationTemplate $notification_template): RedirectResponse
    {
        $notification_template->update(['updated_by' => $request->user()->id]);
        $notification_template->delete();

        return redirect()->route('emergency-response.master-data.notification-templates.index')->with('success', 'Template notifikasi berhasil dihapus.');
    }
}
