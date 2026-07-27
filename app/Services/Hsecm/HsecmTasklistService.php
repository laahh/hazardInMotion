<?php

declare(strict_types=1);

namespace App\Services\Hsecm;

use App\Models\Hsecm\HsecmTasklist;
use App\Models\Hsecm\HsecmTasklistEvidence;
use App\Models\Hsecm\HsecmTasklistItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class HsecmTasklistService
{
    public function __construct(
        private readonly HsecmDatabaseRepository $repository,
    ) {}

    public function tablesAvailable(): bool
    {
        try {
            return Schema::hasTable('hsecm_tasklists')
                && Schema::hasTable('hsecm_tasklist_items')
                && Schema::hasTable('hsecm_tasklist_evidences');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Buat / update tasklist endshift untuk scope site+perusahaan.
     *
     * @param  array{site?: string, perusahaan: string}  $scope
     * @param  list<array<string, mixed>>  $gapItems
     */
    public function createFromEndshift(string $batchSlot, array $scope, array $gapItems): HsecmTasklist
    {
        if (! $this->tablesAvailable()) {
            throw new RuntimeException('Tabel tasklist HSECM belum tersedia. Jalankan migration dulu.');
        }

        $site = trim((string) ($scope['site'] ?? ''));
        $perusahaan = trim((string) ($scope['perusahaan'] ?? ''));
        if ($perusahaan === '') {
            throw new RuntimeException('Scope perusahaan wajib untuk membuat tasklist.');
        }

        $slot = Carbon::parse($batchSlot);
        $nextEscalate = $this->firstEscalateAt($slot);

        return DB::transaction(function () use ($slot, $site, $perusahaan, $gapItems, $nextEscalate): HsecmTasklist {
            /** @var HsecmTasklist $tasklist */
            $tasklist = HsecmTasklist::query()->firstOrNew([
                'batch_slot' => $slot->format('Y-m-d H:i:s'),
                'site' => $site !== '' ? $site : null,
                'perusahaan' => $perusahaan,
            ]);

            if (! $tasklist->exists) {
                $tasklist->token = Str::random(48);
                $tasklist->status = 'open';
                $tasklist->escalate_count = 0;
                $tasklist->next_escalate_at = $nextEscalate;
            }

            if ($tasklist->status === 'closed') {
                // Jangan buka ulang closed; buat token baru hanya jika belum closed
                return $tasklist->load('items');
            }

            $tasklist->save();

            foreach ($gapItems as $item) {
                $businessKey = trim((string) ($item['business_key'] ?? ''));
                $programKey = trim((string) ($item['program_key'] ?? ''));
                if ($businessKey === '' || $programKey === '') {
                    continue;
                }

                $existing = HsecmTasklistItem::query()
                    ->where('tasklist_id', $tasklist->id)
                    ->where('program_key', $programKey)
                    ->where('business_key', $businessKey)
                    ->first();

                if ($existing !== null) {
                    // Jangan overwrite item yang sudah submitted/approved
                    if (in_array($existing->status, ['submitted', 'approved'], true)) {
                        continue;
                    }
                    $existing->fill([
                        'title' => (string) ($item['title'] ?? $existing->title),
                        'action_hint' => (string) ($item['action_hint'] ?? $existing->action_hint),
                        'value_label' => (string) ($item['value_label'] ?? $existing->value_label),
                        'payload' => $item['payload'] ?? $existing->payload,
                    ])->save();

                    continue;
                }

                HsecmTasklistItem::query()->create([
                    'tasklist_id' => $tasklist->id,
                    'program_key' => $programKey,
                    'title' => (string) ($item['title'] ?? $programKey),
                    'business_key' => $businessKey,
                    'action_hint' => (string) ($item['action_hint'] ?? ''),
                    'value_label' => (string) ($item['value_label'] ?? ''),
                    'payload' => $item['payload'] ?? [],
                    'status' => 'open',
                ]);
            }

            $this->recomputeTasklistStatus($tasklist->fresh(['items']));

            return $tasklist->fresh(['items']);
        });
    }

    public function findByToken(string $token): ?HsecmTasklist
    {
        return HsecmTasklist::query()
            ->where('token', $token)
            ->with(['items.evidences'])
            ->first();
    }

    /**
     * Lengkapi tiap item dengan jumlah kemunculan di batch_slot sebelum batch tasklist.
     * Slot yang tidak punya item tidak dihitung.
     *
     * @param  \Illuminate\Support\Collection<int, HsecmTasklistItem>|iterable<HsecmTasklistItem>  $items
     * @return \Illuminate\Support\Collection<int, HsecmTasklistItem>
     */
    public function withPreviousRecurrenceCounts(HsecmTasklist $tasklist, iterable $items): \Illuminate\Support\Collection
    {
        $collection = collect($items);
        $beforeSlot = optional($tasklist->batch_slot)?->format('Y-m-d H:i:s');
        if ($beforeSlot === null || $collection->isEmpty()) {
            return $collection->each(function (HsecmTasklistItem $item): void {
                $item->setAttribute('previous_recurrence_count', $this->fallbackPreviousFromPayload($item));
            });
        }

        /** @var array<string, list<string>> $keysByTable */
        $keysByTable = [];
        foreach ($collection as $item) {
            $payload = is_array($item->payload) ? $item->payload : [];
            $table = trim((string) ($payload['table'] ?? ''));
            $businessKey = trim((string) ($item->business_key ?? ''));
            if ($table === '' || $businessKey === '') {
                continue;
            }
            $keysByTable[$table][] = $businessKey;
        }

        /** @var array<string, array<string, int>> $countsByTable */
        $countsByTable = [];
        foreach ($keysByTable as $table => $keys) {
            $countsByTable[$table] = $this->repository->countPreviousAppearancesByKeys(
                $table,
                $keys,
                $beforeSlot
            );
        }

        return $collection->each(function (HsecmTasklistItem $item) use ($countsByTable): void {
            $payload = is_array($item->payload) ? $item->payload : [];
            $table = trim((string) ($payload['table'] ?? ''));
            $businessKey = trim((string) ($item->business_key ?? ''));
            $count = 0;
            if ($table !== '' && $businessKey !== '') {
                $count = (int) ($countsByTable[$table][$businessKey] ?? 0);
            }
            if ($count <= 0) {
                $count = $this->fallbackPreviousFromPayload($item);
            }
            $item->setAttribute('previous_recurrence_count', $count);
        });
    }

    private function fallbackPreviousFromPayload(HsecmTasklistItem $item): int
    {
        $payload = is_array($item->payload) ? $item->payload : [];
        $gapCount = (int) ($payload['gap_count'] ?? 0);

        // gap_count = streak termasuk slot sekarang → hari sebelumnya = max(0, gap_count - 1)
        return max(0, $gapCount - 1);
    }

    /**
     * Submit item oleh PJO (publik) — 1 file evidence untuk semua item terpilih.
     *
     * @param  list<int>  $itemIds
     * @return array{success: bool, message: string}
     */
    public function submitItems(
        HsecmTasklist $tasklist,
        array $itemIds,
        string $submittedByName,
        string $notes,
        UploadedFile $sharedEvidence,
    ): array {
        if ($tasklist->isClosed()) {
            throw ValidationException::withMessages([
                'items' => 'Tasklist sudah closed; tidak bisa submit lagi.',
            ]);
        }

        $submittedByName = trim($submittedByName);
        $notes = trim($notes);
        if ($submittedByName === '') {
            throw ValidationException::withMessages(['submitted_by_name' => 'Nama pengirim wajib diisi.']);
        }
        if ($notes === '') {
            throw ValidationException::withMessages(['remediation_notes' => 'Catatan perbaikan wajib diisi.']);
        }
        if ($itemIds === []) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu item.']);
        }

        $items = HsecmTasklistItem::query()
            ->where('tasklist_id', $tasklist->id)
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->count() !== count(array_unique($itemIds))) {
            throw ValidationException::withMessages(['items' => 'Ada item yang tidak valid untuk tasklist ini.']);
        }

        foreach ($items as $item) {
            if (! $item->canSubmit()) {
                throw ValidationException::withMessages([
                    'items' => 'Item #'.$item->id.' berstatus '.$item->status.' dan tidak bisa di-submit.',
                ]);
            }
        }

        DB::transaction(function () use ($items, $submittedByName, $notes, $sharedEvidence, $tasklist): void {
            $path = $sharedEvidence->store(
                'hsecm/tasklist-evidence/'.now()->format('Y').'/'.now()->format('m'),
                'public'
            );
            $originalName = $sharedEvidence->getClientOriginalName();
            $mimeType = $sharedEvidence->getClientMimeType();
            $fileSize = (int) $sharedEvidence->getSize();

            foreach ($items as $item) {
                $batch = ((int) $item->submission_batch) + 1;

                HsecmTasklistEvidence::query()->create([
                    'tasklist_item_id' => $item->id,
                    'file_path' => $path,
                    'original_name' => $originalName,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'submission_batch' => $batch,
                ]);

                $item->fill([
                    'status' => 'submitted',
                    'remediation_notes' => $notes,
                    'submitted_by_name' => $submittedByName,
                    'submitted_at' => now(),
                    'submission_batch' => $batch,
                    'rejection_reason' => null,
                    'reviewed_by' => null,
                    'reviewed_by_name' => null,
                    'reviewed_at' => null,
                ])->save();
            }

            $this->recomputeTasklistStatus($tasklist->fresh(['items']));
        });

        return [
            'success' => true,
            'message' => 'Submit berhasil. Menunggu ACC dari HSE.',
        ];
    }

    public function approveItem(HsecmTasklistItem $item, User $reviewer): void
    {
        if ($item->status !== 'submitted') {
            throw ValidationException::withMessages([
                'item' => 'Hanya item berstatus submitted yang bisa di-ACC.',
            ]);
        }

        $item->fill([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_by_name' => (string) ($reviewer->name ?? $reviewer->email ?? 'HSE'),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $this->recomputeTasklistStatus($item->tasklist()->with('items')->first());
    }

    public function rejectItem(HsecmTasklistItem $item, User $reviewer, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Alasan penolakan wajib diisi.',
            ]);
        }
        if ($item->status !== 'submitted') {
            throw ValidationException::withMessages([
                'item' => 'Hanya item berstatus submitted yang bisa ditolak.',
            ]);
        }

        $item->fill([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_by_name' => (string) ($reviewer->name ?? $reviewer->email ?? 'HSE'),
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        $tasklist = $item->tasklist()->with('items')->first();
        $this->recomputeTasklistStatus($tasklist);

        // Tolak → pastikan escalate tetap bisa jalan
        if ($tasklist !== null && $tasklist->status !== 'closed' && $tasklist->next_escalate_at === null) {
            $tasklist->next_escalate_at = now()->timezone('Asia/Makassar');
            $tasklist->save();
        }
    }

    public function recomputeTasklistStatus(?HsecmTasklist $tasklist): void
    {
        if ($tasklist === null) {
            return;
        }

        $items = $tasklist->relationLoaded('items')
            ? $tasklist->items
            : $tasklist->items()->get();

        if ($items->isEmpty()) {
            $tasklist->status = 'open';
            $tasklist->closed_at = null;
            $tasklist->save();

            return;
        }

        $allApproved = $items->every(fn (HsecmTasklistItem $i): bool => $i->status === 'approved');
        if ($allApproved) {
            $tasklist->status = 'closed';
            $tasklist->closed_at = now();
            $tasklist->next_escalate_at = null;
            $tasklist->save();

            return;
        }

        $hasProgress = $items->contains(
            fn (HsecmTasklistItem $i): bool => in_array($i->status, ['submitted', 'approved', 'rejected'], true)
        );

        $tasklist->status = $hasProgress ? 'partial' : 'open';
        $tasklist->closed_at = null;
        $tasklist->save();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, HsecmTasklist>
     */
    public function listDueForEscalate(?Carbon $now = null)
    {
        $now = $now ?? now()->timezone('Asia/Makassar');

        return HsecmTasklist::query()
            ->where('status', '!=', 'closed')
            ->whereNotNull('next_escalate_at')
            ->where('next_escalate_at', '<=', $now)
            ->with(['items'])
            ->orderBy('next_escalate_at')
            ->get();
    }

    public function markEscalated(HsecmTasklist $tasklist, ?Carbon $now = null): void
    {
        $now = $now ?? now()->timezone('Asia/Makassar');
        $tasklist->escalate_count = ((int) $tasklist->escalate_count) + 1;
        $tasklist->last_escalated_at = $now;
        $tasklist->next_escalate_at = $now->copy()->addHours(6);
        $tasklist->save();
    }

    /**
     * Ringkasan item belum approved untuk email escalate.
     *
     * @return array{open: int, submitted: int, rejected: int, approved: int, pending_items: list<array<string, mixed>>}
     */
    public function escalateSummary(HsecmTasklist $tasklist): array
    {
        $items = $tasklist->relationLoaded('items') ? $tasklist->items : $tasklist->items()->get();
        $pending = $items->filter(fn (HsecmTasklistItem $i): bool => $i->status !== 'approved');

        return [
            'open' => $items->where('status', 'open')->count(),
            'submitted' => $items->where('status', 'submitted')->count(),
            'rejected' => $items->where('status', 'rejected')->count(),
            'approved' => $items->where('status', 'approved')->count(),
            'pending_items' => $pending->take(40)->map(fn (HsecmTasklistItem $i): array => [
                'id' => $i->id,
                'title' => $i->title,
                'value_label' => $i->value_label,
                'status' => $i->status,
                'action_hint' => $i->action_hint,
                'rejection_reason' => $i->rejection_reason,
            ])->values()->all(),
        ];
    }

    public function publicUrl(HsecmTasklist $tasklist): string
    {
        $base = rtrim((string) config('hsecm.public_url', 'https://besentry-dev.beraucoal.co.id'), '/');

        return $base.'/hsecm/tasklist/'.$tasklist->token;
    }

    private function firstEscalateAt(Carbon $batchSlot): Carbon
    {
        // H+1 08:00 WITA relatif terhadap tanggal batch_slot
        return $batchSlot->copy()
            ->timezone('Asia/Makassar')
            ->startOfDay()
            ->addDay()
            ->setTime(8, 0, 0);
    }
}
