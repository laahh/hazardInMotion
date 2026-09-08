<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Models\ControlRoom\ScheduleChange;
use Illuminate\Support\Collection;

/**
 * Menyusun riwayat ganti personil harian: "Nama (SID) → Nama (SID)".
 */
final class ControlRoomScheduleChangePresenter
{
    /**
     * SID dan nama dari satu simpanan sering tercatat 1–2 detik terpisah.
     * Gabungkan selama field-nya belum ada di grup yang sama.
     */
    private const MERGE_WINDOW_SECONDS = 60;

    /**
     * @param  Collection<int, ScheduleChange>  $changes
     * @return list<array{at: string, by: string, reason: string, from: string, to: string, summary: string}>
     */
    public function timeline(Collection $changes): array
    {
        $groups = [];
        $index = -1;

        $sorted = $changes->sortBy(
            fn (ScheduleChange $change): int => $change->changed_at?->getTimestamp() ?? 0
        );

        foreach ($sorted as $change) {
            if ($index >= 0 && $this->belongsToGroup($groups[$index], $change)) {
                $groups[$index]['fields'][$change->field] = [
                    'old' => (string) $change->old_value,
                    'new' => (string) $change->new_value,
                ];
                continue;
            }

            $groups[] = [
                'at' => $change->changed_at?->copy()->timezone(config('app.timezone'))->format('d M Y H:i') ?? '—',
                'at_ts' => $change->changed_at?->getTimestamp() ?? 0,
                'changed_by' => (string) $change->changed_by,
                'by' => $change->changedBy?->name ?? '—',
                'reason' => trim((string) $change->reason),
                'fields' => [
                    $change->field => [
                        'old' => (string) $change->old_value,
                        'new' => (string) $change->new_value,
                    ],
                ],
            ];
            $index++;
        }

        $items = [];
        foreach (array_reverse($groups) as $group) {
            $from = $this->personLabel(
                $group['fields']['personnel_name_snapshot']['old'] ?? null,
                $group['fields']['personnel_source_key']['old'] ?? null,
            );
            $to = $this->personLabel(
                $group['fields']['personnel_name_snapshot']['new'] ?? null,
                $group['fields']['personnel_source_key']['new'] ?? null,
            );
            $parts = [];
            if ($from !== '—' || $to !== '—') {
                $parts[] = $from.' → '.$to;
            }
            if (isset($group['fields']['shift_code'])) {
                $parts[] = 'Shift '.$group['fields']['shift_code']['old'].' → '.$group['fields']['shift_code']['new'];
            }
            if ($parts === []) {
                continue;
            }

            $items[] = [
                'at' => $group['at'],
                'by' => $group['by'],
                'reason' => $group['reason'] !== '' ? $group['reason'] : 'Ganti personil harian',
                'from' => $from,
                'to' => $to,
                'summary' => implode(' · ', $parts),
            ];
        }

        return array_values($items);
    }

    public function personLabel(?string $name, ?string $sid): string
    {
        $name = trim((string) $name);
        $sid = strtoupper(trim((string) $sid));
        if ($name !== '' && $name !== '—' && $sid !== '') {
            return $name.' ('.$sid.')';
        }
        if ($name !== '' && $name !== '—') {
            return $name;
        }
        if ($sid !== '') {
            return $sid;
        }

        return '—';
    }

    /**
     * @param  array{at_ts: int, changed_by: string, reason: string, fields: array<string, array{old: string, new: string}>}  $group
     */
    private function belongsToGroup(array $group, ScheduleChange $change): bool
    {
        if ((string) $change->changed_by !== $group['changed_by']) {
            return false;
        }

        if (trim((string) $change->reason) !== $group['reason']) {
            return false;
        }

        if (isset($group['fields'][$change->field])) {
            return false;
        }

        $ts = $change->changed_at?->getTimestamp() ?? 0;

        return abs($ts - $group['at_ts']) <= self::MERGE_WINDOW_SECONDS;
    }
}
