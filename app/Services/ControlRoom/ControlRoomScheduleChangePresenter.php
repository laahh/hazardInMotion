<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Models\ControlRoom\ScheduleChange;
use Illuminate\Support\Collection;

/**
 * Menyusun riwayat ganti personil harian: "sebelumnya siapa jadi siapa".
 */
final class ControlRoomScheduleChangePresenter
{
    /**
     * @param  Collection<int, ScheduleChange>  $changes
     * @return list<array{at: string, by: string, reason: string, from: string, to: string, summary: string}>
     */
    public function timeline(Collection $changes): array
    {
        $groups = [];
        foreach ($changes as $change) {
            $at = $change->changed_at?->format('Y-m-d H:i:s') ?? '';
            $key = $at.'|'.(string) $change->changed_by;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'at' => $change->changed_at?->copy()->timezone(config('app.timezone'))->format('d M Y H:i') ?? '—',
                    'by' => $change->changedBy?->name ?? '—',
                    'reason' => trim((string) $change->reason),
                    'fields' => [],
                ];
            }
            $groups[$key]['fields'][$change->field] = [
                'old' => (string) $change->old_value,
                'new' => (string) $change->new_value,
            ];
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
}
