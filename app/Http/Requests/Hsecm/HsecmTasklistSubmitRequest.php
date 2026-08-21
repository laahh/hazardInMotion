<?php

declare(strict_types=1);

namespace App\Http\Requests\Hsecm;

use App\Services\Hsecm\HsecmTasklistEvidenceUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class HsecmTasklistSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = HsecmTasklistEvidenceUpload::appMaxKilobytes();
        $mimes = (string) config('hsecm.tasklist_evidence.mimes', 'jpg,jpeg,png,pdf,webp,doc,docx,xls,xlsx');

        return [
            'submitted_by_name' => ['required', 'string', 'max:150'],
            'remediation_notes' => ['required', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer', 'min:1'],
            'evidence_shared' => ['required', 'file', 'max:'.$maxKb, 'mimes:'.$mimes],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal satu item.',
            'remediation_notes.required' => 'Catatan perbaikan wajib diisi.',
            'submitted_by_name.required' => 'Nama pengirim wajib diisi.',
            'evidence_shared.required' => 'Upload satu file evidence untuk semua item yang dipilih.',
            'evidence_shared.file' => 'Upload satu file evidence untuk semua item yang dipilih.',
            'evidence_shared.uploaded' => HsecmTasklistEvidenceUpload::postMaxExceededMessage(),
            'evidence_shared.mimes' => 'Format evidence tidak didukung.',
            'evidence_shared.max' => 'Ukuran evidence maksimal 10 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('evidence_shared');

            if ($file instanceof UploadedFile && ! $file->isValid()) {
                $validator->errors()->forget('evidence_shared');
                $validator->errors()->add('evidence_shared', HsecmTasklistEvidenceUpload::errorMessage($file));

                return;
            }

            $contentLength = (int) $this->server('CONTENT_LENGTH', 0);
            $postMax = HsecmTasklistEvidenceUpload::parseIniSize((string) ini_get('post_max_size'));

            if (! $this->hasFile('evidence_shared') && $contentLength > 0 && $postMax > 0 && $contentLength > $postMax) {
                $validator->errors()->forget('evidence_shared');
                $validator->errors()->add(
                    'evidence_shared',
                    HsecmTasklistEvidenceUpload::postMaxExceededMessage()
                );
            }
        });
    }
}
