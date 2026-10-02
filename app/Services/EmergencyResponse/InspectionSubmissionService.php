<?php

declare(strict_types=1);

namespace App\Services\EmergencyResponse;

use App\Models\EmergencyResponse\Equipment\EmergencyEquipment;
use App\Models\EmergencyResponse\Inspection\Inspection;
use App\Models\EmergencyResponse\Inspection\InspectionFinding;
use App\Models\EmergencyResponse\MasterData\ChecklistTemplate;
use App\Models\EmergencyResponse\MasterData\ChecklistTemplateItem;
use App\Models\EmergencyResponse\SafetyDevice\SafetyDevice;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menyimpan hasil inspeksi: nomor inspeksi, baris checklist, temuan untuk
 * jawaban "tidak sesuai", dan tanda tangan.
 *
 * Dipakai bersama oleh form publik /form/inspeksi-emergency dan modul internal,
 * supaya aturan penomoran dan pembuatan temuan tidak bercabang dua.
 */
class InspectionSubmissionService
{
    private const PHOTO_DIRECTORY = 'emergency-response/inspection-photos';

    private const SIGNATURE_DIRECTORY = 'emergency-response/signatures';

    /** Nomor inspeksi unik; tabrakan antar pengisi form dicoba ulang. */
    private const NUMBER_ATTEMPTS = 5;

    /**
     * @param  array<int, array<string, mixed>>  $items  tiap item: checklist_template_item_id,
     *                                                   answer_value, notes, photo_before, photo_after
     * @param  array<string, mixed>  $attributes  status, condition_result, notes, latitude,
     *                                            longitude, inspector_id, inspector_name,
     *                                            inspector_nik, inspector_sid, inspector_phone,
     *                                            created_by
     */
    public function submit(
        EmergencyEquipment|SafetyDevice $target,
        ChecklistTemplate $template,
        array $items,
        array $attributes,
        ?string $signatureData = null,
    ): Inspection {
        // Foto & tanda tangan ditulis di luar transaksi: file yang sudah
        // tersimpan tidak ikut ter-rollback, jadi lebih baik gagal lebih awal.
        $signaturePath = $this->storeSignature($signatureData);
        $rows = $this->prepareRows($template, $items);

        return $this->createWithUniqueNumber(function (string $number) use ($target, $template, $rows, $attributes, $signaturePath): Inspection {
            return DB::transaction(function () use ($number, $target, $template, $rows, $attributes, $signaturePath): Inspection {
                $inspection = Inspection::create([
                    'inspection_number' => $number,
                    'target_type' => $target::class,
                    'target_id' => $target->id,
                    'checklist_template_id' => $template->id,
                    'site_id' => $target->site_id,
                    'inspector_id' => $attributes['inspector_id'] ?? null,
                    'inspector_name' => $attributes['inspector_name'] ?? null,
                    'inspector_nik' => $attributes['inspector_nik'] ?? null,
                    'inspector_sid' => $attributes['inspector_sid'] ?? null,
                    'inspector_phone' => $attributes['inspector_phone'] ?? null,
                    'status' => $attributes['status'] ?? 'submitted',
                    'condition_result' => $attributes['condition_result'] ?? null,
                    'notes' => $attributes['notes'] ?? null,
                    'latitude' => $attributes['latitude'] ?? null,
                    'longitude' => $attributes['longitude'] ?? null,
                    'inspected_at' => now(),
                    'submitted_at' => ($attributes['status'] ?? 'submitted') === 'draft' ? null : now(),
                    'signature_path' => $signaturePath,
                    'created_by' => $attributes['created_by'] ?? null,
                ]);

                $this->saveRows($inspection, $rows, $attributes['created_by'] ?? null);

                return $inspection;
            });
        });
    }

    /**
     * Snapshot teks & tipe jawaban diambil dari template di database, bukan dari
     * input, supaya isian form publik tidak bisa memalsukan isi checklist.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function prepareRows(ChecklistTemplate $template, array $items): array
    {
        $templateItems = ChecklistTemplateItem::query()
            ->where('checklist_template_id', $template->id)
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach (array_values($items) as $item) {
            $templateItem = $templateItems->get($item['checklist_template_item_id'] ?? null);

            if ($templateItem === null) {
                continue;
            }

            $rows[] = [
                'template_item' => $templateItem,
                'answer_value' => $this->nullableText($item['answer_value'] ?? null),
                'notes' => $this->nullableText($item['notes'] ?? null),
                'photo_before' => $this->storePhoto($item['photo_before'] ?? null),
                'photo_after' => $this->storePhoto($item['photo_after'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function saveRows(Inspection $inspection, array $rows, ?int $createdBy): void
    {
        foreach ($rows as $index => $row) {
            /** @var ChecklistTemplateItem $templateItem */
            $templateItem = $row['template_item'];

            $result = $inspection->results()->create([
                'checklist_template_item_id' => $templateItem->id,
                'sort_order' => $index,
                'item_text_snapshot' => $templateItem->item_text,
                'answer_type_snapshot' => $templateItem->answer_type,
                'answer_value' => $row['answer_value'],
                'notes' => $row['notes'],
                'photo_before_path' => $row['photo_before'],
                'photo_after_path' => $row['photo_after'],
            ]);

            if (! $result->isNonCompliant()) {
                continue;
            }

            InspectionFinding::create([
                'inspection_id' => $inspection->id,
                'inspection_result_id' => $result->id,
                'description' => $this->findingDescription($templateItem->item_text, $row['notes']),
                'status' => 'open',
                'created_by' => $createdBy,
            ]);
        }

        if ($inspection->status === 'submitted' && $inspection->findings()->exists()) {
            $inspection->update(['status' => 'follow_up_required']);
        }
    }

    private function findingDescription(string $itemText, ?string $notes): string
    {
        return 'Temuan: '.$itemText.($notes !== null ? ' — '.$notes : '');
    }

    /**
     * Nomor inspeksi dihitung dari nomor terakhir, bukan dari COUNT(*), supaya
     * penghapusan data tidak membuat nomor terpakai ulang.
     *
     * @param  callable(string): Inspection  $callback
     */
    private function createWithUniqueNumber(callable $callback): Inspection
    {
        for ($attempt = 1; $attempt <= self::NUMBER_ATTEMPTS; $attempt++) {
            try {
                return $callback($this->nextNumber($attempt - 1));
            } catch (QueryException $e) {
                if ($attempt === self::NUMBER_ATTEMPTS || ! $this->isDuplicateNumber($e)) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Gagal membuat nomor inspeksi yang unik.');
    }

    private function nextNumber(int $offset): string
    {
        $prefix = 'INSP-'.now()->format('Y').'-';

        // Sufiks di-pad 6 digit, jadi urutan abjad sama dengan urutan angka.
        $last = Inspection::withTrashed()
            ->where('inspection_number', 'like', $prefix.'%')
            ->orderByDesc('inspection_number')
            ->value('inspection_number');

        $sequence = $last === null ? 0 : (int) substr((string) $last, strlen($prefix));

        return $prefix.str_pad((string) ($sequence + 1 + $offset), 6, '0', STR_PAD_LEFT);
    }

    private function isDuplicateNumber(\Throwable $e): bool
    {
        return $e instanceof UniqueConstraintViolationException
            || str_contains($e->getMessage(), 'inspection_number');
    }

    private function storePhoto(mixed $photo): ?string
    {
        return $photo instanceof UploadedFile
            ? $photo->store(self::PHOTO_DIRECTORY, 'public')
            : null;
    }

    private function storeSignature(?string $signatureData): ?string
    {
        if ($signatureData === null || ! str_contains($signatureData, 'base64,')) {
            return null;
        }

        [, $base64] = explode('base64,', $signatureData, 2);
        $binary = base64_decode($base64, true);

        if ($binary === false || $binary === '') {
            return null;
        }

        $path = self::SIGNATURE_DIRECTORY.'/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
