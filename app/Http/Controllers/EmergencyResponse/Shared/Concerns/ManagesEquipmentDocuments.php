<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Shared\Concerns;

use App\Models\EmergencyResponse\Shared\EquipmentDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Shared photo/document upload+delete for EmergencyEquipment and SafetyDevice
 * (both expose documents(): MorphMany via the shared er_equipment_documents table).
 */
trait ManagesEquipmentDocuments
{
    protected function storeDocumentFor(Model $owner, Request $request): void
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx'],
            'type' => ['required', 'in:photo,document'],
        ]);

        $file = $request->file('file');
        $path = $file->store('emergency-response/documents', 'public');

        $owner->documents()->create([
            'type' => $request->input('type'),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
        ]);
    }

    protected function deleteDocument(EquipmentDocument $document): void
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();
    }
}
