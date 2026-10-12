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
     * @param  list<array<string, mixed>>|null  $tbcBasisCards  kartu tab TBC dari
     *     ControlRoomTbcBasisReader; null = pakai Hazard/Inspeksi dari $payload['cards']
     * @return array<string, mixed>
     */
    public function enrich(array $payload, ?array $tbcBasisCards = null): array
    {
        /** @var list<array<string, mixed>> $cards */
        $cards = $payload['cards'] ?? [];
        $basis = $tbcBasisCards ?? $cards;
        $lookup = $this->matchedSet(array_values(array_unique([
            ...$this->hazardInspeksiIds($cards),
            ...$this->hazardInspeksiIds($basis),
        ])));

        $annotated = [];
        foreach ($cards as $card) {
            $card['tbc'] = $this->isHazardInspeksi($card) ? $this->tbcFlag($card, $lookup) : null;
            $annotated[] = $card;
        }

        $tbcCards = [];
        $counts = ['all' => 0, 'sudah' => 0, 'belum' => 0];
        foreach ($basis as $card) {
            if (! $this->isHazardInspeksi($card)) {
                continue;
            }
            $card['tbc'] = $this->tbcFlag($card, $lookup);
            $tbcCards[] = $card;
            $counts['all']++;
            if ($card['tbc'] !== null) {
                $counts[$card['tbc']]++;
            }
        }

        $payload['cards'] = $annotated;
        $payload['tbc_loaded'] = $lookup['loaded'];
        $payload['tbc_counts'] = $counts;
        $payload['tbc_cards'] = $tbcCards;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function isHazardInspeksi(array $card): bool
    {
        $type = strtolower(trim((string) ($card['type'] ?? '')));
        $id = trim((string) ($card['id'] ?? ''));

        return in_array($type, ['hazard', 'inspeksi'], true) && $id !== '' && $id !== '—';
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  array{loaded: bool, set: array<string, true>}  $lookup
     */
    private function tbcFlag(array $card, array $lookup): ?string
    {
        if (! $lookup['loaded']) {
            return null;
        }

        return isset($lookup['set'][trim((string) ($card['id'] ?? ''))]) ? 'sudah' : 'belum';
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
