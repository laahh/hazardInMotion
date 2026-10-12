<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Models\ControlRoom\ControlRoomTbcValidation;
use App\Services\ControlRoom\Source\GSheetTbcReader;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Menandai kartu Detail SAP dengan status TBC (Hazard/Inspeksi saja).
 */
final class ControlRoomSapDetailTbcEnricher
{
    public function __construct(
        private readonly GSheetTbcReader $gsheet = new GSheetTbcReader(),
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrich(array $payload): array
    {
        /** @var list<array<string, mixed>> $cards */
        $cards = $payload['cards'] ?? [];
        $lookup = $this->matchedSet($this->hazardInspeksiIds($cards));
        $annotated = [];
        $tbcCards = [];
        $counts = ['all' => 0, 'sudah' => 0, 'belum' => 0];

        foreach ($cards as $card) {
            $type = strtolower(trim((string) ($card['type'] ?? '')));
            $id = trim((string) ($card['id'] ?? ''));
            $tbc = null;
            if (in_array($type, ['hazard', 'inspeksi'], true) && $id !== '' && $id !== '—') {
                if ($lookup['loaded']) {
                    $tbc = isset($lookup['set'][$id]) ? 'sudah' : 'belum';
                }
                $card['tbc'] = $tbc;
                $tbcCards[] = $card;
                $counts['all']++;
                if ($tbc === 'sudah') {
                    $counts['sudah']++;
                }
                if ($tbc === 'belum') {
                    $counts['belum']++;
                }
            } else {
                $card['tbc'] = null;
            }
            $annotated[] = $card;
        }

        $payload['cards'] = $annotated;
        $payload['tbc_loaded'] = $lookup['loaded'];
        $payload['tbc_counts'] = $counts;
        $payload['tbc_cards'] = $tbcCards;

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return list<string>
     */
    private function hazardInspeksiIds(array $cards): array
    {
        $ids = [];
        foreach ($cards as $card) {
            $type = strtolower(trim((string) ($card['type'] ?? '')));
            if (! in_array($type, ['hazard', 'inspeksi'], true)) {
                continue;
            }
            $id = trim((string) ($card['id'] ?? ''));
            if ($id !== '' && $id !== '—') {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @param  list<string>  $ids
     * @return array{loaded: bool, set: array<string, true>}
     */
    private function matchedSet(array $ids): array
    {
        if ($ids === []) {
            return ['loaded' => false, 'set' => []];
        }

        if ($this->gsheet->isConfigured()) {
            $result = $this->gsheet->matchingRows($ids);
            if ($result['loaded']) {
                return ['loaded' => true, 'set' => $this->tasklistSet($result['rows'])];
            }
        }

        return $this->excelSet($ids);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, true>
     */
    private function tasklistSet(array $rows): array
    {
        $set = [];
        foreach ($rows as $row) {
            $tasklist = trim((string) ($row['tasklist'] ?? $row['Tasklist'] ?? $row['Task_Number'] ?? ''));
            if ($tasklist !== '') {
                $set[$tasklist] = true;
            }
        }

        return $set;
    }

    /**
     * @param  list<string>  $ids
     * @return array{loaded: bool, set: array<string, true>}
     */
    private function excelSet(array $ids): array
    {
        try {
            if (! Schema::hasTable('control_room_tbc_validations')) {
                return ['loaded' => false, 'set' => []];
            }

            $tasklists = ControlRoomTbcValidation::query()
                ->whereIn('tasklist', $ids)
                ->pluck('tasklist')
                ->all();
        } catch (Throwable $e) {
            Log::warning('ControlRoom Detail TBC Excel gagal: '.$e->getMessage());

            return ['loaded' => false, 'set' => []];
        }

        $set = [];
        foreach ($tasklists as $tasklist) {
            $tasklist = trim((string) $tasklist);
            if ($tasklist !== '') {
                $set[$tasklist] = true;
            }
        }

        return ['loaded' => true, 'set' => $set];
    }
}
