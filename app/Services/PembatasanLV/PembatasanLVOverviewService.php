<?php

declare(strict_types=1);

namespace App\Services\PembatasanLV;

use App\Models\PembatasanLvInputasi;
use App\Models\PembatasanOrangInputasi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PembatasanLVOverviewService
{
    private const LIVE_LIST_LIMIT = 100;

    private const HISTORY_LIST_LIMIT = 100;

    /** @var list<string> */
    public const FILTER_SITES = [
        'BMO 1',
        'BMO 2',
        'LMO',
        'SMO',
        'GMO',
        'BMO 3',
    ];

    /** @var array<string, list<string>> */
    private const SITE_ALIASES = [
        'BMO 1' => ['BMO 1', 'BMO1', 'BMO-1'],
        'BMO 2' => ['BMO 2', 'BMO2', 'BMO-2'],
        'BMO 3' => ['BMO 3', 'BMO3', 'BMO-3'],
        'LMO' => ['LMO'],
        'SMO' => ['SMO'],
        'GMO' => ['GMO'],
    ];

    /** @var list<string> */
    public const LV_LIVE_COLUMNS = [
        'id', 'nama_driver', 'no_lambung', 'checkin_at', 'lokasi', 'detail_lokasi',
    ];

    /** @var list<string> */
    public const LV_HISTORY_COLUMNS = [
        'id', 'no_lambung', 'nama_driver', 'control_room', 'lokasi', 'detail_lokasi',
        'checkin_at', 'checkout_at', 'shift',
    ];

    /** @var list<string> */
    public const ORANG_LIVE_COLUMNS = [
        'id', 'sid', 'nama', 'nama_perusahaan', 'checkin_at', 'lokasi', 'detail_lokasi',
    ];

    /** @var list<string> */
    public const ORANG_HISTORY_COLUMNS = [
        'id', 'sid', 'nama', 'nama_perusahaan', 'dept', 'control_room', 'lokasi',
        'detail_lokasi', 'checkin_at', 'checkout_at', 'shift',
    ];

    public function __construct(
        private readonly PembatasanLVControlRoomContextService $controlRoomContext,
    ) {}

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     * @return Collection<int, string>
     */
    public function supervisedRooms(?User $user, array $filters): Collection
    {
        $rooms = $this->controlRoomContext->controlRoomsForUser($user);

        $site = trim((string) ($filters['site'] ?? ''));
        if ($site !== '') {
            $siteRooms = $this->roomsMatchingSite($rooms, $site);
            if ($siteRooms->isNotEmpty()) {
                $rooms = $siteRooms;
            }
        }

        $filterRoom = trim((string) ($filters['control_room'] ?? ''));
        if ($filterRoom !== '') {
            $rooms = $rooms
                ->filter(fn (string $room) => strcasecmp($room, $filterRoom) === 0)
                ->values();
        }

        return $rooms;
    }

    /**
     * @return Collection<int, string>
     */
    public function siteOptions(?User $user = null): Collection
    {
        return collect(self::FILTER_SITES);
    }

    /**
     * @return Collection<int, string>
     */
    public function controlRoomOptions(?User $user, string $site): Collection
    {
        $userRooms = $this->controlRoomContext->controlRoomsForUser($user);
        $site = trim($site);
        if ($site === '') {
            return $userRooms->values();
        }

        $matched = $this->roomsMatchingSite($userRooms, $site);

        return $matched->isNotEmpty() ? $matched : $userRooms->values();
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function baseQuery(?User $user, array $filters): Builder
    {
        return $this->scopedLvQuery($user, $filters)->orderByDesc('checkin_at');
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function lvMasukAktifQuery(?User $user, array $filters): Builder
    {
        return $this->baseQuery($user, $filters)->whereNull('checkout_at');
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function lvKeluarQuery(?User $user, array $filters): Builder
    {
        [$start, $end] = $this->dayBounds($filters);

        return $this->baseQuery($user, $filters)
            ->whereNotNull('checkout_at')
            ->where('checkout_at', '>=', $start)
            ->where('checkout_at', '<', $end);
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function lvAllListQuery(?User $user, array $filters): Builder
    {
        $query = $this->baseQuery($user, $filters);
        $this->applyCheckinDayFilter($query, $filters);

        return $query;
    }

    public function userCanManageRecord(?User $user, PembatasanLvInputasi $record): bool
    {
        return $this->controlRoomContext
            ->controlRoomsForUser($user)
            ->contains(fn (string $room) => strcasecmp($room, (string) $record->control_room) === 0);
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function orangBaseQuery(?User $user, array $filters): Builder
    {
        return $this->scopedOrangQuery($user, $filters)->orderByDesc('checkin_at');
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function orangMasukAktifQuery(?User $user, array $filters): Builder
    {
        return $this->orangBaseQuery($user, $filters)->whereNull('checkout_at');
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function orangKeluarQuery(?User $user, array $filters): Builder
    {
        [$start, $end] = $this->dayBounds($filters);

        return $this->orangBaseQuery($user, $filters)
            ->whereNotNull('checkout_at')
            ->where('checkout_at', '>=', $start)
            ->where('checkout_at', '<', $end);
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function orangAllListQuery(?User $user, array $filters): Builder
    {
        $query = $this->orangBaseQuery($user, $filters);
        $this->applyCheckinDayFilter($query, $filters);

        return $query;
    }

    public function userCanManageOrangRecord(?User $user, PembatasanOrangInputasi $record): bool
    {
        return $this->controlRoomContext
            ->controlRoomsForUser($user)
            ->contains(fn (string $room) => strcasecmp($room, (string) $record->control_room) === 0);
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     * @return array{
     *     supervisedRooms: Collection<int, string>,
     *     sites: Collection<int, string>,
     *     controlRooms: Collection<int, string>,
     *     lvMasukAktif: int,
     *     lvKeluar: int,
     *     lvMasukAktifList: Collection<int, PembatasanLvInputasi>,
     *     lvAllList: Collection<int, PembatasanLvInputasi>,
     *     orangMasukAktif: int,
     *     orangKeluar: int,
     *     orangMasukAktifList: Collection<int, PembatasanOrangInputasi>,
     *     orangAllList: Collection<int, PembatasanOrangInputasi>
     * }
     */
    public function dashboardPayload(?User $user, array $filters): array
    {
        $supervisedRooms = $this->supervisedRooms($user, $filters);

        return [
            'supervisedRooms' => $supervisedRooms,
            'sites' => $this->siteOptions($user),
            'controlRooms' => $this->controlRoomOptions($user, (string) ($filters['site'] ?? '')),
            'lvMasukAktif' => $this->countLvMasukAktif($user, $filters),
            'lvKeluar' => $this->countLvKeluar($user, $filters),
            'lvMasukAktifList' => $this->lvMasukAktifQuery($user, $filters)
                ->select(self::LV_LIVE_COLUMNS)
                ->limit(self::LIVE_LIST_LIMIT)
                ->get(),
            'lvAllList' => $this->lvAllListQuery($user, $filters)
                ->select(self::LV_HISTORY_COLUMNS)
                ->limit(self::HISTORY_LIST_LIMIT)
                ->get(),
            'orangMasukAktif' => $this->countOrangMasukAktif($user, $filters),
            'orangKeluar' => $this->countOrangKeluar($user, $filters),
            'orangMasukAktifList' => $this->orangMasukAktifQuery($user, $filters)
                ->select(self::ORANG_LIVE_COLUMNS)
                ->limit(self::LIVE_LIST_LIMIT)
                ->get(),
            'orangAllList' => $this->orangAllListQuery($user, $filters)
                ->select(self::ORANG_HISTORY_COLUMNS)
                ->limit(self::HISTORY_LIST_LIMIT)
                ->get(),
        ];
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function countLvMasukAktif(?User $user, array $filters): int
    {
        return $this->scopedLvQuery($user, $filters)->whereNull('checkout_at')->count();
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function countLvKeluar(?User $user, array $filters): int
    {
        [$start, $end] = $this->dayBounds($filters);

        return $this->scopedLvQuery($user, $filters)
            ->whereNotNull('checkout_at')
            ->where('checkout_at', '>=', $start)
            ->where('checkout_at', '<', $end)
            ->count();
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function countOrangMasukAktif(?User $user, array $filters): int
    {
        return $this->scopedOrangQuery($user, $filters)->whereNull('checkout_at')->count();
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function countOrangKeluar(?User $user, array $filters): int
    {
        [$start, $end] = $this->dayBounds($filters);

        return $this->scopedOrangQuery($user, $filters)
            ->whereNotNull('checkout_at')
            ->where('checkout_at', '>=', $start)
            ->where('checkout_at', '<', $end)
            ->count();
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    public function dayBounds(array $filters): array
    {
        $timezone = (string) config('app.timezone', 'Asia/Makassar');
        $tanggal = trim((string) ($filters['tanggal'] ?? ''));

        try {
            $start = Carbon::parse($tanggal !== '' ? $tanggal : 'now', $timezone)->startOfDay();
        } catch (\Throwable) {
            $start = Carbon::now($timezone)->startOfDay();
        }

        return [$start, $start->copy()->addDay()];
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    private function scopedLvQuery(?User $user, array $filters): Builder
    {
        return $this->applyRoomScope(PembatasanLvInputasi::query(), $user, $filters, false);
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    private function scopedOrangQuery(?User $user, array $filters): Builder
    {
        return $this->applyRoomScope(PembatasanOrangInputasi::query(), $user, $filters, true);
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    private function applyRoomScope(Builder $query, ?User $user, array $filters, bool $isOrang): Builder
    {
        $rooms = $this->controlRoomContext->controlRoomsForUser($user);
        $filterRoom = trim((string) ($filters['control_room'] ?? ''));
        if ($filterRoom !== '') {
            $rooms = $rooms
                ->filter(fn (string $room) => strcasecmp($room, $filterRoom) === 0)
                ->values();
        }

        $site = trim((string) ($filters['site'] ?? ''));

        if ($isOrang) {
            if ($rooms->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereIn('control_room', $rooms->all());

            if ($site !== '') {
                $aliases = $this->siteAliases($site);
                $matchedRooms = $this->roomsMatchingSite($rooms, $site);
                $query->where(function (Builder $builder) use ($aliases, $matchedRooms): void {
                    $builder->whereIn('site', $aliases);
                    if ($matchedRooms->isNotEmpty()) {
                        $builder->orWhereIn('control_room', $matchedRooms->all());
                    }
                });
            }

            return $query;
        }

        if ($site !== '') {
            $matchedRooms = $this->roomsMatchingSite($rooms, $site);
            if ($matchedRooms->isNotEmpty()) {
                $rooms = $matchedRooms;
            } else {
                return $query->whereRaw('1 = 0');
            }
        }

        if ($rooms->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('control_room', $rooms->all());
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    private function applyCheckinDayFilter(Builder $query, array $filters): void
    {
        $tanggal = trim((string) ($filters['tanggal'] ?? ''));
        if ($tanggal === '') {
            return;
        }

        [$start, $end] = $this->dayBounds($filters);
        $query->where('checkin_at', '>=', $start)->where('checkin_at', '<', $end);
    }

    /**
     * @return Collection<int, string>
     */
    private function roomsMatchingSite(Collection $rooms, string $site): Collection
    {
        return $rooms
            ->filter(fn (string $room) => $this->textMatchesSite($room, $site))
            ->values();
    }

    /**
     * @return list<string>
     */
    private function siteAliases(string $site): array
    {
        return self::SITE_ALIASES[$site] ?? [$site];
    }

    private function textMatchesSite(string $haystack, string $site): bool
    {
        $haystack = strtoupper(trim($haystack));
        if ($haystack === '') {
            return false;
        }

        foreach ($this->siteAliases($site) as $alias) {
            $alias = strtoupper(trim($alias));
            if ($alias === '') {
                continue;
            }

            $pattern = '/(?<![A-Z0-9])'.preg_quote($alias, '/').'(?![A-Z0-9])/';
            if (preg_match($pattern, $haystack) === 1) {
                return true;
            }

            $compactHay = (string) preg_replace('/[\s\-]+/', '', $haystack);
            $compactAlias = (string) preg_replace('/[\s\-]+/', '', $alias);
            $compactPattern = '/(?<![A-Z0-9])'.preg_quote($compactAlias, '/').'(?![A-Z0-9])/';
            if ($compactAlias !== '' && preg_match($compactPattern, $compactHay) === 1) {
                return true;
            }
        }

        return false;
    }
}
