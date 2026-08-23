<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyResponse\MasterData\EmailTemplateRequest;
use App\Models\EmergencyResponse\MasterData\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $templates = EmailTemplate::query()
            ->when($q !== '', fn ($query) => $query
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('EmergencyResponse.master-data.email-template.index', compact('templates', 'q'));
    }

    public function create(): View
    {
        return view('EmergencyResponse.master-data.email-template.form', ['template' => new EmailTemplate()]);
    }

    public function edit(EmailTemplate $email_template): View
    {
        return view('EmergencyResponse.master-data.email-template.form', ['template' => $email_template]);
    }

    public function store(EmailTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['created_by'] = $request->user()->id;

        EmailTemplate::create($data);

        return redirect()->route('emergency-response.master-data.email-templates.index')->with('success', 'Template email berhasil ditambahkan.');
    }

    public function update(EmailTemplateRequest $request, EmailTemplate $email_template): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['updated_by'] = $request->user()->id;

        $email_template->update($data);

        return redirect()->route('emergency-response.master-data.email-templates.index')->with('success', 'Template email berhasil diperbarui.');
    }

    public function destroy(Request $request, EmailTemplate $email_template): RedirectResponse
    {
        $email_template->update(['updated_by' => $request->user()->id]);
        $email_template->delete();

        return redirect()->route('emergency-response.master-data.email-templates.index')->with('success', 'Template email berhasil dihapus.');
    }
}
